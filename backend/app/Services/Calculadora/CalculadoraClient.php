<?php

namespace App\Services\Calculadora;

use App\Services\Calculadora\Dto\VersaoInfo;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Shared HTTP behaviour for both official calculators: timeout, retry on connection
 * failures / 5xx, structured logging and Redis cache for open-data endpoints.
 */
abstract class CalculadoraClient
{
    /** Short name used in logs, cache keys and audit records. */
    abstract public function name(): string;

    /** Path of the version endpoint (different in each calculator). */
    abstract protected function versaoPath(): string;

    public function __construct(protected readonly array $config) {}

    public function versao(): VersaoInfo
    {
        return VersaoInfo::fromArray($this->get($this->versaoPath()));
    }

    protected function http(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->config['base_url'], '/'))
            ->acceptJson()
            ->asJson()
            ->timeout($this->config['timeout'] ?? 60)
            ->connectTimeout($this->config['connect_timeout'] ?? 5)
            ->retry(
                ($this->config['retries'] ?? 2) + 1,
                $this->config['retry_sleep_ms'] ?? 500,
                fn (Throwable $e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && $e->response->serverError()),
                throw: false,
            );
    }

    /**
     * @return array<mixed>|null null when $nullOn404 is true and the calculator answers 404
     */
    protected function get(string $path, array $query = [], bool $nullOn404 = false): ?array
    {
        return $this->send('GET', $path, $query, $nullOn404);
    }

    /** @return array<mixed> */
    protected function post(string $path, array $payload): array
    {
        return $this->send('POST', $path, $payload);
    }

    /** Cached GET for open data (NCM, NBS, cClassTrib, rates, activities). */
    protected function cachedGet(string $path, array $query = [], bool $nullOn404 = false): ?array
    {
        ksort($query);
        $key = sprintf('calc:%s:%s:%s', $this->name(), trim($path, '/'), md5(http_build_query($query)));

        return $this->remember($key, fn () => $this->get($path, $query, $nullOn404));
    }

    protected function remember(string $key, Closure $callback): mixed
    {
        $store = Cache::store(config('calculadoras.cache_store'));
        $cached = $store->get($key);
        if ($cached !== null) {
            return $cached === '__null__' ? null : $cached;
        }

        $value = $callback();
        $store->put($key, $value ?? '__null__', config('calculadoras.cache_ttl', 86400));

        return $value;
    }

    private function send(string $method, string $path, array $data = [], bool $nullOn404 = false): ?array
    {
        $started = microtime(true);
        $path = ltrim($path, '/');

        try {
            /** @var Response $response */
            $response = $method === 'GET'
                ? $this->http()->get($path, $data)
                : $this->http()->post($path, $data);
        } catch (ConnectionException $e) {
            $this->log('error', $method, $path, null, $started, ['error' => $e->getMessage()]);
            throw new CalculadoraException(
                "Calculadora {$this->name()} indisponível: {$e->getMessage()}", $this->name(), previous: $e,
            );
        }

        $this->log($response->successful() ? 'info' : 'warning', $method, $path, $response->status(), $started);

        if ($nullOn404 && $response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw new CalculadoraException(
                "Calculadora {$this->name()} respondeu HTTP {$response->status()} em {$method} /{$path}",
                $this->name(),
                $response->status(),
                $response->json() ?? $response->body(),
            );
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new CalculadoraException(
                "Resposta não-JSON da calculadora {$this->name()} em {$method} /{$path}",
                $this->name(), $response->status(), $response->body(),
            );
        }

        return $json;
    }

    private function log(string $level, string $method, string $path, ?int $status, float $started, array $extra = []): void
    {
        Log::channel(config('calculadoras.log_channel', 'stack'))->{$level}('calculadora.request', [
            'calculadora' => $this->name(),
            'method' => $method,
            'path' => '/'.$path,
            'status' => $status,
            'ms' => (int) round((microtime(true) - $started) * 1000),
        ] + $extra);
    }
}

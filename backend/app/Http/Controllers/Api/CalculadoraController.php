<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Calculadora\CalculadoraException;
use App\Services\Calculadora\RegimeGeralClient;
use App\Services\Calculadora\SimplesNacionalClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Calculator status and open data proxied (and cached) for the SPA. */
class CalculadoraController extends Controller
{
    public function __construct(
        private readonly RegimeGeralClient $regimeGeral,
        private readonly SimplesNacionalClient $simples,
    ) {}

    public function status(): JsonResponse
    {
        $status = [];
        foreach ([$this->regimeGeral, $this->simples] as $client) {
            try {
                $status[$client->name()] = ['online' => true, 'versao' => $client->versao()->toArray()];
            } catch (CalculadoraException $e) {
                $status[$client->name()] = ['online' => false, 'error' => $e->getMessage()];
            }
        }

        return response()->json($status);
    }

    public function atividades(Request $request): JsonResponse
    {
        $data = $request->validate([
            'data' => ['nullable', 'date_format:Y-m-d'],
            'anexos' => ['nullable', 'array'],
            'anexos.*' => ['string', 'max:5'],
        ]);

        $filters = array_filter(['anexos' => $data['anexos'] ?? null]);

        return response()->json([
            'data' => $this->simples->atividades($data['data'] ?? now()->toDateString(), $filters),
        ]);
    }

    public function ncm(Request $request): JsonResponse
    {
        $data = $request->validate(['ncm' => ['required', 'digits:8'], 'data' => ['nullable', 'date_format:Y-m-d']]);
        $result = $this->regimeGeral->ncm($data['ncm'], $data['data'] ?? now()->toDateString());

        return $result === null
            ? response()->json(['message' => 'NCM não encontrada.'], 404)
            : response()->json(['data' => $result]);
    }

    public function nbs(Request $request): JsonResponse
    {
        $data = $request->validate(['nbs' => ['required', 'digits:9'], 'data' => ['nullable', 'date_format:Y-m-d']]);
        $result = $this->regimeGeral->nbs($data['nbs'], $data['data'] ?? now()->toDateString());

        return $result === null
            ? response()->json(['message' => 'NBS não encontrada.'], 404)
            : response()->json(['data' => $result]);
    }

    public function classificacao(Request $request, string $cClassTrib): JsonResponse
    {
        abort_unless(preg_match('/^\d{6}$/', $cClassTrib), 422, 'cClassTrib inválido.');
        $data = $request->validate(['data' => ['nullable', 'date_format:Y-m-d']]);
        $result = $this->regimeGeral->classificacaoTributaria($cClassTrib, $data['data'] ?? now()->toDateString());

        return $result === null
            ? response()->json(['message' => 'Classificação tributária não encontrada.'], 404)
            : response()->json(['data' => $result]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Office;
use App\Models\User;
use App\Rules\Cnpj;
use App\Support\Documento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** Office sign-up: creates the tenant and its first admin user. */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'office_name' => ['required', 'string', 'max:255'],
            'office_cnpj' => ['required', new Cnpj],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [], [
            'office_name' => 'nome do escritório',
            'office_cnpj' => 'CNPJ do escritório',
            'name' => 'nome',
            'password' => 'senha',
        ]);

        $cnpj = Documento::limpar($data['office_cnpj']);
        if (Office::where('cnpj', $cnpj)->exists()) {
            throw ValidationException::withMessages(['office_cnpj' => 'Já existe um escritório cadastrado com este CNPJ.']);
        }

        $user = DB::transaction(function () use ($data, $cnpj) {
            $office = Office::create([
                'name' => $data['office_name'],
                'cnpj' => $cnpj,
                'email' => $data['email'],
                'plan' => config('plans.default'),
                'plan_status' => 'trialing',
                'trial_ends_at' => now()->addDays(config('plans.trial_days')),
            ]);

            return User::create([
                'office_id' => $office->id,
                'role' => User::ROLE_ADMIN,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);
        });

        return response()->json($this->tokenPayload($user), 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = strtolower($data['email']);
        // Failed attempts only (a successful login is never blocked by someone else's failures on another IP):
        //  - per e-mail + client network (IPv6 grouped by /64): 5 failures / 5 min;
        //  - per e-mail, account-wide: high cap (100 failures / hour) against distributed attacks,
        //    with a warning log from 10 failures on for alerting. Future hardening: CAPTCHA.
        $keySource = 'login-fail:'.$email.'|'.self::clientNetwork((string) $request->ip());
        $keyAccount = 'login-fail-account:'.$email;

        if (RateLimiter::tooManyAttempts($keySource, 5) || RateLimiter::tooManyAttempts($keyAccount, 100)) {
            $seconds = max(RateLimiter::availableIn($keySource), RateLimiter::availableIn($keyAccount));
            throw ValidationException::withMessages([
                'email' => "Muitas tentativas de login. Tente novamente em {$seconds} segundos.",
            ])->status(429);
        }

        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($keySource, 300);
            $accountFailures = RateLimiter::hit($keyAccount, 3600);
            if ($accountFailures >= 10) {
                Log::warning('auth.login_failures', ['email_hash' => hash('sha256', $email), 'failures_last_hour' => $accountFailures]);
            }
            throw ValidationException::withMessages(['email' => 'E-mail ou senha inválidos.']);
        }

        RateLimiter::clear($keySource);

        return response()->json($this->tokenPayload($user));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Sessão encerrada.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()->load('office')]);
    }

    /** IPv4 as is; IPv6 reduced to its /64 prefix (one client usually owns a whole /64). */
    private static function clientNetwork(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($ip);

            return $packed === false ? $ip : bin2hex(substr($packed, 0, 8)).'::/64';
        }

        return $ip;
    }

    private function tokenPayload(User $user): array
    {
        return [
            'token' => $user->createToken('spa')->plainTextToken,
            'user' => $user->load('office'),
        ];
    }
}

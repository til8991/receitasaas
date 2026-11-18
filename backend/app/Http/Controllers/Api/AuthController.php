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
        // Failed attempts are counted per e-mail + IP: blocks brute force from one source without letting
        // a third party lock the real user out (no per-e-mail-only hard block). Plus the per-IP route limit.
        // Future hardening: CAPTCHA after N failures.
        $keyIp = 'login-fail:'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($keyIp, 5)) {
            $seconds = RateLimiter::availableIn($keyIp);
            throw ValidationException::withMessages([
                'email' => "Muitas tentativas de login. Tente novamente em {$seconds} segundos.",
            ])->status(429);
        }

        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($keyIp, 300);
            throw ValidationException::withMessages(['email' => 'E-mail ou senha inválidos.']);
        }

        RateLimiter::clear($keyIp);

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

    private function tokenPayload(User $user): array
    {
        return [
            'token' => $user->createToken('spa')->plainTextToken,
            'user' => $user->load('office'),
        ];
    }
}

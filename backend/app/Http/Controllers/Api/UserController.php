<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/** Office users (admin only). Users are scoped to the authenticated user's office. */
class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('admin');

        return response()->json([
            'data' => User::where('office_id', $request->user()->office_id)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('admin');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(User::ROLES)],
            'password' => ['required', Password::min(8)->letters()->numbers()],
        ]);

        $user = User::create($data + ['office_id' => $request->user()->office_id]);

        return response()->json(['data' => $user], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        Gate::authorize('admin');
        $user = $this->findInOffice($request, $id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['sometimes', Rule::in(User::ROLES)],
            'password' => ['sometimes', 'nullable', Password::min(8)->letters()->numbers()],
        ]);
        if (empty($data['password'])) {
            unset($data['password']);
        }

        if (($data['role'] ?? $user->role) !== User::ROLE_ADMIN && $user->isAdmin()) {
            $this->ensureAnotherAdmin($request, $user);
        }

        $credentialsChanged = isset($data['password'])
            || (isset($data['email']) && $data['email'] !== $user->email)
            || (isset($data['role']) && $data['role'] !== $user->role);

        $user->update($data);

        // Revoke sessions when credentials or privileges change (keeps the caller's own current token).
        if ($credentialsChanged) {
            $current = $request->user()->currentAccessToken();
            $user->tokens()
                ->when($user->id === $request->user()->id && $current, fn ($q) => $q->whereKeyNot($current->id))
                ->delete();
        }

        return response()->json(['data' => $user]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        Gate::authorize('admin');
        $user = $this->findInOffice($request, $id);

        if ($user->id === $request->user()->id) {
            throw ValidationException::withMessages(['user' => 'Você não pode remover o próprio usuário.']);
        }
        if ($user->isAdmin()) {
            $this->ensureAnotherAdmin($request, $user);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(status: 204);
    }

    private function findInOffice(Request $request, int $id): User
    {
        return User::where('office_id', $request->user()->office_id)->findOrFail($id);
    }

    private function ensureAnotherAdmin(Request $request, User $user): void
    {
        $admins = User::where('office_id', $request->user()->office_id)
            ->where('role', User::ROLE_ADMIN)->whereKeyNot($user->id)->count();
        if ($admins === 0) {
            throw ValidationException::withMessages(['role' => 'O escritório precisa ter ao menos um administrador.']);
        }
    }
}

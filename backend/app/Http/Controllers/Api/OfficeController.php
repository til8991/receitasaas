<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Comparador\Premissas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class OfficeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $office = $request->user()->office;

        return response()->json([
            'office' => $office,
            'assumptions' => Premissas::forOffice($office)->toArray(),
            'assumption_definitions' => Premissas::definitions(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        Gate::authorize('admin');
        $office = $request->user()->office;

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'primary_color' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'assumptions' => ['sometimes', 'array'],
        ]);

        if (array_key_exists('assumptions', $data)) {
            $data['assumptions'] = Premissas::validateOverrides($data['assumptions']);
        }

        $office->update($data);

        return $this->show($request);
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        Gate::authorize('admin');
        $request->validate(['logo' => ['required', 'image', 'mimes:png,jpg,jpeg', 'max:2048']]);

        $office = $request->user()->office;
        if ($office->logo_path) {
            Storage::disk('public')->delete($office->logo_path);
        }
        $office->update(['logo_path' => $request->file('logo')->store("offices/{$office->id}", 'public')]);

        return response()->json(['office' => $office->fresh()]);
    }
}

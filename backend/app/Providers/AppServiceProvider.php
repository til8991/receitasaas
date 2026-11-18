<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Billing\BillingGateway;
use App\Services\Billing\NullBillingGateway;
use App\Services\Calculadora\RegimeGeralClient;
use App\Services\Calculadora\SimplesNacionalClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RegimeGeralClient::class, fn () => new RegimeGeralClient(config('calculadoras.regime_geral')));
        $this->app->singleton(SimplesNacionalClient::class, fn () => new SimplesNacionalClient(config('calculadoras.simples_nacional')));
        $this->app->singleton(BillingGateway::class, fn () => match (config('plans.gateway')) {
            default => new NullBillingGateway,
        });
    }

    public function boot(): void
    {
        Gate::define('admin', fn (User $user) => $user->isAdmin());

        // Per IP and per target account, so rotating IPs does not allow brute force on one e-mail.
        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(10)->by('ip|'.$request->ip()),
            Limit::perMinute(5)->by('email|'.strtolower((string) $request->input('email'))),
            Limit::perHour(30)->by('email-h|'.strtolower((string) $request->input('email'))),
        ]);
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(240)->by($request->user()?->id ?: $request->ip()));
    }
}

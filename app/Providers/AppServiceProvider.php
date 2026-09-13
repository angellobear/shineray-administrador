<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }

    /**
     * Limitadores nombrados de la API pública. Login/registro/reset se limitan
     * por email + IP (no solo IP: varios clientes comparten NAT en Ecuador).
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('customer-auth', fn (Request $request): Limit => Limit::perMinute(5)->by(
            Str::transliterate(Str::lower((string) $request->input('email')).'|'.$request->ip()),
        ));

        RateLimiter::for('store-lookup', fn (Request $request): Limit => Limit::perMinute(30)->by($request->ip()));

        RateLimiter::for('webhooks', fn (Request $request): Limit => Limit::perMinute(120)->by($request->ip()));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}

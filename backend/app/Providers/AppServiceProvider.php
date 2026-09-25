<?php

namespace App\Providers;

use App\Services\Analysis\CvAnalyzer;
use App\Services\Analysis\LlmCvAnalyzer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CvAnalyzer::class, LlmCvAnalyzer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        RateLimiter::for('llm', function (): Limit {
            return Limit::perMinute((int) config('llm.rate_limit_per_minute', 20));
        });
    }
}

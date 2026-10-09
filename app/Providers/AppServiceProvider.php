<?php

namespace App\Providers;

use App\Halden\Game\DecisionBook;
use App\Halden\Game\QuarterRunner;
use App\Halden\OperatingModel\ModelData;
use App\Halden\OperatingModel\OperatingModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ModelData::class, fn (): ModelData => new ModelData);
        $this->app->singleton(OperatingModel::class, fn ($app): OperatingModel => new OperatingModel($app->make(ModelData::class)));
        $this->app->singleton(DecisionBook::class, fn ($app): DecisionBook => new DecisionBook($app->make(ModelData::class)));
        $this->app->singleton(QuarterRunner::class, fn ($app): QuarterRunner => new QuarterRunner($app->make(OperatingModel::class), $app->make(DecisionBook::class)));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
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

<?php

namespace App\Providers;

use App\Halden\Ai\AdvisorRoom;
use App\Halden\Ai\AnthropicClient;
use App\Halden\Ai\FacultyDrafts;
use App\Halden\Ai\Findings;
use App\Halden\Ai\LlmClient;
use App\Halden\Ai\StubClient;
use App\Halden\Content\ContentPack;
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

        // With no API key the advisors use a stand-in with fixed replies, and on the live site they stay switched off.
        $this->app->singleton(LlmClient::class, function (): LlmClient {
            $key = (string) config('halden.ai.anthropic_key');

            return $key === '' || $this->app->runningUnitTests() ? new StubClient : new AnthropicClient($key, (string) config('halden.ai.model'));
        });
        $this->app->singleton(AdvisorRoom::class, fn ($app): AdvisorRoom => new AdvisorRoom(
            $app->make(LlmClient::class),
            $app->make(ContentPack::class),
            $app->make(ModelData::class),
            enabled: (string) config('halden.ai.anthropic_key') !== '' || ! $app->isProduction(),
            maxTokens: (int) config('halden.ai.max_tokens'),
        ));
        $this->app->singleton(FacultyDrafts::class, fn ($app): FacultyDrafts => new FacultyDrafts(
            $app->make(LlmClient::class),
            new Findings($app->make(OperatingModel::class), $app->make(ModelData::class)),
            $app->make(AdvisorRoom::class),
            enabled: (string) config('halden.ai.anthropic_key') !== '' || ! $app->isProduction(),
        ));
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

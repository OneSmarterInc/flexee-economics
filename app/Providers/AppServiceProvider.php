<?php

namespace App\Providers;

use App\Halden\Ai\AdvisorRoom;
use App\Halden\Ai\AnthropicClient;
use App\Halden\Ai\Carrying;
use App\Halden\Ai\FacultyDrafts;
use App\Halden\Ai\Findings;
use App\Halden\Ai\HelpDesk;
use App\Halden\Ai\LlmClient;
use App\Halden\Ai\StubClient;
use App\Halden\Content\ContentPack;
use App\Halden\Game\BoardRecord;
use App\Halden\Game\DecisionBook;
use App\Halden\Game\QuarterRunner;
use App\Halden\OperatingModel\ModelData;
use App\Halden\OperatingModel\OperatingModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
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
            runner: $app->make(QuarterRunner::class),
        ));
        $aiOn = fn ($app): bool => (string) config('halden.ai.anthropic_key') !== '' || ! $app->isProduction();
        $this->app->singleton(Carrying::class, fn ($app): Carrying => new Carrying(
            $app->make(LlmClient::class),
            new Findings($app->make(OperatingModel::class), $app->make(ModelData::class), $app->make(DecisionBook::class)),
            enabled: $aiOn($app),
        ));
        $this->app->singleton(HelpDesk::class, fn ($app): HelpDesk => new HelpDesk($app->make(LlmClient::class), enabled: $aiOn($app)));
        $this->app->singleton(FacultyDrafts::class, fn ($app): FacultyDrafts => new FacultyDrafts(
            $app->make(LlmClient::class),
            new Findings($app->make(OperatingModel::class), $app->make(ModelData::class), $app->make(DecisionBook::class)),
            $app->make(AdvisorRoom::class),
            $app->make(BoardRecord::class),
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

        // Behind the host's proxy the app may not see HTTPS itself; links must still use it.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

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

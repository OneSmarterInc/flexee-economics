<?php

namespace Tests\Feature\Seeders;

use App\Models\CohortResponseFunction;
use App\Models\DecisionFormDefinition;
use App\Models\KpiDefinition;
use App\Models\MemoDefinition;
use App\Models\SectionSimulation;
use App\Models\SimulationContentActivation;
use App\Models\SimulationVariant;
use App\Models\SimulationWeek;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Database\Seeders\DemoAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class ProductionSafeSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_account_seeder_refuses_default_password_in_production(): void
    {
        $this->seed(ContentSeeder::class);
        $this->clearDemoPassword();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Refusing to seed demo accounts in production without SEED_DEMO_PASSWORD (16+ characters).');

        $this->withApplicationEnvironment('production', function (): void {
            $this->assertTrue(app()->isProduction());

            app(DemoAccountSeeder::class)->run();
        });
    }

    public function test_demo_account_seeder_uses_supplied_strong_password_in_production(): void
    {
        $strongPassword = 'SyntheticStrongSeed!2026';

        $this->seed(ContentSeeder::class);
        $this->setDemoPassword($strongPassword);

        $this->withApplicationEnvironment('production', function (): void {
            $this->assertTrue(app()->isProduction());

            app(DemoAccountSeeder::class)->run();
        });

        $admin = User::query()->where('email', 'admin@example.test')->firstOrFail();

        $this->assertFalse(Hash::check('password', $admin->password));
        $this->assertTrue(Hash::check($strongPassword, $admin->password));
    }

    public function test_local_seed_preserves_default_demo_password_and_runtime_data(): void
    {
        $this->clearDemoPassword();

        $this->withApplicationEnvironment('testing', function (): void {
            $this->assertFalse(app()->isProduction());

            $this->seed();
        });

        $admin = User::query()->where('email', 'admin@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('password', $admin->password));

        $this->assertNotNull(SectionSimulation::query()->where('name', 'Seven-Week Pilot Halden Energy')->first());
        $this->assertSame(2, User::query()->where('email', 'like', 'pilot-%1@example.test')->count());
    }

    public function test_content_seeder_preserves_simulation_configuration_without_demo_accounts(): void
    {
        $this->seed(ContentSeeder::class);

        $this->assertSame(0, User::query()->count());
        $this->assertNotNull(SimulationVariant::query()->where('slug', 'fourteen-week')->first());
        $this->assertNotNull(SimulationVariant::query()->where('slug', 'seven-week')->first());
        $this->assertSame(21, SimulationWeek::query()->count());
        $this->assertGreaterThanOrEqual(13, SimulationContentActivation::query()->count());
        $this->assertGreaterThanOrEqual(7, KpiDefinition::query()->count());
        $this->assertGreaterThanOrEqual(3, CohortResponseFunction::query()->count());
        $this->assertGreaterThanOrEqual(17, DecisionFormDefinition::query()->count());
        $this->assertGreaterThanOrEqual(17, MemoDefinition::query()->count());
    }

    /**
     * @param  callable(): void  $callback
     */
    private function withApplicationEnvironment(string $environment, callable $callback): void
    {
        $original = app()->environment();
        app()->detectEnvironment(fn (): string => $environment);

        try {
            $callback();
        } finally {
            app()->detectEnvironment(fn (): string => $original);
            $this->clearDemoPassword();
        }
    }

    private function setDemoPassword(string $password): void
    {
        putenv('SEED_DEMO_PASSWORD='.$password);
        $_ENV['SEED_DEMO_PASSWORD'] = $password;
        $_SERVER['SEED_DEMO_PASSWORD'] = $password;
    }

    private function clearDemoPassword(): void
    {
        putenv('SEED_DEMO_PASSWORD');
        unset($_ENV['SEED_DEMO_PASSWORD'], $_SERVER['SEED_DEMO_PASSWORD']);
    }
}

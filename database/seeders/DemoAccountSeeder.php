<?php

namespace Database\Seeders;

use App\Domain\Simulation\SimulationLifecycleService;
use App\Enums\PlatformRole;
use App\Enums\SectionSimulationWeekStatus;
use App\Enums\StandingValue;
use App\Models\Counterparty;
use App\Models\Enrollment;
use App\Models\Seat;
use App\Models\Section;
use App\Models\SectionFaculty;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationVersion;
use App\Models\StandingState;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TeamSimulation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DemoAccountSeeder extends Seeder
{
    public function run(): void
    {
        $plain = (string) Env::get('SEED_DEMO_PASSWORD', 'password');

        if (app()->isProduction() && ($plain === 'password' || strlen($plain) < 16)) {
            throw new RuntimeException(
                'Refusing to seed demo accounts in production without SEED_DEMO_PASSWORD (16+ characters).'
            );
        }

        $password = Hash::make($plain);

        $tenant = Tenant::query()->where('slug', 'halden-university-demo')->firstOrFail();
        $sectionA = $this->section($tenant, 'Section A');
        $sectionB = $this->section($tenant, 'Section B');
        $sevenWeekPilotSection = $this->section($tenant, 'Seven-Week Pilot');
        $seats = Seat::query()->orderBy('sort_order')->get();

        User::query()->firstOrCreate([
            'tenant_id' => $tenant->id,
            'email' => 'admin@example.test',
        ], [
            'name' => 'Demo Administrator',
            'password' => $password,
            'global_role' => PlatformRole::Administrator,
            'email_verified_at' => now(),
        ]);

        $faculty = User::query()->firstOrCreate([
            'tenant_id' => $tenant->id,
            'email' => 'faculty@example.test',
        ], [
            'name' => 'Demo Faculty',
            'password' => $password,
            'global_role' => PlatformRole::Faculty,
            'email_verified_at' => now(),
        ]);

        foreach ([$sectionA, $sectionB, $sevenWeekPilotSection] as $section) {
            SectionFaculty::query()->firstOrCreate([
                'tenant_id' => $tenant->id,
                'section_id' => $section->id,
                'user_id' => $faculty->id,
            ], [
                'role' => 'instructor',
            ]);
        }

        foreach ([[$sectionA, 1], [$sectionB, 2]] as [$section, $sectionIndex]) {
            foreach (range(1, 5) as $studentIndex) {
                $student = User::query()->firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'email' => 'student'.$sectionIndex.$studentIndex.'@example.test',
                ], [
                    'name' => 'Demo Student '.$sectionIndex.$studentIndex,
                    'password' => $password,
                    'global_role' => PlatformRole::Student,
                    'email_verified_at' => now(),
                ]);

                Enrollment::query()->firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'section_id' => $section->id,
                    'user_id' => $student->id,
                ], [
                    'status' => 'active',
                ]);
            }
        }

        $this->ensureSevenWeekPilotTeams($tenant, $sevenWeekPilotSection, $seats, $password);

        foreach ([[$sectionA, 'Alpha'], [$sectionB, 'Bravo']] as [$section, $teamName]) {
            $team = Team::query()->firstOrCreate([
                'tenant_id' => $tenant->id,
                'section_id' => $section->id,
                'slug' => strtolower($teamName),
            ], [
                'name' => 'Team '.$teamName,
            ]);

            $students = $section->enrollments()->with('user')->limit(5)->get()->pluck('user');

            foreach ($students->values() as $index => $student) {
                TeamMember::query()->firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'team_id' => $team->id,
                    'user_id' => $student->id,
                ], [
                    'seat_id' => $seats->values()->get($index)?->id,
                ]);
            }
        }

        $version = $this->simulationVersion('2026-demo');
        $sevenWeekVersion = $this->simulationVersion('2026-seven-week-pilot');

        $sectionSimulation = SectionSimulation::query()
            ->where('tenant_id', $tenant->id)
            ->where('section_id', $sectionA->id)
            ->where('simulation_version_id', $version->id)
            ->first();

        if (! $sectionSimulation) {
            $sectionSimulation = app(SimulationLifecycleService::class)
                ->assignToSection($sectionA, $version, $faculty, 'Section A Demo Halden Energy');
        }

        $sevenWeekSectionSimulation = SectionSimulation::query()
            ->where('tenant_id', $tenant->id)
            ->where('section_id', $sevenWeekPilotSection->id)
            ->where('simulation_version_id', $sevenWeekVersion->id)
            ->first();

        if (! $sevenWeekSectionSimulation) {
            $sevenWeekSectionSimulation = app(SimulationLifecycleService::class)
                ->assignToSection($sevenWeekPilotSection, $sevenWeekVersion, $faculty, 'Seven-Week Pilot Halden Energy');
        }

        $this->ensureSevenWeekPilotStanding($sevenWeekSectionSimulation);
        $this->openInitialSevenWeekPilotWeek($sevenWeekSectionSimulation, $faculty);
        $this->openFourteenWeekDemoWeeks($sectionSimulation, $faculty);
    }

    private function section(Tenant $tenant, string $name): Section
    {
        return Section::query()
            ->where('tenant_id', $tenant->id)
            ->where('name', $name)
            ->firstOrFail();
    }

    private function simulationVersion(string $version): SimulationVersion
    {
        return SimulationVersion::query()->where('version', $version)->firstOrFail();
    }

    /**
     * @param  Collection<int, Seat>  $seats
     */
    private function ensureSevenWeekPilotTeams(Tenant $tenant, Section $section, Collection $seats, string $password): void
    {
        foreach ([
            'Alpha' => 'pilot-alpha',
            'Beta' => 'pilot-beta',
        ] as $teamName => $emailPrefix) {
            $team = Team::query()->firstOrCreate([
                'tenant_id' => $tenant->id,
                'section_id' => $section->id,
                'slug' => strtolower($teamName),
            ], [
                'name' => 'Team '.$teamName,
            ]);

            foreach (range(1, 5) as $studentIndex) {
                $student = User::query()->firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'email' => $emailPrefix.$studentIndex.'@example.test',
                ], [
                    'name' => 'Pilot '.ucfirst($emailPrefix).' Student '.$studentIndex,
                    'password' => $password,
                    'global_role' => PlatformRole::Student,
                    'email_verified_at' => now(),
                ]);

                Enrollment::query()->firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'section_id' => $section->id,
                    'user_id' => $student->id,
                ], [
                    'status' => 'active',
                ]);

                TeamMember::query()->firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'team_id' => $team->id,
                    'user_id' => $student->id,
                ], [
                    'seat_id' => $seats->values()->get($studentIndex - 1)?->id,
                ]);
            }
        }
    }

    private function ensureSevenWeekPilotStanding(SectionSimulation $sectionSimulation): void
    {
        $counterparty = Counterparty::query()->firstOrCreate(
            ['key' => 'straits_pacific'],
            [
                'name' => 'Straits Pacific',
                'description' => 'Singapore JV partner',
                'sort_order' => 50,
                'is_active' => true,
                'metadata' => [],
            ],
        );

        $sectionSimulation->teamSimulations()
            ->with('team')
            ->get()
            ->each(function (TeamSimulation $teamSimulation) use ($counterparty): void {
                StandingState::query()->updateOrCreate(
                    [
                        'tenant_id' => $teamSimulation->tenant_id,
                        'section_simulation_id' => $teamSimulation->section_simulation_id,
                        'team_simulation_id' => $teamSimulation->id,
                        'counterparty_id' => $counterparty->id,
                    ],
                    [
                        'team_id' => $teamSimulation->team_id,
                        'state' => StandingValue::Cooperative->value,
                        'reason' => 'Seven-week pilot standing baseline; Week 10 flex binding is derived from current standing state.',
                        'state_changed_at' => now(),
                    ],
                );
            });
    }

    private function openInitialSevenWeekPilotWeek(SectionSimulation $sectionSimulation, User $faculty): void
    {
        $weekOne = $sectionSimulation->weeks()
            ->whereHas('definition', fn ($query) => $query->where('week_number', 1))
            ->first();

        if ($weekOne && $weekOne->statusEnum() === SectionSimulationWeekStatus::Draft) {
            app(SimulationLifecycleService::class)
                ->transitionWeek($weekOne, SectionSimulationWeekStatus::Released, $faculty);
        }

        if ($weekOne && $weekOne->refresh()->statusEnum() === SectionSimulationWeekStatus::Released) {
            app(SimulationLifecycleService::class)
                ->transitionWeek($weekOne, SectionSimulationWeekStatus::Open, $faculty, now()->addWeek());
        }
    }

    private function openFourteenWeekDemoWeeks(SectionSimulation $sectionSimulation, User $faculty): void
    {
        $weekOne = $sectionSimulation->weeks()
            ->whereHas('definition', fn ($query) => $query->where('week_number', 1))
            ->first();

        if ($weekOne && $weekOne->statusEnum() === SectionSimulationWeekStatus::Draft) {
            app(SimulationLifecycleService::class)
                ->transitionWeek($weekOne, SectionSimulationWeekStatus::Released, $faculty);
        }

        if ($weekOne && $weekOne->refresh()->statusEnum() === SectionSimulationWeekStatus::Released) {
            app(SimulationLifecycleService::class)
                ->transitionWeek($weekOne, SectionSimulationWeekStatus::Open, $faculty, now()->addWeek());
        }

        $weekFour = $sectionSimulation->weeks()
            ->whereHas('definition', fn ($query) => $query->where('week_number', 4))
            ->first();

        if ($weekFour && $weekFour->statusEnum() === SectionSimulationWeekStatus::Draft) {
            app(SimulationLifecycleService::class)
                ->transitionWeek($weekFour, SectionSimulationWeekStatus::Released, $faculty);
        }

        if ($weekFour && $weekFour->refresh()->statusEnum() === SectionSimulationWeekStatus::Released) {
            app(SimulationLifecycleService::class)
                ->transitionWeek($weekFour, SectionSimulationWeekStatus::Open, $faculty, now()->addWeeks(4));
        }

        $sectionSimulation->weeks()
            ->with('definition')
            ->get()
            ->each(function (SectionSimulationWeek $runtimeWeek) use ($faculty): void {
                if ($runtimeWeek->definition->week_number === 14) {
                    return;
                }

                if ($runtimeWeek->statusEnum() === SectionSimulationWeekStatus::Draft) {
                    $runtimeWeek = app(SimulationLifecycleService::class)
                        ->transitionWeek($runtimeWeek, SectionSimulationWeekStatus::Released, $faculty);
                }

                if ($runtimeWeek->refresh()->statusEnum() === SectionSimulationWeekStatus::Released) {
                    app(SimulationLifecycleService::class)
                        ->transitionWeek($runtimeWeek, SectionSimulationWeekStatus::Open, $faculty, now()->addWeeks($runtimeWeek->definition->week_number));
                }

                if ($runtimeWeek->refresh()->statusEnum() !== SectionSimulationWeekStatus::Open) {
                    $runtimeWeek->forceFill([
                        'status' => SectionSimulationWeekStatus::Open->value,
                        'opened_at' => $runtimeWeek->opened_at ?? now(),
                        'closes_at' => now()->addWeeks($runtimeWeek->definition->week_number),
                    ])->save();
                }
            });
    }
}

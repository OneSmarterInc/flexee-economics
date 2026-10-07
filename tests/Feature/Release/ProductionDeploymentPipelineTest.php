<?php

namespace Tests\Feature\Release;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductionDeploymentPipelineTest extends TestCase
{
    public function test_deploy_workflow_depends_on_successful_tests_on_main(): void
    {
        $testsWorkflow = $this->readFile('.github/workflows/tests.yml');
        $deployWorkflow = $this->readFile('.github/workflows/deploy.yml');

        $this->assertStringContainsString('name: tests', $testsWorkflow);
        $this->assertStringContainsString('MySQL 8.4 CI', $testsWorkflow);
        $this->assertStringContainsString('branches:', $testsWorkflow);
        $this->assertStringContainsString('- main', $testsWorkflow);

        $this->assertStringContainsString('workflow_run:', $deployWorkflow);
        $this->assertStringContainsString('- tests', $deployWorkflow);
        $this->assertStringContainsString('- main', $deployWorkflow);
        $this->assertStringContainsString("github.event.workflow_run.conclusion == 'success'", $deployWorkflow);
        $this->assertStringNotContainsString('- master', $deployWorkflow);
        $this->assertStringNotContainsString('on:'."\n".'  push:', $deployWorkflow);
    }

    public function test_tests_workflow_has_mysql_release_gate(): void
    {
        $testsWorkflow = $this->readFile('.github/workflows/tests.yml');

        $this->assertStringContainsString('DB_CONNECTION: sqlite', $testsWorkflow);
        $this->assertStringContainsString('DB_DATABASE: ${{ github.workspace }}/database/database.sqlite', $testsWorkflow);
        $this->assertStringContainsString('touch database/database.sqlite', $testsWorkflow);
        $this->assertStringContainsString('image: mysql:8.4', $testsWorkflow);
        $this->assertStringContainsString('DB_CONNECTION: mysql', $testsWorkflow);
        $this->assertStringContainsString('DB_COLLATION: utf8mb4_0900_ai_ci', $testsWorkflow);
        $this->assertStringContainsString('php artisan migrate:fresh --seed --no-interaction', $testsWorkflow);
        $this->assertStringContainsString('php artisan test --no-interaction', $testsWorkflow);
    }

    public function test_deploy_workflow_builds_frontend_and_installs_production_dependencies(): void
    {
        $deployWorkflow = $this->readFile('.github/workflows/deploy.yml');

        $this->assertStringContainsString('composer install \\', $deployWorkflow);
        $this->assertStringContainsString('--no-dev \\', $deployWorkflow);
        $this->assertStringContainsString('--optimize-autoloader', $deployWorkflow);
        $this->assertStringContainsString('npm ci', $deployWorkflow);
        $this->assertStringContainsString('npm run build', $deployWorkflow);
        $this->assertStringContainsString('git checkout --force "${RELEASE_SHA}"', $deployWorkflow);
    }

    public function test_deploy_workflow_requires_backup_before_migrations(): void
    {
        $deployWorkflow = $this->readFile('.github/workflows/deploy.yml');

        $this->assertStringContainsString('php artisan down', $deployWorkflow);
        $this->assertStringContainsString('PRODUCTION_BACKUP_COMMAND', $deployWorkflow);
        $this->assertStringContainsString('PRODUCTION_BACKUP_VERIFY_COMMAND', $deployWorkflow);
        $this->assertStringContainsString('TAKE VERIFIED DATABASE BACKUP', $deployWorkflow);
        $this->assertStringContainsString('bash -lc "${PRODUCTION_BACKUP_COMMAND}"', $deployWorkflow);
        $this->assertStringContainsString('bash -lc "${PRODUCTION_BACKUP_VERIFY_COMMAND}"', $deployWorkflow);

        $this->assertLessThan(
            strpos($deployWorkflow, 'php artisan migrate --force'),
            strpos($deployWorkflow, 'bash -lc "${PRODUCTION_BACKUP_VERIFY_COMMAND}"'),
            'Backup verification must appear before production migrations.',
        );

        $this->assertStringContainsString('php artisan up', $deployWorkflow);
    }

    public function test_release_runbooks_document_single_branch_and_no_fake_backup_provider(): void
    {
        $deploymentRunbook = $this->readFile('docs/PRODUCTION_DEPLOYMENT_RUNBOOK.md');
        $releaseProcess = $this->readFile('docs/RELEASE_PROCESS.md');
        $backupRunbook = $this->readFile('docs/BACKUP_RESTORE_RUNBOOK.md');

        $this->assertStringContainsString('main', $deploymentRunbook);
        $this->assertStringContainsString('tests workflow', $deploymentRunbook);
        $this->assertStringContainsString('production environment approval', $deploymentRunbook);
        $this->assertStringContainsString('Do not deploy from `master`.', $deploymentRunbook);

        $this->assertStringContainsString('single-release-line process', $releaseProcess);
        $this->assertStringContainsString('`master` is not an independent production line', $releaseProcess);
        $this->assertStringContainsString('No staging deployment workflow is present', $releaseProcess);

        $this->assertStringContainsString('Currently Implemented', $backupRunbook);
        $this->assertStringContainsString('Operator Procedure', $backupRunbook);
        $this->assertStringContainsString('Recommended Future Automation', $backupRunbook);
        $this->assertStringContainsString('does not define a database provider-specific backup tool', $backupRunbook);
    }

    public function test_release_files_do_not_contain_plaintext_secret_values(): void
    {
        foreach ([
            '.github/workflows/deploy.yml',
            'docs/PRODUCTION_DEPLOYMENT_RUNBOOK.md',
            'docs/RELEASE_PROCESS.md',
            'docs/BACKUP_RESTORE_RUNBOOK.md',
        ] as $path) {
            $content = $this->readFile($path);

            $this->assertStringNotContainsString('BEGIN OPENSSH PRIVATE KEY', $content);
            $this->assertStringNotContainsString('DB_PASSWORD=', $content);
            $this->assertStringNotContainsString('password:', strtolower($content));
            $this->assertStringNotContainsString('mysql://', strtolower($content));
            $this->assertStringNotContainsString('postgres://', strtolower($content));
        }
    }

    public function test_migration_identifiers_are_mysql_safe(): void
    {
        $longIdentifiers = [];

        foreach (glob(base_path('database/migrations/*.php')) ?: [] as $migration) {
            foreach ($this->migrationIdentifiers($migration) as $identifier) {
                if (mb_strlen($identifier['name']) > 64) {
                    $longIdentifiers[] = [
                        'migration' => basename($migration),
                        'identifier' => $identifier['name'],
                        'length' => mb_strlen($identifier['name']),
                        'type' => $identifier['type'],
                    ];
                }
            }
        }

        $this->assertSame([], $longIdentifiers);
    }

    public function test_mysql_schema_identifiers_are_mysql_safe_after_migration(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL information_schema identifier audit requires a MySQL connection.');
        }

        /** @var list<object{kind: string, table_name: string, identifier: string, length: int}> $longIdentifiers */
        $longIdentifiers = DB::select(<<<'SQL'
            SELECT 'index' AS kind, table_name, index_name AS identifier, CHAR_LENGTH(index_name) AS length
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND CHAR_LENGTH(index_name) > 64
            UNION ALL
            SELECT 'constraint' AS kind, table_name, constraint_name AS identifier, CHAR_LENGTH(constraint_name) AS length
            FROM information_schema.table_constraints
            WHERE table_schema = DATABASE()
              AND CHAR_LENGTH(constraint_name) > 64
            ORDER BY table_name, identifier
        SQL);

        $this->assertSame([], array_map(
            fn (object $identifier): array => [
                'kind' => $identifier->kind,
                'table' => $identifier->table_name,
                'identifier' => $identifier->identifier,
                'length' => $identifier->length,
            ],
            $longIdentifiers,
        ));
    }

    private function readFile(string $path): string
    {
        $fullPath = base_path($path);

        $this->assertFileExists($fullPath);

        return (string) file_get_contents($fullPath);
    }

    /**
     * @return array<int, array{name: string, type: string}>
     */
    private function migrationIdentifiers(string $migration): array
    {
        $identifiers = [];
        $table = null;

        foreach (file($migration) ?: [] as $line) {
            if (preg_match("/Schema::create\\('([^']+)'/", $line, $matches) === 1) {
                $table = $matches[1];
            }

            if ($table === null) {
                continue;
            }

            if (preg_match("/->foreignId\\('([^']+)'\\).*->constrained\\(/", $line, $matches) === 1) {
                $identifiers[] = [
                    'name' => "{$table}_{$matches[1]}_foreign",
                    'type' => 'foreign',
                ];
            }

            if (preg_match("/->\\w+\\('([^']+)'\\).*->unique\\((?:\\s*'([^']+)')?/", $line, $matches) === 1) {
                $identifiers[] = [
                    'name' => $matches[2] ?? "{$table}_{$matches[1]}_unique",
                    'type' => 'unique',
                ];
            }

            if (preg_match("/->foreign\\('([^']+)'(?:,\\s*'([^']+)')?/", $line, $matches) === 1) {
                $identifiers[] = [
                    'name' => $matches[2] ?? "{$table}_{$matches[1]}_foreign",
                    'type' => 'foreign',
                ];
            }

            foreach ([
                'foreign' => 'foreign',
                'unique' => 'unique',
                'index' => 'index',
            ] as $method => $type) {
                if (preg_match("/->{$method}\\(\\[([^\\]]+)\\](?:,\\s*'([^']+)')?/", $line, $matches) !== 1) {
                    continue;
                }

                $identifiers[] = [
                    'name' => $matches[2] ?? "{$table}_{$this->columnIdentifier($matches[1])}_{$type}",
                    'type' => $type,
                ];
            }
        }

        return $identifiers;
    }

    private function columnIdentifier(string $columns): string
    {
        preg_match_all("/'([^']+)'/", $columns, $matches);

        return implode('_', $matches[1]);
    }
}

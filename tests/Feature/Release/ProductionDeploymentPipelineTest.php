<?php

namespace Tests\Feature\Release;

use Tests\TestCase;

class ProductionDeploymentPipelineTest extends TestCase
{
    public function test_deploy_workflow_depends_on_successful_tests_on_main(): void
    {
        $testsWorkflow = $this->readFile('.github/workflows/tests.yml');
        $deployWorkflow = $this->readFile('.github/workflows/deploy.yml');

        $this->assertStringContainsString('name: tests', $testsWorkflow);
        $this->assertStringContainsString('branches:', $testsWorkflow);
        $this->assertStringContainsString('- main', $testsWorkflow);

        $this->assertStringContainsString('workflow_run:', $deployWorkflow);
        $this->assertStringContainsString('- tests', $deployWorkflow);
        $this->assertStringContainsString('- main', $deployWorkflow);
        $this->assertStringContainsString("github.event.workflow_run.conclusion == 'success'", $deployWorkflow);
        $this->assertStringNotContainsString('- master', $deployWorkflow);
        $this->assertStringNotContainsString('on:'."\n".'  push:', $deployWorkflow);
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

    private function readFile(string $path): string
    {
        $fullPath = base_path($path);

        $this->assertFileExists($fullPath);

        return (string) file_get_contents($fullPath);
    }
}

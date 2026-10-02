<?php

namespace Tests\Feature\Consequences;

use App\Domain\Consequences\Week4ConsequenceDefinitionCatalog;
use App\Models\ConsequenceDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Week11Week12ConsequenceMappingReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_documents_week11_week12_as_reconciliation_only(): void
    {
        $inventory = (string) file_get_contents(base_path('docs/WEEK11_WEEK12_CONSEQUENCE_INVENTORY.md'));
        $implementation = (string) file_get_contents(base_path('docs/BATCH27A_IMPLEMENTATION.md'));

        $this->assertStringContainsString('No new consequence definitions', $inventory);
        $this->assertStringContainsString('No Week 11 or Week 12 consequence rule is confirmed', $implementation);
        $this->assertStringContainsString('Documentation/reconciliation only', $implementation);
    }

    public function test_week11_authoritative_package_does_not_define_consequence_targets(): void
    {
        $manifest = (string) file_get_contents(base_path('halden-week11-data-package/MANIFEST.md'));
        $validation = (string) file_get_contents(base_path('halden-week11-data-package/VALIDATION_16A.md'));
        $golden = (string) file_get_contents(base_path('halden-week11-data-package/fixtures/week11_golden.json'));

        $sourceText = $manifest."\n".$validation."\n".$golden;

        $this->assertStringContainsString('Kessana', $sourceText);
        $this->assertStringContainsString('indifference_take', $sourceText);
        $this->assertStringNotContainsString('ConsequenceLink', $sourceText);
        $this->assertStringNotContainsString('standing transition', strtolower($sourceText));
        $this->assertStringNotContainsString('target week', strtolower($sourceText));
    }

    public function test_week12_authoritative_package_does_not_define_consequence_targets(): void
    {
        $manifest = (string) file_get_contents(base_path('halden-week12-data-package/MANIFEST.md'));
        $validation = (string) file_get_contents(base_path('halden-week12-data-package/VALIDATION_16A.md'));
        $golden = (string) file_get_contents(base_path('halden-week12-data-package/fixtures/week12_golden.json'));

        $sourceText = $manifest."\n".$validation."\n".$golden;

        $this->assertStringContainsString('Helix Rotterdam', $sourceText);
        $this->assertStringContainsString('portfolios_unlocked_by_divest', $sourceText);
        $this->assertStringNotContainsString('ConsequenceLink', $sourceText);
        $this->assertStringNotContainsString('standing transition', strtolower($sourceText));
        $this->assertStringNotContainsString('target week', strtolower($sourceText));
    }

    public function test_existing_consequence_catalog_does_not_register_week11_or_week12_definitions(): void
    {
        app(Week4ConsequenceDefinitionCatalog::class)->ensureDefinitions();

        $this->assertSame(2, ConsequenceDefinition::query()->count());
        $this->assertFalse(ConsequenceDefinition::query()->where('key', 'like', 'week11_%')->exists());
        $this->assertFalse(ConsequenceDefinition::query()->where('key', 'like', 'week12_%')->exists());
    }
}

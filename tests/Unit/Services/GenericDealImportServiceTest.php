<?php

namespace Tests\Unit\Services;

use App\Services\GenericDealImportService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for GenericDealImportService.
 *
 * IMPORTANT: These tests assert that GenericDealImportService:
 * - Has NO LGU IDS pricing logic (no TIERS constant, no municipality/province fields)
 * - Normalises amounts, maps column aliases, and parses partner emails correctly
 * - Defaults deal_start_date to the import date when blank
 * - Loads industry templates from config
 *
 * Tests that require DB/models use stubs/mocks. Tests that only exercise
 * static helpers or config/class-level constants run without database calls.
 */
class GenericDealImportServiceTest extends TestCase
{
    // ── Amount normalisation ──────────────────────────────────────

    /** @test */
    public function testNormalizeAmountHandlesPesoSign(): void
    {
        // Construct a minimal service stub — normalisation is inline, not a static method.
        // We test via normalizeRow with a known mapping.
        $service = $this->makeService();

        $mapping = ['Deal Amount' => 'deal_amount'];
        $result  = $service->normalizeRow(['Deal Amount' => '₱500,000'], $mapping, '2026-05-06');

        $this->assertSame(500000.0, $result['deal_amount']);
    }

    /** @test */
    public function testNormalizeAmountHandlesCommas(): void
    {
        $service = $this->makeService();
        $mapping = ['Deal Amount' => 'deal_amount'];
        $result  = $service->normalizeRow(['Deal Amount' => '1,000,000'], $mapping, '2026-05-06');

        $this->assertSame(1000000.0, $result['deal_amount']);
    }

    /** @test */
    public function testNormalizeAmountHandlesPlainNumber(): void
    {
        $service = $this->makeService();
        $mapping = ['Deal Amount' => 'deal_amount'];
        $result  = $service->normalizeRow(['Deal Amount' => '500000'], $mapping, '2026-05-06');

        $this->assertSame(500000.0, $result['deal_amount']);
    }

    /** @test */
    public function testNormalizeAmountReturnsNullForGarbage(): void
    {
        $service = $this->makeService();
        $mapping = ['Deal Amount' => 'deal_amount'];
        $result  = $service->normalizeRow(['Deal Amount' => 'abc'], $mapping, '2026-05-06');

        $this->assertNull($result['deal_amount']);
    }

    // ── Column alias detection ────────────────────────────────────

    /** @test */
    public function testDetectColumnsMapsDealNameAlias(): void
    {
        $service = $this->makeService();
        $aliases = $this->loadTemplates()['default']['aliases'];
        $mapping = $service->detectColumns(['Deal'], $aliases);

        $this->assertSame('deal_name', $mapping['Deal']);
    }

    /** @test */
    public function testDetectColumnsMapsResellerEmailToReferrerEmail(): void
    {
        $service = $this->makeService();
        $aliases = $this->loadTemplates()['default']['aliases'];
        $mapping = $service->detectColumns(['Reseller Email'], $aliases);

        $this->assertSame('referrer_email', $mapping['Reseller Email']);
    }

    /** @test */
    public function testDetectColumnsMapsOrganizationAlias(): void
    {
        $service = $this->makeService();
        $aliases = $this->loadTemplates()['default']['aliases'];
        $mapping = $service->detectColumns(['Company'], $aliases);

        $this->assertSame('organization_name', $mapping['Company']);
    }

    // ── Missing required fields ───────────────────────────────────

    /** @test */
    public function testMissingRequiredFieldsAreDetected(): void
    {
        $service = $this->makeService();

        // Mapping only covers deal_name, referrer_email, organization_name — missing deal_amount
        $mapping        = ['Deal Name' => 'deal_name', 'Referrer Email' => 'referrer_email', 'Company' => 'organization_name'];
        $requiredFields = ['deal_name', 'deal_amount', 'referrer_email', 'organization_name'];
        $missing        = $service->getMissingRequired($mapping, $requiredFields);

        $this->assertContains('deal_amount', $missing);
        $this->assertNotContains('deal_name', $missing);
    }

    // ── Row normalisation defaults ────────────────────────────────

    /** @test */
    public function testNormalizeRowDefaultsStartDateToImportDate(): void
    {
        $service    = $this->makeService();
        $importDate = '2026-05-06';
        $mapping    = ['Deal Name' => 'deal_name'];
        $result     = $service->normalizeRow(['Deal Name' => 'Test Deal'], $mapping, $importDate);

        $this->assertSame($importDate, $result['deal_start_date']);
    }

    /** @test */
    public function testNormalizeRowNormalizesStage(): void
    {
        $service = $this->makeService();
        $mapping = ['Deal Name' => 'deal_name', 'Stage' => 'deal_stage'];
        $result  = $service->normalizeRow(['Deal Name' => 'Test', 'Stage' => 'Introduction'], $mapping, '2026-05-06');

        // "Introduction" should normalise to 'introduction'
        $this->assertSame('introduction', $result['deal_stage']);
    }

    /** @test */
    public function testNormalizeRowParsesPartnerEmails(): void
    {
        $service = $this->makeService();
        $mapping = ['Deal Name' => 'deal_name', 'Partner Emails' => 'partner_emails'];
        $result  = $service->normalizeRow(
            ['Deal Name' => 'Test', 'Partner Emails' => 'a@b.com, c@d.com'],
            $mapping,
            '2026-05-06'
        );

        $this->assertIsArray($result['partner_emails']);
        $this->assertCount(2, $result['partner_emails']);
        $this->assertContains('a@b.com', $result['partner_emails']);
        $this->assertContains('c@d.com', $result['partner_emails']);
    }

    // ── Architecture / separation-of-concerns assertions ─────────

    /** @test */
    public function testNonLguIdsTenantDoesNotUsePricingTiers(): void
    {
        // GenericDealImportService must NOT define a TIERS constant.
        $this->assertFalse(
            defined(GenericDealImportService::class . '::TIERS'),
            'GenericDealImportService must not define a TIERS constant — LGU IDS pricing is locked to LguIdsPricingService.'
        );
    }

    /** @test */
    public function testNoMunicipalityOrProvinceFieldsInGenericService(): void
    {
        $aliases = $this->loadTemplates()['default']['aliases'];

        $this->assertArrayNotHasKey(
            'municipality',
            $aliases,
            'The default generic template must not have a municipality alias.'
        );
        $this->assertArrayNotHasKey(
            'province',
            $aliases,
            'The default generic template must not have a province alias.'
        );
    }

    /** @test */
    public function testIndustryTemplateLoadedFromConfig(): void
    {
        $templates = $this->loadTemplates();

        $this->assertArrayHasKey('saas', $templates, "The 'saas' template must exist in config.");
        $this->assertContains(
            'product_plan',
            $templates['saas']['optional_fields'] ?? [],
            "The 'saas' template must have 'product_plan' in optional_fields."
        );
    }

    /** @test */
    public function testGovernmentGenericTemplateHasLguIdsLockedFlag(): void
    {
        $templates = $this->loadTemplates();

        $this->assertArrayHasKey('government_generic', $templates, "The 'government_generic' template must exist in config.");
        $this->assertTrue(
            $templates['government_generic']['lgu_ids_locked'] ?? false,
            "The government_generic template must have lgu_ids_locked = true."
        );
    }

    // ── Helpers ──────────────────────────────────────────────────

    /**
     * Build a GenericDealImportService with a mocked NotificationDispatchService.
     * This avoids any container / database dependency.
     */
    private function makeService(): GenericDealImportService
    {
        $notifications = $this->createMock(\App\Services\NotificationDispatchService::class);
        return new GenericDealImportService($notifications);
    }

    /**
     * Load the import templates config array directly from the PHP config file
     * so these tests work as plain PHPUnit tests without Laravel bootstrap.
     */
    private function loadTemplates(): array
    {
        $projectRoot = dirname(__DIR__, 3); // tests/Unit/Services -> project root
        return require $projectRoot . '/config/referralbunny_import_templates.php';
    }
}

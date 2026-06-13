<?php

namespace App\Services\ReferralProgram;

use App\Models\TenantReferralProgramDraft;
use App\Support\ProtectedTenants;
use App\Support\ReferralProgramOptions;

/**
 * Dry-run validation for the Setup Wizard's "Simulate" step (Step 15). Checks
 * the draft's config against real workflow scenarios without writing any
 * data, and reports which scenarios pass, warn, or would block publish.
 */
class ReferralProgramSimulationService
{
    public function simulate(TenantReferralProgramDraft $draft, string $tenantId): array
    {
        $config    = $draft->config ?? [];
        $protected = ProtectedTenants::isProtected($tenantId);

        $scenarios = [
            $this->checkPipeline($config),
            $this->checkDealSubmission($config),
            $this->checkCommission($config, $protected),
            $this->checkPartnerSplit($config),
            $this->checkCustomFields($config),
            $this->checkDocuments($config),
            $this->checkPublicForm($config),
            $this->checkImport($config, $protected),
            $this->checkNotifications($config),
            $this->checkDashboard($config),
        ];

        $blockers = count(array_filter($scenarios, fn ($s) => $s['status'] === 'fail'));
        $warnings = count(array_filter($scenarios, fn ($s) => $s['status'] === 'warn'));

        return [
            'scenarios'   => $scenarios,
            'blockers'    => $blockers,
            'warnings'    => $warnings,
            'can_publish' => $blockers === 0,
        ];
    }

    private function checkPipeline(array $config): array
    {
        $label  = 'Pipeline & Journey';
        $stages = $config['pipeline']['stages'] ?? [];

        if (count($stages) < 2) {
            return $this->result($label, 'fail', 'Add at least two pipeline stages before publishing.');
        }

        $keys = array_map(fn ($s) => $s['stage_key'] ?? '', $stages);
        if (count($keys) !== count(array_unique($keys))) {
            return $this->result($label, 'fail', 'Pipeline stage keys must be unique.');
        }

        $wonStages = array_filter($stages, fn ($s) => !empty($s['is_won']));
        if (count($wonStages) === 0) {
            return $this->result($label, 'fail', 'At least one stage must be marked "Won" so closed deals can be tracked and commission can be calculated.');
        }

        return $this->result($label, 'pass', count($stages) . ' stages configured, including ' . count($wonStages) . ' marked "Won".');
    }

    private function checkDealSubmission(array $config): array
    {
        $label = 'Referrer submits a new deal';
        $roles = $config['participants']['roles'] ?? [];

        if (!in_array('referrers', $roles, true) && !in_array('public_submitters', $roles, true)) {
            return $this->result($label, 'warn', 'No Referrer or public-submitter role is enabled — nobody outside Tenant Admins can submit a deal yet.');
        }

        $note = !empty($config['approvals']['new_referral_review'])
            ? ' New referrals will wait for admin approval before entering the pipeline.'
            : ' New referrals enter the first pipeline stage immediately.';

        return $this->result($label, 'pass', 'A Referrer can submit a new deal.' . $note);
    }

    private function checkCommission(array $config, bool $protected): array
    {
        $label = 'Commission is calculated and split';

        if ($protected) {
            return $this->result($label, 'pass', 'This workspace uses a dedicated pricing service — commission settings from this wizard are not applied.');
        }

        $rewards = $config['rewards'] ?? [];
        if (empty($rewards)) {
            return $this->result($label, 'fail', 'Set up Rewards & Commission before publishing.');
        }

        $company  = (float) ($rewards['company_share_pct'] ?? 0);
        $referrer = (float) ($rewards['referrer_share_pct'] ?? 0);
        if (abs(($company + $referrer) - 100) > 0.01) {
            return $this->result($label, 'fail', 'Company share and Referrer share must add up to 100%.');
        }

        return $this->result($label, 'pass', "Company {$company}% / Referrer {$referrer}% of the commission pool on the standard Deal Value formula.");
    }

    private function checkPartnerSplit(array $config): array
    {
        $label = 'Partner Split';
        $roles = $config['participants']['roles'] ?? [];

        if (!in_array('partners', $roles, true)) {
            return $this->result($label, 'pass', 'Partners are not part of this program — partner split rules are not applied.');
        }

        $split = $config['partner_split'] ?? [];
        if (empty($split['allow_partners'])) {
            return $this->result($label, 'warn', '"Partners" is enabled as a participant role, but "Allow Partners" is off on the Partner Split step.');
        }

        $value = (float) ($split['default_split_value'] ?? 0);
        if (($split['split_type'] ?? null) === 'percentage' && $value > 100) {
            return $this->result($label, 'fail', 'Partner split percentage cannot exceed 100%.');
        }

        $unit = ($split['split_type'] ?? 'percentage') === 'percentage' ? '%' : ' (fixed amount)';
        return $this->result($label, 'pass', "Partners receive {$value}{$unit} of the commission pool per deal.");
    }

    private function checkCustomFields(array $config): array
    {
        $label  = 'Deal Fields';
        $fields = $config['fields']['fields'] ?? [];

        if (empty($fields)) {
            return $this->result($label, 'pass', 'No custom deal fields configured — only the standard fields will be used.');
        }

        $keys = array_map(fn ($f) => $f['field_key'] ?? '', $fields);
        if (count($keys) !== count(array_unique($keys))) {
            return $this->result($label, 'fail', 'Custom field keys must be unique.');
        }

        return $this->result($label, 'pass', count($fields) . ' custom deal field(s) will be added (existing data is never deleted).');
    }

    private function checkDocuments(array $config): array
    {
        $label = 'Documents & Agreements';
        $docs  = $config['documents']['required_documents'] ?? [];

        $keys = array_map(fn ($d) => $d['doc_key'] ?? '', $docs);
        if (count($keys) !== count(array_unique($keys))) {
            return $this->result($label, 'fail', 'Required document keys must be unique.');
        }

        $parts = [];
        if (!empty($config['documents']['require_referrer_agreement'])) $parts[] = 'Referrer agreement';
        if (!empty($config['documents']['require_partner_agreement']))  $parts[] = 'Partner agreement';
        if (count($docs) > 0) $parts[] = count($docs) . ' required document(s)';

        if (empty($parts)) {
            return $this->result($label, 'pass', 'No agreements or required documents configured.');
        }

        return $this->result($label, 'pass', implode(', ', $parts) . ' will be required before participants can start.');
    }

    private function checkPublicForm(array $config): array
    {
        $label = 'Public Referral Form';
        $forms = $config['forms'] ?? [];

        if (empty($forms['enable_public_referral_form'])) {
            return $this->result($label, 'pass', 'Public referral form is disabled.');
        }

        $slug = trim((string) ($forms['referral_link_slug'] ?? ''));
        if ($slug === '') {
            return $this->result($label, 'fail', 'Set a link slug before publishing with the public referral form enabled.');
        }

        return $this->result($label, 'warn', "referralbunny.ai/r/{$slug} will go live and accept submissions from anyone with the link once published.");
    }

    private function checkImport(array $config, bool $protected): array
    {
        $label = 'Bulk Import Template';

        if ($protected) {
            return $this->result($label, 'pass', 'This workspace uses a dedicated, locked import template — wizard import settings are not applied.');
        }

        $templateKey = $config['import']['template_key'] ?? null;
        $templates   = ReferralProgramOptions::importTemplates();

        if (!$templateKey || !isset($templates[$templateKey])) {
            return $this->result($label, 'fail', 'Choose an import template before publishing.');
        }

        $note = !empty($config['approvals']['import_approval'])
            ? ' Imports will wait for admin approval before processing.'
            : '';

        return $this->result($label, 'pass', "\"{$templates[$templateKey]['label']}\" will be set as the default import template.{$note}");
    }

    private function checkNotifications(array $config): array
    {
        $label         = 'Notifications';
        $notifications = $config['notifications'] ?? [];

        if (empty($notifications)) {
            return $this->result($label, 'warn', 'No notification preferences configured yet — defaults will apply.');
        }

        $enabled = count(array_filter(
            array_keys(ReferralProgramOptions::notificationEvents()),
            fn ($key) => !empty($notifications[$key])
        ));

        return $this->result($label, 'pass', "{$enabled} notification event(s) enabled.");
    }

    private function checkDashboard(array $config): array
    {
        $label   = 'Dashboard Metrics';
        $widgets = $config['dashboard']['visible_widgets'] ?? [];

        if (empty($widgets)) {
            return $this->result($label, 'warn', 'No dashboard widgets selected — pick a preset on the Dashboard Metrics step.');
        }

        return $this->result($label, 'pass', count($widgets) . ' widget(s) will appear on the dashboard.');
    }

    private function result(string $label, string $status, string $message): array
    {
        return ['label' => $label, 'status' => $status, 'message' => $message];
    }
}

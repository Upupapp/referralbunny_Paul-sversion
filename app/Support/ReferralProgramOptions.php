<?php

namespace App\Support;

/**
 * Static reference data for the Referral Program Setup Wizard (industries,
 * referral goals, program types, participant roles). Shared between server-side
 * validation (ReferralProgramSetupController) and the wizard step views so the
 * two never drift apart.
 */
class ReferralProgramOptions
{
    public static function industries(): array
    {
        return [
            'saas_software'            => 'SaaS / Software',
            'beauty_wellness'          => 'Beauty and Wellness',
            'retail'                   => 'Retail',
            'food_beverage'            => 'Food and Beverage',
            'pet_industry'             => 'Pet Industry',
            'training_events'          => 'Training and Events',
            'ngo_nonprofit'            => 'NGO / Nonprofit',
            'real_estate'              => 'Real Estate',
            'education'                => 'Education',
            'finance_insurance'        => 'Finance / Insurance',
            'healthcare_wellness'      => 'Healthcare / Wellness',
            'professional_services'    => 'Professional Services',
            'agencies_marketing'       => 'Agencies / Marketing',
            'b2b_services'             => 'B2B Services',
            'construction_contractors' => 'Construction / Contractors',
            'hospitality_travel'       => 'Hospitality / Travel',
            'ecommerce'                => 'Ecommerce',
            'manufacturing_distribution' => 'Manufacturing / Distribution',
            'government_public_sector' => 'Government / Public Sector',
            'other'                    => 'Other',
        ];
    }

    public static function referralGoals(): array
    {
        return [
            'generate_sales_leads'      => 'Generate sales leads',
            'track_introduced_accounts' => 'Track introduced accounts',
            'reward_online_signups'     => 'Reward online signups',
            'manage_partner_cosell'     => 'Manage partner co-selling',
            'manage_referrers'          => 'Manage Referrers',
            'reward_customer_referrals' => 'Reward customers for referrals',
            'track_influencer_campaigns'=> 'Track influencer campaigns',
            'recruit_donors_volunteers' => 'Recruit donors or volunteers',
            'track_event_registrations' => 'Track event registrations',
            'track_b2b_introductions'   => 'Track high-value B2B introductions',
            'track_service_bookings'    => 'Track service bookings',
            'track_product_sales'       => 'Track product sales',
            'recommend_for_me'          => "I'm not sure, recommend for me",
        ];
    }

    /**
     * Each program type: label, short explanation, best-for, usual participants,
     * reward style, and a rough complexity rating shown on the Step 3 cards.
     */
    public static function programTypes(): array
    {
        return [
            'referrer_program' => [
                'label' => 'Referrer Program',
                'description' => 'People you know introduce new business and earn a reward when it closes.',
                'best_for' => 'Most service and B2B businesses getting started with referrals.',
                'participants' => 'Referrers, Tenant Admins/Managers',
                'reward_style' => 'Percentage or flat fee per closed deal',
                'complexity' => 'Simple',
            ],
            'sales_referral' => [
                'label' => 'Sales Referral Program',
                'description' => 'Track introductions through a sales pipeline from first contact to paid.',
                'best_for' => 'Companies with a multi-step sales process.',
                'participants' => 'Referrers, Sales team',
                'reward_style' => 'Percentage of deal value',
                'complexity' => 'Moderate',
            ],
            'affiliate' => [
                'label' => 'Affiliate Referral Program',
                'description' => 'Affiliates share a unique link; you track clicks, signups, and conversions.',
                'best_for' => 'SaaS, ecommerce, and online subscription products.',
                'participants' => 'Affiliates / Creators',
                'reward_style' => 'Recurring or one-time commission per conversion',
                'complexity' => 'Moderate',
            ],
            'partner_cosell' => [
                'label' => 'Partner Co-Sell Program',
                'description' => 'Partners introduce and co-sell into their networks alongside your team.',
                'best_for' => 'Agencies, consultancies, and channel-driven B2B.',
                'participants' => 'Partners, Referrers, Sales team',
                'reward_style' => 'Split commission between partner and referrer',
                'complexity' => 'Advanced',
            ],
            'customer_referral' => [
                'label' => 'Customer Referral Program',
                'description' => 'Existing customers refer friends and earn a reward after they purchase.',
                'best_for' => 'Retail, ecommerce, and subscription businesses.',
                'participants' => 'Customers',
                'reward_style' => 'Flat reward or discount per successful referral',
                'complexity' => 'Simple',
            ],
            'influencer_creator' => [
                'label' => 'Influencer / Creator Referral Program',
                'description' => 'Creators promote your brand with trackable codes or links.',
                'best_for' => 'Consumer brands running influencer campaigns.',
                'participants' => 'Affiliates / Creators',
                'reward_style' => 'Commission per sale or flat sponsorship + bonus',
                'complexity' => 'Moderate',
            ],
            'employee_referral' => [
                'label' => 'Employee Referral Program',
                'description' => 'Employees refer candidates or clients and earn a bonus.',
                'best_for' => 'Companies hiring frequently or growing via employee networks.',
                'participants' => 'Employees',
                'reward_style' => 'Flat bonus per hire or closed referral',
                'complexity' => 'Simple',
            ],
            'channel_partner' => [
                'label' => 'Channel Partner Program',
                'description' => 'Resellers and channel partners sell on your behalf.',
                'best_for' => 'Software and hardware vendors with reseller networks.',
                'participants' => 'Partners, Agencies',
                'reward_style' => 'Margin or commission per sale',
                'complexity' => 'Advanced',
            ],
            'agency_referral' => [
                'label' => 'Agency Referral Program',
                'description' => 'Agencies refer their clients to your services.',
                'best_for' => 'Professional services and marketing agencies.',
                'participants' => 'Agencies, Partners',
                'reward_style' => 'Percentage of first contract value',
                'complexity' => 'Moderate',
            ],
            'event_referral' => [
                'label' => 'Event Referral Program',
                'description' => 'Attendees refer others to register for an event or campaign.',
                'best_for' => 'Conferences, webinars, and campaign-driven signups.',
                'participants' => 'Referrers, Public submitters',
                'reward_style' => 'Flat reward per registration',
                'complexity' => 'Simple',
            ],
            'donor_volunteer' => [
                'label' => 'Donor / Volunteer Referral Program',
                'description' => 'Supporters refer new donors or volunteers to your organization.',
                'best_for' => 'Nonprofits and community organizations.',
                'participants' => 'Referrers, Contacts only',
                'reward_style' => 'Tracking only, or recognition-based rewards',
                'complexity' => 'Simple',
            ],
            'service_booking' => [
                'label' => 'Service Booking Referral Program',
                'description' => 'Referrers send clients who book a service, and earn a reward once it is completed.',
                'best_for' => 'Home services, wellness, and appointment-based businesses.',
                'participants' => 'Referrers, Customers',
                'reward_style' => 'Flat or percentage reward per completed booking',
                'complexity' => 'Moderate',
            ],
            'product_sales' => [
                'label' => 'Product Sales Referral Program',
                'description' => 'Referrers introduce buyers for specific products and earn a reward per sale.',
                'best_for' => 'Manufacturers, distributors, and product-led businesses.',
                'participants' => 'Referrers, Partners',
                'reward_style' => 'Percentage or flat fee per product sold',
                'complexity' => 'Moderate',
            ],
            'custom' => [
                'label' => 'Custom Referral Program',
                'description' => 'Start from a blank setup and configure every step yourself.',
                'best_for' => 'Teams with specific requirements not covered by the presets above.',
                'participants' => 'Configured by you',
                'reward_style' => 'Configured by you',
                'complexity' => 'Advanced',
            ],
        ];
    }

    /**
     * Participant roles offered on Step 4. Partner and Referrer are intentionally
     * separate and Partner never inherits Referrer permissions (rule B.6/B.7) —
     * this is enforced system-wide via separate auth guards, not by this list.
     */
    public static function participantRoles(): array
    {
        return [
            'tenant_admins'   => ['label' => 'Tenant Admins', 'description' => 'Full access to manage this workspace and its referral program.'],
            'tenant_managers' => ['label' => 'Tenant Managers', 'description' => 'Day-to-day management, with permissions your Admin controls.'],
            'referrers'       => ['label' => 'Referrers', 'description' => 'Introduce new business and earn rewards when it closes.'],
            'partners'        => ['label' => 'Partners', 'description' => 'A separate, limited role for co-selling — does not inherit Referrer access.'],
            'customers'       => ['label' => 'Customers', 'description' => 'Existing customers who refer friends or colleagues.'],
            'affiliates_creators' => ['label' => 'Affiliates / Creators', 'description' => 'Share links or codes and earn commission on conversions.'],
            'agencies'        => ['label' => 'Agencies', 'description' => 'External agencies referring their own clients.'],
            'employees'       => ['label' => 'Employees', 'description' => 'Staff who refer candidates, clients, or partners.'],
            'public_submitters' => ['label' => 'Public submitters', 'description' => 'Anyone can submit a referral through a public form, no account required.'],
            'contacts_only'   => ['label' => 'Contacts only', 'description' => "People tracked as contacts who don't have a ReferralBunny account or receive emails."],
        ];
    }

    /**
     * Pipeline stage templates for Step 5, keyed by template name. Each stage
     * mirrors the tenant_pipeline_stages columns (stage_key/name/days/color/
     * is_final/is_won); array position is the stage's order.
     */
    public static function pipelineStageTemplates(): array
    {
        return [
            'sales' => [
                ['stage_key' => 'introduction', 'name' => 'Introduction',  'days' => 7,    'color' => '#60A5FA', 'is_final' => false, 'is_won' => false],
                ['stage_key' => 'qualified',    'name' => 'Qualified',     'days' => 7,    'color' => '#7B61FF', 'is_final' => false, 'is_won' => false],
                ['stage_key' => 'proposal',     'name' => 'Proposal Sent', 'days' => 7,    'color' => '#FBBF24', 'is_final' => false, 'is_won' => false],
                ['stage_key' => 'won',          'name' => 'Won',           'days' => null, 'color' => '#34D399', 'is_final' => true,  'is_won' => true],
                ['stage_key' => 'lost',         'name' => 'Lost',          'days' => null, 'color' => '#9CA3AF', 'is_final' => true,  'is_won' => false],
            ],
            'affiliate' => [
                ['stage_key' => 'clicked',   'name' => 'Link Clicked',    'days' => 14,   'color' => '#60A5FA', 'is_final' => false, 'is_won' => false],
                ['stage_key' => 'signed_up', 'name' => 'Signed Up',       'days' => 14,   'color' => '#7B61FF', 'is_final' => false, 'is_won' => false],
                ['stage_key' => 'converted', 'name' => 'Converted (Paid)','days' => null, 'color' => '#34D399', 'is_final' => true,  'is_won' => true],
                ['stage_key' => 'churned',   'name' => 'Churned',         'days' => null, 'color' => '#9CA3AF', 'is_final' => true,  'is_won' => false],
            ],
            'customer' => [
                ['stage_key' => 'referred',      'name' => 'Referred',        'days' => 14,   'color' => '#60A5FA', 'is_final' => false, 'is_won' => false],
                ['stage_key' => 'signed_up',     'name' => 'Signed Up',       'days' => 14,   'color' => '#7B61FF', 'is_final' => false, 'is_won' => false],
                ['stage_key' => 'purchased',     'name' => 'Purchased',       'days' => null, 'color' => '#34D399', 'is_final' => true,  'is_won' => true],
                ['stage_key' => 'not_converted', 'name' => 'Did Not Convert', 'days' => null, 'color' => '#9CA3AF', 'is_final' => true,  'is_won' => false],
            ],
            'partner_cosell' => [
                ['stage_key' => 'introduction', 'name' => 'Introduction',  'days' => 7,    'color' => '#60A5FA', 'is_final' => false, 'is_won' => false],
                ['stage_key' => 'co_selling',   'name' => 'Co-Selling',    'days' => 14,   'color' => '#7B61FF', 'is_final' => false, 'is_won' => false],
                ['stage_key' => 'proposal',     'name' => 'Proposal Sent','days' => 7,    'color' => '#FBBF24', 'is_final' => false, 'is_won' => false],
                ['stage_key' => 'closed_won',   'name' => 'Closed Won',    'days' => null, 'color' => '#34D399', 'is_final' => true,  'is_won' => true],
                ['stage_key' => 'closed_lost',  'name' => 'Closed Lost',   'days' => null, 'color' => '#9CA3AF', 'is_final' => true,  'is_won' => false],
            ],
            'donor_volunteer' => [
                ['stage_key' => 'new_contact', 'name' => 'New Contact', 'days' => 14,   'color' => '#60A5FA', 'is_final' => false, 'is_won' => false],
                ['stage_key' => 'engaged',     'name' => 'Engaged',     'days' => 14,   'color' => '#7B61FF', 'is_final' => false, 'is_won' => false],
                ['stage_key' => 'onboarded',   'name' => 'Onboarded',   'days' => null, 'color' => '#34D399', 'is_final' => true,  'is_won' => true],
                ['stage_key' => 'lapsed',      'name' => 'Lapsed',      'days' => null, 'color' => '#9CA3AF', 'is_final' => true,  'is_won' => false],
            ],
            'service_booking' => [
                ['stage_key' => 'inquiry',   'name' => 'Inquiry',   'days' => 3,    'color' => '#60A5FA', 'is_final' => false, 'is_won' => false],
                ['stage_key' => 'quoted',    'name' => 'Quoted',    'days' => 7,    'color' => '#7B61FF', 'is_final' => false, 'is_won' => false],
                ['stage_key' => 'booked',    'name' => 'Booked',    'days' => 14,   'color' => '#FBBF24', 'is_final' => false, 'is_won' => false],
                ['stage_key' => 'completed', 'name' => 'Completed', 'days' => null, 'color' => '#34D399', 'is_final' => true,  'is_won' => true],
                ['stage_key' => 'cancelled', 'name' => 'Cancelled', 'days' => null, 'color' => '#9CA3AF', 'is_final' => true,  'is_won' => false],
            ],
        ];
    }

    /**
     * Maps a Step 3 program type to the closest pipeline template name above.
     */
    public static function pipelineTemplateForProgramType(?string $programType): string
    {
        return match ($programType) {
            'affiliate', 'influencer_creator'                    => 'affiliate',
            'customer_referral', 'event_referral'                => 'customer',
            'partner_cosell', 'channel_partner', 'agency_referral' => 'partner_cosell',
            'donor_volunteer'                                    => 'donor_volunteer',
            'service_booking'                                    => 'service_booking',
            default                                              => 'sales',
        };
    }

    /**
     * Data types offered when adding a custom deal field on Step 6 — mirrors
     * the data_type options already used by the deal import column mapper.
     */
    public static function customFieldDataTypes(): array
    {
        return [
            'text'    => 'Text',
            'number'  => 'Number',
            'email'   => 'Email',
            'date'    => 'Date',
            'boolean' => 'Yes / No',
            'url'     => 'URL',
            'phone'   => 'Phone',
        ];
    }

    /**
     * Commission types offered on Step 7 — mirrors tenant_program_configs.commission_type.
     */
    public static function commissionTypes(): array
    {
        return [
            'percentage_of_value' => ['label' => 'Percentage of Deal Value', 'description' => 'Commission is a percentage of the Added Amount (Deal Value minus Base Cost).'],
            'fixed_amount'        => ['label' => 'Fixed Amount per Deal', 'description' => 'A flat commission amount is paid out per closed deal, regardless of value.'],
            'placement_fee'       => ['label' => 'Placement Fee', 'description' => 'A one-time fee is paid when a referral results in a placement (e.g. hire, booking, signup).'],
        ];
    }

    /**
     * Reassignment modes offered on Step 7 — mirrors tenant_program_configs.reassignment_mode.
     */
    public static function reassignmentModes(): array
    {
        return [
            'manual'    => ['label' => 'Manual', 'description' => 'Tenant Admins reassign deals between Referrers and Partners by hand.'],
            'automatic' => ['label' => 'Automatic', 'description' => 'Deals are automatically reassigned based on pipeline rules, e.g. on a stage timeout.'],
        ];
    }

    /**
     * Partner split types offered on Step 8 — mirrors CommissionCalculationService::partnerShare()'s $type argument.
     */
    public static function partnerSplitTypes(): array
    {
        return [
            'percentage'   => ['label' => 'Percentage of Commission Pool', 'description' => "The partner's share is a percentage of the commission pool, split with the Referrer."],
            'fixed_amount' => ['label' => 'Fixed Amount per Deal', 'description' => 'The partner receives a flat amount per closed deal, regardless of value.'],
        ];
    }

    /**
     * Document types offered on Step 9's required-documents list — mirrors the
     * shape of reseller_required_documents.document_type.
     */
    public static function documentTypes(): array
    {
        return [
            'government_id'    => 'Government-issued ID',
            'business_permit'  => 'Business Permit',
            'tax_document'     => 'Tax Document',
            'proof_of_address' => 'Proof of Address',
            'certification'    => 'Certification / License',
            'other'            => 'Other',
        ];
    }

    /**
     * Approver role options offered on Step 10.
     */
    public static function approverRoles(): array
    {
        return [
            'owner_admin'         => 'Owner & Admins only',
            'owner_admin_manager' => 'Owner, Admins & Managers',
        ];
    }

    /**
     * Import templates offered on Step 12 — sourced from
     * config/referralbunny_import_templates.php so the wizard and the import
     * tooling never drift apart.
     */
    public static function importTemplates(): array
    {
        return collect(config('referralbunny_import_templates', []))
            ->map(fn (array $tpl) => [
                'label'           => $tpl['label'] ?? '',
                'description'     => $tpl['description'] ?? '',
                'required_fields' => $tpl['required_fields'] ?? [],
                'optional_fields' => $tpl['optional_fields'] ?? [],
            ])
            ->all();
    }

    /**
     * Maps a Step 2 industry to the closest import template key above, used
     * to pre-select a sensible default on Step 12.
     */
    public static function importTemplateForIndustry(?string $industry): string
    {
        return match ($industry) {
            'saas_software'                                        => 'saas',
            'retail', 'ecommerce'                                  => 'retail',
            'food_beverage'                                        => 'fnb',
            'beauty_wellness', 'healthcare_wellness'               => 'beauty',
            'pet_industry'                                         => 'pet',
            'training_events', 'education'                         => 'training',
            'ngo_nonprofit'                                        => 'ngo',
            'real_estate'                                          => 'real_estate',
            'professional_services', 'agencies_marketing',
            'b2b_services', 'finance_insurance',
            'construction_contractors', 'hospitality_travel',
            'manufacturing_distribution'                           => 'professional_services',
            'government_public_sector'                             => 'government_generic',
            default                                                => 'default',
        };
    }
}

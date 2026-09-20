<?php
namespace App\Services\Programs;

use App\Models\Program;
use App\Support\ProtectedTenants;
use Carbon\CarbonImmutable as Date;

/** Calendar-day reporting period in the program timezone, backed by existing referral windows. */
class CampaignPeriod
{
    public function forProgram(Program $program): ?array
    {
        if (ProtectedTenants::isProtected($program->tenant_id)) return null;
        if (!$program->referral_period_opens_at || !$program->referral_period_closes_at) return null;
        $tz = $program->timezone ?: 'UTC';
        $start = Date::instance($program->referral_period_opens_at)->setTimezone($tz)->startOfDay();
        $end = Date::instance($program->referral_period_closes_at)->setTimezone($tz)->startOfDay();
        if ($end->lt($start)) return null;
        $today = Date::now($tz)->startOfDay();
        return ['starts_on'=>$start->toDateString(), 'ends_on'=>$end->toDateString(),
            'days'=>(int)$start->diffInDays($end)+1,
            'state'=>$today->lt($start) ? 'Upcoming' : ($today->gt($end) ? 'Completed' : 'In progress')];
    }
}

@php
 abort_if(\App\Support\ProtectedTenants::isProtected($program->tenant_id),404);
 $program->loadMissing('offers.currentVersion');
 $mechanicsOffers=$program->offers->filter(fn($o)=>$o->status==='active' && $o->currentVersion?->status==='published' && $o->currentVersion->tenant_id===$program->tenant_id && $o->currentVersion->program_id===$program->id);
 $automated=$program->effectiveOperatingMode()==='automated';
 $gethired=$automated && \App\Models\ProgramConnection::where('tenant_id',$program->tenant_id)->where('program_id',$program->id)->where('platform','gethired')->exists();
 $days=$program->attribution_window_days ?? 30;
 $mechanicsTz=$program->timezone ?: 'UTC';
@endphp
<style>
.rbm{--accent:{{ $mechanicsGreen?'#0f766e':'#7734ff' }};--tint:{{ $mechanicsGreen?'#f0fdfa':'#f7f2ff' }};color:#292147}.rbm header,.rbm article{border:1px solid #e8e2f1;border-radius:18px;padding:24px;margin-bottom:16px;background:white}.rbm header{background:linear-gradient(120deg,white,var(--tint));position:relative;padding-right:115px}.rbm h2{font-size:26px;font-weight:750;margin:8px 0}.rbm h3{font-size:17px;font-weight:700;margin:0 0 12px}.rbm p,.rbm li{font-size:14px;line-height:1.8;margin:8px 0}.rbm .eyebrow,.rbm strong{color:var(--accent)}.rbm .eyebrow{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px}.rbm ol{padding-left:22px;list-style:decimal}.rbm .note{background:var(--tint);border-radius:12px;padding:14px;font-size:13px}.rbm img{position:absolute;right:20px;top:28px;width:80px;height:90px;object-fit:contain}.rbm .reward{font-size:29px;font-weight:750;color:var(--accent)}
</style>
<div class="rbm">
<header><span class="eyebrow">{{ $program->referralProgramLabel() }}</span><h2>Program mechanics</h2><p>{{ $program->name }}</p><img src="{{ asset('images/mascots/r-bunny-thumbs-up.webp') }}" alt="Referral Bunny">
<p>How to refer, qualify, and earn. These mechanics follow this program’s current published offers.</p></header>
<article><h3>1. Join and refer</h3>
@if($gethired)
<p>Share your personal referral link with new GetHired employers. They must open the link and create a new employer account within <strong>{{ $days }} days</strong> of the referral visit, using the same browser with referral storage available.</p>
<p>After successful signup attribution, the customer is linked to the referrer’s account. That saved link has no automatic expiry and a later referral link cannot overwrite it. Existing accounts and self-referrals do not qualify.</p>
@elseif($automated)<p>Share your personal program link. The connected platform must identify the referred customer and send a qualifying event. The configured attribution window is <strong>{{ $days }} days</strong>; confirm signup capture support with the company admin.</p>
@else<p>Submit referrals through the company’s agreed process. The company admin assigns each referral to this program and its referrer, reviews its progress, and confirms the qualifying event below.</p>@endif
@if($program->referral_period_opens_at || $program->referral_period_closes_at)
<p><strong>Campaign:</strong> {{ $program->referral_period_opens_at?->setTimezone($mechanicsTz)->format('M j, Y') ?? 'No start date set' }} – {{ $program->referral_period_closes_at?->setTimezone($mechanicsTz)->format('M j, Y') ?? 'No end date set' }} · {{ $mechanicsTz }}.</p>
<p>Campaign dates describe the referral campaign. The reward duration below is measured separately.</p>
@endif
</article>
<article><h3>2. Qualify for a reward</h3>
@if($automated)<p>The customer’s <strong>first qualifying payment must arrive within {{ $days }} days of the original referral visit</strong>. Signing up within that period does not extend the payment deadline. A click or signup alone does not earn a payment-based reward.</p>
<p>The program and referrer membership must be active when a payment is recorded. Payment credit requires the connected platform to deliver a valid payment event to Referral Bunny.</p>
@else<p>Eligibility follows the qualifying event and published offer below. A submitted lead alone does not prove that a reward has been earned.</p>@endif
</article>
@forelse($mechanicsOffers as $offer)
@php $v=$offer->currentVersion; $rules=$v->reward_rules ?? []; @endphp
<article><h3>{{ $offer->name }}</h3><div class="reward">{{ $v->reward_model==='percentage'?(float)$v->percentage_rate.'%':($v->reward_model==='fixed'?$v->currency.' '.number_format($v->fixed_amount,2):'Custom') }} reward</div>
<p><strong>Qualifying event:</strong> {{ ucfirst(str_replace('_',' ',$v->qualifying_event ?? 'Not specified')) }}.</p>
@if(($rules['basis']??'')==='net_collected_excluding_tax')<p>Calculated on collected revenue excluding tax. @if($v->reward_model==='percentage')For example, {{ $v->currency }} 1,000 in eligible collected revenue earns {{ $v->currency }} {{ number_format(1000*(float)$v->percentage_rate/100,2) }}.@endif</p>@endif
@if(($rules['scope']??'')==='recurring')<p><strong>Reward duration:</strong> Eligible payments for {{ $rules['duration_months'] ?? 'the configured number of' }} months from the first payment. Payments on or after that reward period’s end do not earn further rewards.</p>
@elseif(($rules['scope']??'')==='first_payment')<p><strong>Reward duration:</strong> First eligible payment only. Renewals do not earn an additional reward.</p>
@else<p>Additional reward eligibility must be confirmed with the company admin; no recurring duration is specified in this offer.</p>@endif
@if(isset($rules['hold_days']))<p><strong>Hold period:</strong> {{ $rules['hold_days'] }} days after each eligible payment before review eligibility.</p>@endif
@if(($rules['approval']??'')==='manual')<p><strong>Approval:</strong> Company admin review is required. A recorded reward or completed hold is not confirmation that money has been paid.</p>@endif
@if(($rules['refund_policy']??'')==='reverse_reward')<p><strong>Refunds:</strong> Full or partial refunds reverse the corresponding reward.</p>@endif
@if(($rules['self_referrals']??'')==='reject' || ($rules['existing_customers']??'')==='reject')<p><strong>Exclusions:</strong> @if(($rules['self_referrals']??'')==='reject')Self-referrals do not qualify. @endif @if(($rules['existing_customers']??'')==='reject')Existing customers do not qualify.@endif</p>@endif
<p style="font-size:12px">Published offer version {{ $v->version_number }}. Historical recorded rewards keep the offer version applied to them.</p>
</article>
@empty<article><h3>Reward terms not published yet</h3><p>The company admin must publish an active offer before a reward rate and qualifying event can be shown here.</p></article>@endforelse
<article><h3>3. Track your progress</h3><p>Use the dashboard to follow recorded activity and rewards. Contact the company admin through Messages for eligibility questions and payout arrangements.</p><p class="note">These mechanics explain saved program rules. A connected account or recorded signup does not confirm that payment tracking or automatic payouts are enabled. Actual credit requires a qualifying event to be received and accepted.</p></article>
</div>

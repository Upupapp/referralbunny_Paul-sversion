@extends('emails.layouts.base', ['headerLabel' => 'Action Needed'])
@section('content')

<p class="greeting">Hi {{ $referrerName }},</p>
<p class="text">
    Your deal <strong>{{ $dealName }}</strong> has reached the <strong>{{ $stageLabel }}</strong> stage
    but has <strong>no notes yet</strong>.
</p>
<p class="text">
    Adding a note keeps your team informed and helps move the deal forward.
    Deals with regular updates are more likely to be approved quickly.
</p>

<table width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0;border:1px solid #E5E7EB;border-radius:12px;overflow:hidden">
    <tr style="background:#F9FAFB">
        <td style="padding:12px 16px">
            <div style="font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Deal</div>
            <div style="font-size:15px;font-weight:700;color:#1E1B4B">{{ $dealName }}</div>
        </td>
        <td style="padding:12px 16px">
            <div style="font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Stage</div>
            <span style="display:inline-block;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:700;color:#FFFFFF;background:#7B61FF">{{ $stageLabel }}</span>
        </td>
        <td style="padding:12px 16px">
            <div style="font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Notes</div>
            <span style="display:inline-block;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:700;background:#FEE2E2;color:#991B1B">None yet</span>
        </td>
    </tr>
</table>

<div style="text-align:center;margin:24px 0">
    <a href="{{ $dealUrl }}"
       style="display:inline-block;padding:12px 28px;background:#7B61FF;color:#FFFFFF;font-size:14px;font-weight:700;text-decoration:none;border-radius:8px">
        📝 Add a Note Now
    </a>
</div>

<p class="text" style="font-size:12px;color:#6B7280;text-align:center">
    This task was automatically created by {{ $tenantName }}. Once you add a note, this task will be marked complete.
</p>

@endsection

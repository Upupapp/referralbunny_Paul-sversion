{{--
    Email: Import Failed — sent to Admins + Managers when an import batch hard-fails.
    Mailable: App\Mail\ImportFailedMail
    Recipients: all active tenant Admins + Managers
    NOT sent for 'completed_with_warnings' (in-app only for that case).
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import failed — action required</title>
</head>
<body style="margin:0;padding:0;background:#F0EFFA;font-family:'Inter',Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#F0EFFA;padding:40px 16px;">
    <tr>
        <td align="center">
            <table width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;">

                {{-- Logo header --}}
                <tr>
                    <td align="center" style="padding-bottom:24px;">
                        <div style="display:inline-flex;align-items:center;gap:10px;">
                            <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#FF6CAB,#7B61FF);display:flex;align-items:center;justify-content:center;font-size:18px;">🐰</div>
                            <span style="font-weight:700;font-size:15px;color:#1E1B4B;">Referral Bunny</span>
                        </div>
                    </td>
                </tr>

                {{-- Card --}}
                <tr>
                    <td style="background:#FFFFFF;border-radius:16px;padding:32px;box-shadow:0 2px 12px rgba(123,97,255,0.08);">

                        {{-- Red alert icon --}}
                        <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
                            <tr>
                                <td align="center">
                                    <div style="width:52px;height:52px;border-radius:14px;background:#FEE2E2;display:flex;align-items:center;justify-content:center;font-size:24px;margin:0 auto 12px;">❌</div>
                                    <div style="font-size:11px;font-weight:700;letter-spacing:0.05em;color:#DC2626;text-transform:uppercase;margin-bottom:8px;">Import Failed</div>
                                    <h1 style="margin:0;font-size:20px;font-weight:800;color:#1E1B4B;line-height:1.3;">Action required on your import</h1>
                                </td>
                            </tr>
                        </table>

                        {{-- Greeting --}}
                        <p style="margin:0 0 16px;color:#374151;font-size:14px;line-height:1.6;">
                            Hi {{ $recipientName }},
                        </p>
                        <p style="margin:0 0 20px;color:#374151;font-size:14px;line-height:1.6;">
                            A <strong>{{ $typeLabel }}</strong> in your <strong>{{ $tenantName }}</strong> workspace has failed and requires your attention.
                        </p>

                        {{-- Detail box --}}
                        <table width="100%" cellpadding="0" cellspacing="0" style="background:#FEF2F2;border:1px solid #FECACA;border-radius:10px;padding:16px;margin-bottom:24px;">
                            <tr>
                                <td>
                                    <table width="100%" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td style="padding:4px 0;">
                                                <span style="font-size:11px;font-weight:700;color:#DC2626;text-transform:uppercase;letter-spacing:0.04em;">File</span>
                                            </td>
                                            <td style="padding:4px 0;text-align:right;">
                                                <span style="font-size:13px;color:#1E1B4B;font-weight:600;">{{ $fileName }}</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding:4px 0;">
                                                <span style="font-size:11px;font-weight:700;color:#DC2626;text-transform:uppercase;letter-spacing:0.04em;">Failed Rows</span>
                                            </td>
                                            <td style="padding:4px 0;text-align:right;">
                                                <span style="font-size:13px;color:#DC2626;font-weight:700;">{{ $failedRows }} / {{ $totalRows }}</span>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>

                        {{-- CTA --}}
                        <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                            <tr>
                                <td align="center">
                                    <a href="{{ $reportUrl }}"
                                       style="display:inline-block;background:linear-gradient(135deg,#7B61FF,#FF6CAB);color:#fff;text-decoration:none;font-weight:700;font-size:14px;padding:13px 28px;border-radius:10px;">
                                        View Import Report →
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0 0 6px;color:#6B7280;font-size:12px;text-align:center;line-height:1.5;">
                            You're receiving this because you are an Admin or Manager on <strong>{{ $tenantName }}</strong>.
                        </p>

                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td align="center" style="padding-top:20px;">
                        <p style="margin:0;font-size:11px;color:#9CA3AF;">
                            Referral Bunny &mdash; Powered by R Bunny
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>

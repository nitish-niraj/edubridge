<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? config('app.name', 'EduBridge') }}</title>
</head>
<body style="margin:0; background:#f1f5f9; font-family:Arial,Helvetica,sans-serif; color:#1e293b;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="padding:24px 0;">
    <tr>
        <td align="center">
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width:620px; background:#ffffff; border:1px solid #dbe7f5; border-radius:14px; overflow:hidden;">
                <tr>
                    <td style="padding:22px 28px; background:linear-gradient(120deg,#0d3b66 0%, #1f6fb2 100%); color:#ffffff;">
                        <div style="font-size:22px; font-weight:700; letter-spacing:.02em;">EduBridge</div>
                        <div style="font-size:13px; opacity:.9; margin-top:4px;">Trusted online learning</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:26px 28px; line-height:1.7; font-size:15px; color:#334155;">
                        @yield('content')
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 28px 22px; border-top:1px solid #e2e8f0; font-size:12px; color:#64748b;">
                        <div>{{ config('app.name', 'EduBridge') }}</div>
                        <div style="margin-top:4px;">If you did not expect this email, you can ignore it.</div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

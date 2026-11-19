<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Astrotring Login OTP</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;color:#202938;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#f4f6f9;padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:560px;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="padding:26px 32px;background:#172554;color:#ffffff;">
                            <div style="font-size:23px;font-weight:700;letter-spacing:.2px;">Astrotring</div>
                            <div style="font-size:13px;margin-top:6px;color:#dbeafe;">Secure email verification</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px 32px 34px;">
                            <p style="margin:0 0 14px;font-size:16px;">Hello {{ $userName ?: 'there' }},</p>
                            <p style="margin:0 0 22px;font-size:15px;line-height:1.6;color:#4b5563;">Use the one-time password below to sign in to your Astrotring account.</p>
                            <div style="border:1px solid #dbeafe;background:#eff6ff;border-radius:10px;text-align:center;padding:22px 12px;margin:0 0 22px;">
                                <div style="font-size:12px;color:#475569;text-transform:uppercase;letter-spacing:2px;margin-bottom:10px;">Your login OTP</div>
                                <div style="font-size:34px;font-weight:700;letter-spacing:8px;color:#172554;">{{ $otp }}</div>
                            </div>
                            <p style="font-size:14px;line-height:1.6;color:#4b5563;margin:0 0 12px;">This code expires in <strong>{{ $expiresInMinutes }} minutes</strong>.</p>
                            <p style="font-size:14px;line-height:1.6;color:#4b5563;margin:0;">If you did not request this code, you can safely ignore this email. Never share your OTP with anyone.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 32px;background:#f9fafb;border-top:1px solid #e5e7eb;font-size:12px;line-height:1.5;color:#6b7280;">
                            This is an automated message from Astrotring. Please do not reply to this email.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

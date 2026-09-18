<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
  <div style="max-width:480px;margin:0 auto;padding:40px 24px;">
    <h1 style="font-size:20px;margin:0 0 8px;">Reset your password</h1>
    <p style="font-size:14px;line-height:1.6;color:#475569;margin:0 0 24px;">
      {{ $firstName ? "Hi $firstName," : 'Hi,' }} enter this code in SkillLink to choose a new password.
      It expires in 15 minutes.
    </p>
    <div style="font-size:34px;font-weight:bold;letter-spacing:10px;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:20px;text-align:center;">
      {{ $code }}
    </div>
    <p style="font-size:12px;color:#94a3b8;margin:24px 0 0;">
      If you didn't request a password reset, you can safely ignore this email — your password won't change.
    </p>
  </div>
</body>
</html>

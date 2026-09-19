<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $storeName }} — Test email</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; background: #f6f8f6; color: #333; padding: 24px; }
        .wrapper { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; }
        .header { background: #198754; color: #ffffff; padding: 24px 28px; }
        .header h1 { font-size: 20px; font-weight: 700; }
        .body { padding: 28px; }
        .body p { line-height: 1.6; margin-bottom: 14px; }
        .ok { margin: 16px 0; padding: 16px; background: #eefaf2; border: 1px solid #d1e7dd; border-radius: 6px; color: #1b432c; line-height: 1.6; }
        .footer { padding: 20px 28px; border-top: 1px solid #eee; color: #888; font-size: 13px; }
        .footer .sub { margin-bottom: 6px; }
        .tag { color: #999; font-size: 12px; line-height: 1.8; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>{{ $storeName }}</h1>
            <div style="font-size: 13px; opacity: .9;">Test email</div>
        </div>

        <div class="body">
            <p>Hello,</p>
            <p>This is a test email from {{ $storeName }}.</p>

            <div class="ok">
                If you received this message, email delivery is configured correctly and
                replies to customer feedback will be sent to your customers.
            </div>

            <p>Best regards,<br>The {{ $storeName }} team</p>
        </div>

        <div class="footer">
            <div class="sub">You are receiving this because an administrator requested a delivery test.</div>
            <div class="tag">&copy; {{ date('Y') }} {{ $storeName }}. All rights reserved.</div>
        </div>
    </div>
</body>
</html>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $storeName }} — Reply to your message</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; background: #f6f8f6; color: #333; padding: 24px; }
        .wrapper { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; }
        .header { background: #198754; color: #ffffff; padding: 24px 28px; }
        .header h1 { font-size: 20px; font-weight: 700; }
        .body { padding: 28px; }
        .body p { line-height: 1.6; margin-bottom: 14px; }
        .quote { margin: 16px 0; padding: 14px 16px; border-left: 4px solid #cfe3d6; background: #f2f8f4; color: #555; white-space: pre-wrap; }
        .reply { margin: 16px 0; padding: 16px; background: #eefaf2; border: 1px solid #d1e7dd; border-radius: 6px; color: #1b432c; white-space: pre-wrap; line-height: 1.6; }
        .footer { padding: 20px 28px; border-top: 1px solid #eee; color: #888; font-size: 13px; }
        .footer .sub { margin-bottom: 6px; }
        .tag { color: #999; font-size: 12px; line-height: 1.8; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>{{ $storeName }}</h1>
            <div style="font-size: 13px; opacity: .9;">Reply to your message</div>
        </div>

        <div class="body">
            <p>Hello {{ $contactMessage->name }},</p>
            <p>Thanks for reaching out. Here is a copy of your message and our reply:</p>

            <div class="quote">
                <strong style="color: #333;">You wrote:</strong>
                {{ $contactMessage->message }}
            </div>

            <div class="reply">
                <strong style="color: #1b432c;">Our reply:</strong>
                {{ $reply }}
            </div>

            @if ($storeContactPhone)
                <p style="margin-top: 18px;">
                    If you need more help, call us at <a href="tel:{{ $storeContactPhone }}">{{ $storeContactPhone }}</a>
                    @if ($storeContactEmail) or email <a href="mailto:{{ $storeContactEmail }}">{{ $storeContactEmail }}</a>@endif.
                </p>
            @endif

            <p>Best regards,<br>The {{ $storeName }} team</p>
        </div>

        <div class="footer">
            <div class="sub">You are receiving this because you sent us a message through {{ $storeName }}.</div>
            <div class="tag">&copy; {{ date('Y') }} {{ $storeName }}. All rights reserved.</div>
        </div>
    </div>
</body>
</html>
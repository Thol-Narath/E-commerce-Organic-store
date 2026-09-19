<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $storeName }} — We received your message</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; background: #f6f8f6; color: #333; padding: 24px; }
        .wrapper { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; }
        .header { background: #198754; color: #ffffff; padding: 24px 28px; }
        .header h1 { font-size: 20px; font-weight: 700; }
        .body { padding: 28px; }
        .body p { line-height: 1.6; margin-bottom: 14px; }
        .quote { margin: 16px 0; padding: 14px 16px; border-left: 4px solid #cfe3d6; background: #f2f8f4; color: #555; white-space: pre-wrap; }
        .footer { padding: 20px 28px; border-top: 1px solid #eee; color: #888; font-size: 13px; }
        .footer .sub { margin-bottom: 6px; }
        .tag { color: #999; font-size: 12px; line-height: 1.8; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>{{ $storeName }}</h1>
            <div style="font-size: 13px; opacity: .9;">We received your message</div>
        </div>

        <div class="body">
            <p>Hello {{ $contactMessage->name }},</p>
            <p>Thank you for contacting us. We have received your message and will get back to you as soon as possible. Here is a copy of what you sent:</p>

            <div class="quote">
                {{ $contactMessage->message }}
            </div>

            @if ($storeContactPhone || $storeContactEmail)
                <p style="margin-top: 18px;">
                    If your question is urgent, reach us at
                    @if ($storeContactPhone)<a href="tel:{{ $storeContactPhone }}">{{ $storeContactPhone }}</a>@endif
                    @if ($storeContactPhone && $storeContactEmail) or @endif
                    @if ($storeContactEmail)<a href="mailto:{{ $storeContactEmail }}">{{ $storeContactEmail }}</a>@endif.
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
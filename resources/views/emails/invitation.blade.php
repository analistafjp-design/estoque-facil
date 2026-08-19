<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ config('app.name') }}</title>
</head>
<body style="font-family: sans-serif; background: #f3f4f6; padding: 24px; margin: 0;">
    <table role="presentation" width="100%" style="max-width: 480px; margin: 0 auto; background: #ffffff; border-radius: 8px; padding: 32px;">
        <tr>
            <td>
                <h1 style="font-size: 18px; margin: 0 0 16px;">Você foi convidado</h1>
                <p style="font-size: 14px; color: #374151; line-height: 1.5;">
                    Você foi convidado para entrar em <strong>{{ $empresa }}</strong>
                    no {{ config('app.name') }}, como <strong>{{ $papel }}</strong>.
                </p>
                <p style="margin: 24px 0;">
                    <a href="{{ $url }}" style="background: #111827; color: #ffffff; padding: 12px 20px; border-radius: 6px; text-decoration: none; font-size: 14px; font-weight: 600;">
                        Aceitar convite
                    </a>
                </p>
                <p style="font-size: 12px; color: #6b7280; line-height: 1.5;">
                    Este link expira em {{ $expiraEm }}. Se você não esperava este convite, pode ignorar este e-mail.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>

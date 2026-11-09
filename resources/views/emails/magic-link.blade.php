<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Your Login Link - adatempat</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2>Halo, {{ $user->name }}!</h2>
    <p>Klik tombol atau tautan di bawah ini untuk masuk ke akun adatempat Anda. Tautan ini hanya berlaku selama <strong>15 menit</strong> dan hanya dapat digunakan <strong>sekali</strong>.</p>
    <p style="margin: 30px 0;">
        <a href="{{ $url }}" style="background-color: #0284c7; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;">
            Masuk ke adatempat
        </a>
    </p>
    <p style="color: #666666; font-size: 14px;">Jika tombol di atas tidak berfungsi, salin dan tempel URL berikut ke browser Anda:</p>
    <p style="word-break: break-all; color: #0284c7; font-size: 14px;">{{ $url }}</p>
    <hr style="border: none; border-top: 1px solid #eeeeee; margin: 30px 0;">
    <p style="color: #999999; font-size: 12px;">Jika Anda tidak merasa meminta tautan ini, abaikan email ini dengan aman.</p>
</body>
</html>

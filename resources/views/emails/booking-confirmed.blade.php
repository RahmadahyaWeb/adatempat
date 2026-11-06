<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Pemesanan & Tiket</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f4f1ea;
            color: #121316;
            margin: 0;
            padding: 24px;
        }
        .container {
            max-width: 580px;
            margin: 0 auto;
            background-color: #ffffff;
            border: 1px solid rgba(18, 19, 22, 0.15);
            padding: 32px 24px;
        }
        .brand {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #121316;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid rgba(18, 19, 22, 0.1);
        }
        h1 {
            font-size: 22px;
            font-weight: 700;
            color: #121316;
            margin-top: 0;
            margin-bottom: 12px;
        }
        p {
            font-size: 14px;
            line-height: 1.6;
            color: #3b3a36;
            margin: 8px 0;
        }
        .ticket-box {
            background-color: #fcfbfa;
            border: 1px solid rgba(18, 19, 22, 0.15);
            padding: 20px;
            margin: 24px 0;
        }
        .row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 13px;
            border-bottom: 1px dashed rgba(18, 19, 22, 0.1);
        }
        .row:last-child {
            border-bottom: none;
        }
        .label {
            color: #64635e;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .value {
            font-weight: 600;
            color: #121316;
            text-align: right;
        }
        .cta-btn {
            display: inline-block;
            background-color: #2154d6;
            color: #ffffff !important;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            padding: 12px 24px;
            margin: 16px 0;
            text-align: center;
        }
        .token-box {
            background-color: #edf2ff;
            border: 1px solid #c7d2fe;
            padding: 12px;
            margin: 16px 0;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 12px;
            word-break: break-all;
            color: #1e1b4b;
        }
        .footer {
            margin-top: 32px;
            padding-top: 16px;
            border-top: 1px solid rgba(18, 19, 22, 0.1);
            font-size: 12px;
            color: #64635e;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="brand">AdaTempat</div>
        <h1>Pemesanan Terkonfirmasi!</h1>
        <p>Halo <strong>{{ $booking->customer_name }}</strong>,</p>
        <p>Pembayaran Anda sebesar <strong>Rp {{ number_format($booking->total_amount, 0, ',', '.') }}</strong> telah berhasil diverifikasi. Jadwal penggunaan lapangan telah resmi diamankan.</p>

        <div class="ticket-box">
            <div class="row">
                <span class="label">Kode Booking</span>
                <span class="value">{{ $booking->code }}</span>
            </div>
            <div class="row">
                <span class="label">Venue</span>
                <span class="value">{{ $booking->venue->name }}</span>
            </div>
            <div class="row">
                <span class="label">Lapangan</span>
                <span class="value">{{ $booking->space->name }}</span>
            </div>
            <div class="row">
                <span class="label">Tanggal</span>
                <span class="value">{{ $booking->booking_date->format('d M Y') }}</span>
            </div>
            <div class="row">
                <span class="label">Waktu</span>
                <span class="value">{{ substr($booking->start_time, 0, 5) }} – {{ substr($booking->end_time, 0, 5) }}</span>
            </div>
            <div class="row">
                <span class="label">Lokasi</span>
                <span class="value">{{ $booking->venue->address }}, {{ $booking->venue->city }}</span>
            </div>
        </div>

        <div style="text-align: center; margin: 24px 0;">
            <a href="{{ $signedTicketUrl }}" class="cta-btn">Buka Tiket Masuk Digital & QR</a>
        </div>

        <p style="font-size: 12px; color: #64635e;">
            Tunjukkan kode QR pada tiket digital kepada staf resepsionis saat tiba di venue. Anda juga dapat menggunakan token check-in di bawah ini:
        </p>

        <div class="token-box">
            TOKEN CHECK-IN: {{ $booking->qr_checkin_token }}
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} AdaTempat. Tautan tiket ini bersifat privat dan aman.
        </div>
    </div>
</body>
</html>

<?php

declare(strict_types=1);

/**
 * Konfigurasi YesPOS. Salin ke `config/yespos.php`.
 *
 * Nilainya dibaca lewat `env()` hanya di berkas ini, supaya `php artisan
 * config:cache` tetap aman dipakai di produksi.
 */
return [
    'base_url' => env('YESPOS_API_BASE_URL', 'https://api.yespos.id'),
    'key' => env('YESPOS_CLIENT_KEY', ''),
    'secret' => env('YESPOS_CLIENT_SECRET', ''),

    // Kosong = outlet pertama milik pemilik client.
    'outlet_uuid' => env('YESPOS_OUTLET_UUID', ''),

    // Alamat callback status order. Isi dengan URL penuh menuju rute
    // `yespos.notify`, misalnya https://toko.example.com/yespos/notify
    'notify_url' => env('YESPOS_NOTIFY_URL', ''),

    'timeout' => (float) env('YESPOS_TIMEOUT', 15),
    'language' => env('YESPOS_LANGUAGE', 'id'),

    // Terminal EDC. Selama salah satunya kosong, EDC disembunyikan dari daftar
    // metode pembayaran.
    'edc' => [
        'app_code' => env('YESPOS_EDC_APP_CODE', ''),
        'terminal_code' => env('YESPOS_EDC_TERMINAL_CODE', ''),
        'merchant_code' => env('YESPOS_EDC_MERCHANT_CODE', ''),
    ],
];

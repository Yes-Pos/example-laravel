<?php

declare(strict_types=1);

/**
 * Laravel 11+ mendaftarkan provider di berkas ini. Tambahkan baris YesPOS-nya
 * ke `bootstrap/providers.php` milikmu.
 *
 * Di Laravel 10 ke bawah, daftarnya ada di `config/app.php` pada kunci
 * `providers`.
 */
use App\Providers\AppServiceProvider;
use App\Providers\YesposServiceProvider;

return [
    AppServiceProvider::class,
    YesposServiceProvider::class,
];

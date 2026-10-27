<?php

declare(strict_types=1);

/**
 * Dua tambahan YesPOS di `bootstrap/app.php` — keduanya ditandai komentar di
 * bawah. Berkas ini mengikuti kerangka Laravel 11+; di Laravel 10 ke bawah,
 * pengecualian CSRF ada di `app/Http/Middleware/VerifyCsrfToken.php` dan
 * penanganan error di `app/Exceptions/Handler.php`.
 */

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Yespos\Client\YesposException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Callback datang dari YesPOS, bukan dari browser pembeli, jadi tidak
        // membawa token CSRF. Hanya rute ini yang dikecualikan.
        $middleware->validateCsrfTokens(except: ['yespos/notify']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Baris bawaan Laravel; dipertahankan apa adanya.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Kegagalan YesPOS diterjemahkan sekali di sini, supaya controller tetap
        // bersih dari try/catch.
        $exceptions->render(function (YesposException $failure, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null; // biarkan Laravel menampilkan halaman error biasa
            }

            if ($failure->isAuthError()) {
                // Kredensial salah itu masalah server, bukan urusan pembeli.
                Log::error('yespos: kredensial ditolak', ['message' => $failure->getMessage()]);

                return response()->json(['message' => 'Toko sedang tidak dapat melayani pesanan.'], 500);
            }

            if ($failure->isNetworkError()) {
                Log::error('yespos: tidak terjangkau', ['message' => $failure->getMessage()]);

                return response()->json(['message' => 'Server pembayaran sedang tidak dapat dihubungi.'], 502);
            }

            // Sisanya — 400, 404, 409 — memang pesan untuk pembeli.
            return response()->json(['message' => $failure->getMessage()], $failure->status);
        });
    })->create();

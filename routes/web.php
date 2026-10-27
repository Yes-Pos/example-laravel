<?php

declare(strict_types=1);

/**
 * Rute storefront. Salin isinya ke `routes/web.php`.
 *
 * Semua endpoint JSON di sini dipanggil oleh halaman toko sendiri, jadi POST-nya
 * tetap dilindungi CSRF. Hanya rute callback yang dikecualikan — kirimannya
 * datang dari YesPOS, bukan dari browser pembeli.
 */

use App\Http\Controllers\ShopController;
use App\Http\Controllers\YesposWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ShopController::class, 'index'])->name('shop');

Route::prefix('api')->group(function (): void {
    Route::get('catalog', [ShopController::class, 'catalog']);
    Route::get('payment-methods', [ShopController::class, 'paymentMethods']);
    Route::post('checkout', [ShopController::class, 'checkout']);
    Route::get('orders/{uuid}', [ShopController::class, 'order']);
});

Route::post('yespos/notify', YesposWebhookController::class)->name('yespos.notify');

# yespos-example-laravel

Contoh storefront **Laravel** di atas SDK [`yespos/client`](https://github.com/Yes-Pos/yespos-client-sdk-php): katalog produk, keranjang, checkout **QRIS / Virtual Account / EDC**, dan penerima callback status order.

Aplikasi Laravel biasa — tidak ada yang istimewa selain satu service provider, satu berkas config, dan dua controller.

## Menjalankan

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Isi kredensial YesPOS di `.env`:

```dotenv
YESPOS_API_BASE_URL=https://api.yespos.id
YESPOS_CLIENT_KEY=…
YESPOS_CLIENT_SECRET=…
YESPOS_OUTLET_UUID=              # kosong = outlet pertama
YESPOS_NOTIFY_URL=https://toko.example.com/yespos/notify
```

```bash
php artisan serve     # lalu buka http://localhost:8000
```

## Isinya

| Berkas | Guna |
|---|---|
| `config/yespos.php` | Semua `env()` YesPOS, supaya `config:cache` tetap aman |
| `app/Providers/YesposServiceProvider.php` | Menaruh `Client` dan `Shop` di service container |
| `app/Http/Controllers/ShopController.php` | Halaman toko + endpoint JSON yang dipanggil browser |
| `app/Http/Controllers/YesposWebhookController.php` | Callback status order dari YesPOS |
| `resources/views/shop.blade.php` | Halaman toko (HTML + JS, tanpa build step) |
| `bootstrap/app.php` | Pengecualian CSRF untuk callback + penerjemah `YesposException` |
| `routes/web.php` | Rutenya |

### Rute

| Metode | URI | Guna |
|---|---|---|
| `GET` | `/` | Halaman toko |
| `GET` | `/api/catalog` | Outlet dan produknya |
| `GET` | `/api/payment-methods` | Metode aktif; EDC muncul hanya bila terminalnya dikonfigurasi |
| `POST` | `/api/checkout` | Membuat order dari keranjang |
| `GET` | `/api/orders/{uuid}` | Status dan instruksi bayar |
| `POST` | `/yespos/notify` | Callback status order (tanpa CSRF) |

## Pola yang dipakai

**SDK hanya hidup di server.** Browser memanggil endpoint JSON milik aplikasi ini sendiri, jadi `YESPOS_CLIENT_SECRET` tidak pernah ikut terkirim ke mana pun selain header signature.

**Harga tidak pernah datang dari browser.** Aturan validasi `checkout` sengaja tidak menyentuh harga — yang diterima hanya `product_uuid`, `variant_uuid`, dan `quantity`:

```php
$input = $request->validate([
    'lines.*.product_uuid' => ['required', 'string'],
    'lines.*.variant_uuid' => ['nullable', 'string'],
    'lines.*.quantity'     => ['required', 'integer', 'min:1'],
    // …tidak ada 'price' di mana pun
]);
```

`$shop->placeOrder()` yang mengambil harga dari API lalu menghitung totalnya. Angka total yang tampil di halaman cuma perkiraan untuk pembeli.

**Dependency injection, bukan facade.** Provider menaruh `Shop` sebagai singleton, controller tinggal memintanya:

```php
public function __construct(private readonly Shop $shop) {}
```

Jadi di test bisa ditukar dengan `$this->app->instance(Shop::class, $tiruan)`.

**Error diterjemahkan sekali di `bootstrap/app.php`,** bukan di tiap controller:

| Kondisi | Yang dilihat pembeli |
|---|---|
| `isAuthError()` — 401/403 | `500` "Toko sedang tidak dapat melayani pesanan." (detailnya ke log) |
| `isNetworkError()` | `502` "Server pembayaran sedang tidak dapat dihubungi." |
| Sisanya — 400, 404, 409 | Status dan pesan dari YesPOS apa adanya |

**CSRF tetap menyala.** Endpoint JSON dipanggil halaman sendiri, jadi POST-nya membawa `X-CSRF-TOKEN`. Hanya `/yespos/notify` yang dikecualikan — kirimannya datang dari YesPOS, bukan dari browser pembeli.

**Status akhir datang lewat callback, bukan polling.** `YesposWebhookController` mencatat kiriman lalu **membaca ulang order lewat SDK** sebagai sumber kebenaran — isi body callback tidak dipercaya begitu saja. Di halaman, status hanya dicek saat pembeli menekan tombol.

Tempat menyambungkan ke pesanan sendiri ditandai komentar di dua titik: setelah `placeOrder()` berhasil di `ShopController`, dan di cabang `$order->isPaid()` pada webhook. Pastikan langkah di cabang itu idempoten — YesPOS bisa mengirim callback yang sama lebih dari sekali.

## Mencoba tanpa kredensial asli

Arahkan `YESPOS_API_BASE_URL` ke server tiruan. Repo SDK punya satu yang memverifikasi signature persis seperti backend:

```bash
git clone https://github.com/Yes-Pos/yespos-client-sdk-php
cd yespos-client-sdk-php && composer install
php -S 127.0.0.1:9731 tests/server.php
```

Lalu di aplikasi ini: `YESPOS_API_BASE_URL=http://127.0.0.1:9731`.

## Contoh lain

| Repo | Kerangka |
|---|---|
| [`yespos-client-sdk-php`](https://github.com/Yes-Pos/yespos-client-sdk-php) | PHP biasa — CLI dan `php -S` di `examples/` |
| [`yespos-client-sdk-go`](https://github.com/Yes-Pos/yespos-client-sdk-go) | Go |
| [`yespos-example-landingpage`](https://github.com/Yes-Pos/yespos-example-landingpage) | SvelteKit |
| [`yespos-example-landingpage-react`](https://github.com/Yes-Pos/yespos-example-landingpage-react) | Next.js |
| [`yespos-example-landingpage-vue`](https://github.com/Yes-Pos/yespos-example-landingpage-vue) | Nuxt |

## Lisensi

MIT

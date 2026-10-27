<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yespos\Client\Model\CartLine;
use Yespos\Client\Model\Customer;
use Yespos\Client\Model\Order;
use Yespos\Client\Model\PaymentMethod;
use Yespos\Client\Model\Product;
use Yespos\Client\Model\ProductVariant;
use Yespos\Client\Shop;

/**
 * Storefront: halaman toko beserta endpoint JSON yang dipanggil browser.
 *
 * SDK hanya hidup di server. `YESPOS_CLIENT_SECRET` tidak pernah ikut terkirim
 * ke browser, dan harga selalu dihitung ulang dari API.
 */
final class ShopController extends Controller
{
    public function __construct(private readonly Shop $shop)
    {
    }

    /** Halaman toko. */
    public function index(): View
    {
        return view('shop');
    }

    /** Outlet dan produknya — tanpa data internal. */
    public function catalog(): JsonResponse
    {
        $categories = collect($this->shop->products())->map(fn ($category): array => [
            'name' => $category->categoryName,
            'products' => collect($category->products)->map(function (Product $product): array {
                ['min' => $min, 'max' => $max] = $product->priceRange();

                return [
                    'uuid' => $product->uuid,
                    'name' => $product->name,
                    'price' => $min,
                    'price_max' => $max,
                    // Produk lama hanya menyimpan token; URL publiknya dibangun di sini.
                    'image' => $product->mainImageUrl
                        ?? ($product->mainImageToken !== null ? $this->shop->mediaUrl($product->mainImageToken) : null),
                    'sold_out' => $product->isSoldOut(),
                    'variants' => collect($product->variants)->map(fn (ProductVariant $variant): array => [
                        'uuid' => $variant->uuid,
                        'name' => $variant->name,
                        'price' => $variant->price,
                        'sold_out' => $product->trackStock && $variant->currentStock <= 0,
                    ])->all(),
                ];
            })->all(),
        ])->all();

        return response()->json([
            'outlet' => $this->shop->outlet()->name,
            'categories' => $categories,
        ]);
    }

    /** Metode aktif; EDC disembunyikan Shop bila terminalnya tidak dikonfigurasi. */
    public function paymentMethods(): JsonResponse
    {
        return response()->json(
            collect($this->shop->paymentMethods())
                ->map(fn (PaymentMethod $method): array => ['value' => $method->value(), 'label' => $method->name])
                ->all(),
        );
    }

    /**
     * Membuat order dari keranjang.
     *
     * Validasi di sini sengaja tidak menyentuh harga: yang diterima hanya
     * produk dan jumlahnya, lalu `placeOrder()` yang mengambil harga dari API.
     */
    public function checkout(Request $request): JsonResponse
    {
        $input = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_uuid' => ['required', 'string'],
            'lines.*.variant_uuid' => ['nullable', 'string'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'customer.name' => ['required', 'string', 'max:100'],
            'customer.email' => ['required', 'email'],
            'customer.phone' => ['required', 'string', 'max:30'],
            'payment' => ['required', 'string'],
        ]);

        $lines = collect($input['lines'])
            ->map(fn (array $line): CartLine => new CartLine(
                $line['product_uuid'],
                (int) $line['quantity'],
                ($line['variant_uuid'] ?? '') !== '' ? $line['variant_uuid'] : null,
            ))
            ->all();

        $order = $this->shop->placeOrder(
            $lines,
            new Customer($input['customer']['name'], $input['customer']['email'], $input['customer']['phone']),
            $input['payment'],
        );

        // Di aplikasi sungguhan, simpan $order->uuid ke tabel sendiri di sini,
        // supaya callback nanti bisa mencocokkannya dengan pesanan internal.

        return response()->json(self::view($order), 201);
    }

    /** Status dan instruksi bayar sebuah order. */
    public function order(string $uuid): JsonResponse
    {
        return response()->json(self::view($this->shop->order($uuid)));
    }

    /**
     * Bagian order yang aman ditampilkan ke pembeli.
     *
     * @return array<string,mixed>
     */
    private static function view(Order $order): array
    {
        $view = [
            'uuid' => $order->uuid,
            'total_payment' => $order->totalPayment,
            'payment_method' => $order->paymentMethod,
            'status' => $order->status,
            'state' => $order->state()->value,
        ];

        // Tiap metode memberi petunjuk bayar di field yang berbeda.
        return match (true) {
            $order->qrString !== null => $view + ['qr_string' => $order->qrString],
            $order->paymentNo !== null => $view + [
                'payment_no' => $order->paymentNo,
                'payment_name' => $order->paymentName,
                'expired_at' => $order->expiredAt,
            ],
            $order->appLink !== null => $view + ['app_link' => $order->appLink],
            default => $view,
        };
    }
}

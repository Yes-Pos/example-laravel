<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Yespos\Client\Shop;
use Yespos\Client\YesposException;

/**
 * Penerima callback status order dari YesPOS (`YESPOS_NOTIFY_URL`).
 *
 * Isi callback tidak dipercaya begitu saja: statusnya dibaca ulang lewat SDK,
 * dan itulah yang dipakai untuk memperbarui pesanan sendiri.
 */
final class YesposWebhookController extends Controller
{
    public function __construct(private readonly Shop $shop)
    {
    }

    public function __invoke(Request $request): Response
    {
        $uuid = (string) $request->input('uuid', '');
        Log::info('yespos: callback diterima', [
            'uuid' => $uuid,
            'status' => $request->input('status'),
        ]);

        if ($uuid === '') {
            return response()->noContent();
        }

        try {
            $order = $this->shop->order($uuid);
        } catch (YesposException $failure) {
            // Order yang tak terbaca tidak boleh membuat YesPOS mengulang kiriman
            // selamanya; cukup catat lalu balas 200.
            Log::warning('yespos: order callback tidak terbaca', [
                'uuid' => $uuid,
                'status' => $failure->status,
                'message' => $failure->getMessage(),
            ]);

            return response()->noContent();
        }

        Log::info('yespos: status terverifikasi', [
            'uuid' => $order->uuid,
            'status' => $order->status,
            'state' => $order->state()->value,
        ]);

        if ($order->isPaid()) {
            // Di sini pesanan internal ditandai lunas, email dikirim, dan
            // seterusnya. Pastikan langkah ini idempoten: YesPOS bisa mengirim
            // callback yang sama lebih dari sekali.
        }

        return response()->noContent();
    }
}

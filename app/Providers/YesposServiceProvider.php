<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Yespos\Client\Client;
use Yespos\Client\Http\ClientOptions;
use Yespos\Client\Model\EdcTerminal;
use Yespos\Client\Shop;
use Yespos\Client\ShopOptions;

/**
 * Menaruh [Client] dan [Shop] di service container, supaya controller cukup
 * meminta lewat constructor:
 *
 *     public function __construct(private readonly Shop $shop) {}
 */
final class YesposServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ClientOptions::class, static function (Application $app): ClientOptions {
            $config = $app->make('config')->get('yespos');

            return new ClientOptions(
                baseUrl: (string) $config['base_url'],
                key: (string) $config['key'],
                secret: (string) $config['secret'],
                timeout: (float) $config['timeout'],
                language: (string) $config['language'],
            );
        });

        $this->app->singleton(Client::class, static fn (Application $app): Client => new Client(
            $app->make(ClientOptions::class),
        ));

        $this->app->singleton(Shop::class, static function (Application $app): Shop {
            $config = $app->make('config')->get('yespos');
            $edc = $config['edc'];

            return new Shop(
                $app->make(Client::class),
                new ShopOptions(
                    outletUuid: $config['outlet_uuid'] ?: null,
                    notifyUrl: $config['notify_url'] ?: null,
                    // Terminal yang hanya setengah terisi tetap menyembunyikan EDC.
                    edc: $edc['app_code'] && $edc['terminal_code'] && $edc['merchant_code']
                        ? new EdcTerminal($edc['app_code'], $edc['terminal_code'], $edc['merchant_code'])
                        : null,
                ),
            );
        });
    }
}

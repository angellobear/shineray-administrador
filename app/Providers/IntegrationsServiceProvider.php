<?php

namespace App\Providers;

use App\Domain\ErpSync\Clients\ShinerayErpClient;
use App\Domain\ErpSync\Contracts\ErpClientContract;
use App\Domain\Payments\Contracts\PaymentGatewayContract;
use App\Domain\Payments\Gateways\CreditoB2bGateway;
use App\Domain\Payments\Gateways\DatafastGateway;
use App\Domain\Payments\Gateways\DeunaGateway;
use App\Domain\Payments\Gateways\ManualPaymentGateway;
use App\Domain\Payments\Services\PaymentGatewayResolver;
use App\Domain\Search\Contracts\SearchIndexerContract;
use App\Domain\Search\Indexers\ScoutSearchIndexer;
use App\Domain\Shipping\Contracts\ShippingProviderContract;
use App\Domain\Shipping\Providers\UnconfiguredShippingProvider;
use App\Enums\PaymentGateway;
use App\Support\TaxCalculator;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

/**
 * Único lugar donde se decide qué implementación concreta responde a cada
 * contrato de integración externa. Los módulos reemplazan aquí el stub
 * `Unconfigured*` por la implementación real cuando la construyen.
 */
class IntegrationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TaxCalculator::class, fn (): TaxCalculator => TaxCalculator::fromConfig());

        $this->app->bind(ShippingProviderContract::class, UnconfiguredShippingProvider::class);
        $this->app->bind(ErpClientContract::class, ShinerayErpClient::class);
        $this->app->bind(SearchIndexerContract::class, ScoutSearchIndexer::class);

        $this->app->singleton(PaymentGatewayResolver::class, function (Container $app): PaymentGatewayResolver {
            return new PaymentGatewayResolver($app, [
                PaymentGateway::Datafast->value => fn () => new DatafastGateway($app->make(TaxCalculator::class), b2b: false),
                PaymentGateway::DatafastB2b->value => fn () => new DatafastGateway($app->make(TaxCalculator::class), b2b: true),
                PaymentGateway::Deuna->value => fn () => new DeunaGateway(b2b: false),
                PaymentGateway::DeunaB2b->value => fn () => new DeunaGateway(b2b: true),
                PaymentGateway::CreditoB2b->value => CreditoB2bGateway::class,
                PaymentGateway::Manual->value => ManualPaymentGateway::class,
            ]);
        });

        // Inyectar `PaymentGatewayContract` a secas resuelve el gateway B2C por defecto;
        // los servicios que necesiten uno específico usan `PaymentGatewayResolver`.
        $this->app->bind(PaymentGatewayContract::class, function (Container $app): PaymentGatewayContract {
            return $app->make(PaymentGatewayResolver::class)->resolve(PaymentGateway::Datafast);
        });
    }
}

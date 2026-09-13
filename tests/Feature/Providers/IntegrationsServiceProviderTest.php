<?php

use App\Domain\ErpSync\Contracts\ErpClientContract;
use App\Domain\Payments\Contracts\PaymentGatewayContract;
use App\Domain\Payments\Services\PaymentGatewayResolver;
use App\Domain\Search\Contracts\SearchIndexerContract;
use App\Domain\Shipping\Contracts\ShippingProviderContract;
use App\Enums\PaymentGateway;
use App\Support\TaxCalculator;

test('resolves every integration contract from the container', function (string $contract) {
    expect(app($contract))->toBeInstanceOf($contract);
})->with([
    PaymentGatewayContract::class,
    ShippingProviderContract::class,
    ErpClientContract::class,
    SearchIndexerContract::class,
]);

test('registers a gateway for every payment method', function () {
    $resolver = app(PaymentGatewayResolver::class);

    expect($resolver->registered())->toEqualCanonicalizing(PaymentGateway::cases());

    foreach (PaymentGateway::cases() as $gateway) {
        expect($resolver->resolve($gateway)->gateway())->toBe($gateway);
    }
});

test('the tax calculator reads the IVA rate from configuration', function () {
    config()->set('shineray.iva_rate', 0.12);
    app()->forgetInstance(TaxCalculator::class);

    expect(app(TaxCalculator::class)->rate())->toBe(0.12);
});

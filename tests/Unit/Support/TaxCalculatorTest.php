<?php

use App\Support\TaxCalculator;

test('removes the included IVA from a gross amount', function () {
    $calculator = new TaxCalculator(0.15);

    expect($calculator->netFromGross(11500))->toBe(10000);
    expect($calculator->taxFromGross(11500))->toBe(1500);
});

test('adds IVA to a net amount', function () {
    $calculator = new TaxCalculator(0.15);

    expect($calculator->taxFromNet(10000))->toBe(1500);
    expect($calculator->grossFromNet(10000))->toBe(11500);
});

test('converts an ERP gross price in dollars to net cents', function (float|string $erpPrice, int $expectedCents) {
    $calculator = new TaxCalculator(0.15);

    expect($calculator->erpGrossPriceToNetCents($erpPrice))->toBe($expectedCents);
})->with([
    'exact division' => ['11.50', 1000],
    'rounds half up' => [12.99, 1130],
    'string with decimals' => ['0.23', 20],
    'zero' => [0, 0],
]);

test('exposes the gross factor derived from the rate', function () {
    expect((new TaxCalculator(0.15))->grossFactor())->toBe(1.15);
    expect((new TaxCalculator(0.12))->grossFactor())->toBe(1.12);
});

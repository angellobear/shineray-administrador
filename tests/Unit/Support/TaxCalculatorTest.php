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

test('replicates the double rounding of the legacy ERP price conversion', function () {
    $calculator = new TaxCalculator(0.15);

    // 1.149 → 1.15 → 1.00 → 100 (the legacy chain rounds the ERP price to 2 decimals first)
    expect($calculator->erpGrossPriceToNetCents(1.149))->toBe(100);
    // 2.30 → 2.00 → 200
    expect($calculator->erpGrossPriceToNetCents('2.30'))->toBe(200);
});

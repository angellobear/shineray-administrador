<?php

use App\Models\Discount;

test('calculates the discounted amount by type', function (Discount $discount, int $subtotal, int $expected) {
    expect($discount->amountFor($subtotal))->toBe($expected);
})->with([
    'percentage' => [fn () => Discount::factory()->make(['value' => 10]), 20000, 2000],
    'percentage caps at 100' => [fn () => Discount::factory()->make(['value' => 150]), 20000, 20000],
    'fixed' => [fn () => Discount::factory()->fixed(500)->make(), 20000, 500],
    'fixed never exceeds subtotal' => [fn () => Discount::factory()->fixed(50000)->make(), 20000, 20000],
    'free shipping does not touch subtotal' => [fn () => Discount::factory()->freeShipping()->make(), 20000, 0],
]);

test('an expired or exhausted discount is not usable', function () {
    expect(Discount::factory()->expired()->make()->isUsable())->toBeFalse();
    expect(Discount::factory()->make(['usage_limit' => 5, 'usage_count' => 5])->isUsable())->toBeFalse();
    expect(Discount::factory()->make(['is_active' => false])->isUsable())->toBeFalse();
    expect(Discount::factory()->make()->isUsable())->toBeTrue();
});

test('the active scope excludes expired and future discounts', function () {
    Discount::factory()->create(['code' => 'NOW']);
    Discount::factory()->expired()->create(['code' => 'OLD']);
    Discount::factory()->create(['code' => 'SOON', 'starts_at' => now()->addDay()]);

    expect(Discount::active()->pluck('code')->all())->toBe(['NOW']);
});

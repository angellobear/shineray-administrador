<?php

use App\Models\Product;
use App\Models\ProductVariant;

test('only published products are searchable', function () {
    $published = Product::factory()->create();
    $draft = Product::factory()->draft()->create();

    expect($published->shouldBeSearchable())->toBeTrue();
    expect($draft->shouldBeSearchable())->toBeFalse();
    expect(Product::published()->pluck('id')->all())->toBe([$published->id]);
});

test('soft-deleted products leave the index even when published', function () {
    $product = Product::factory()->create();

    $product->delete();

    expect($product->shouldBeSearchable())->toBeFalse();
});

test('variants allow backorders by default so checkout never blocks on local stock', function () {
    $variant = ProductVariant::factory()->outOfStock()->create();

    expect($variant->fresh())
        ->allow_backorder->toBeTrue()
        ->manage_inventory->toBeFalse()
        ->hasStock()->toBeFalse();
});

test('falls back to the configured weight when a variant has none', function () {
    config()->set('shineray.shipping.default_variant_weight_kg', 1.5);
    $variant = ProductVariant::factory()->make(['weight_kg' => null]);

    expect($variant->weightOrDefault())->toBe(1.5);
});

test('exposes the single variant of a product', function () {
    $product = Product::factory()->withVariant()->create();

    expect($product->variant)->toBeInstanceOf(ProductVariant::class);
    expect($product->variants)->toHaveCount(1);
});

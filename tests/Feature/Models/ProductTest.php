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

test('builds the search document with the same shape the storefront reads today', function () {
    $product = Product::factory()->create([
        'metadata' => [
            'CODIGO_MARCA' => 'SHINERAY', 'NOMBRE_MARCA' => 'Shineray', 'NOMBRE_CATEGORIA' => 'SISTEMA DE LUCES',
            'NIVEL_1' => 'REPUESTOS', 'COD_NIVEL_1' => 'N1', 'NIVEL_2' => 'ELECTRICO', 'NIVEL_3' => null,
            'ANIO_DESDE' => 2018, 'is_b2b' => true, 'IS_PROMO' => false, 'OLD_PRICE' => 2000, 'COD_PRODUCTO' => 'ELE-1',
        ],
    ]);
    ProductVariant::factory()->for($product)->create(['sku' => 'ELE-1', 'price' => 1500, 'inventory_quantity' => 3]);

    $document = $product->fresh()->toSearchableArray();

    expect($document)
        ->toHaveKey('id', $product->id)
        ->toHaveKey('status', 'published')
        ->toHaveKey('price', 1500)
        ->toHaveKey('codigoProducto', 'ELE-1')
        ->toHaveKey('nombreMarca', 'Shineray')
        ->toHaveKey('nombreSubsistema', 'ELECTRICO')
        ->toHaveKey('nivel1', 'REPUESTOS')
        ->toHaveKey('codNivel1', 'N1')
        ->toHaveKey('anioDesde', 2018)
        ->toHaveKey('isB2b', true)
        ->toHaveKey('isLandingPromo', false)
        ->toHaveKey('OldPrice', 2000)
        ->toHaveKey('hasStock', true);
    expect((new Product)->searchableAs())->toBe('products');
});

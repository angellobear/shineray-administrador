<?php

use App\Domain\ErpSync\Contracts\ErpClientContract;
use App\Domain\ErpSync\DTOs\StockResult;
use App\Models\IntegrationLog;
use App\Models\Product;
use App\Models\ProductVariant;
use Tests\Support\FakeErpClient;

test('lists only published products with their variant', function () {
    $published = Product::factory()->withVariant()->create(['title' => 'Bujía']);
    Product::factory()->draft()->withVariant()->create(['title' => 'Oculto']);

    $response = $this->getJson(route('store.products.index'));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $published->id)
        ->assertJsonPath('data.0.variant.sku', $published->variant->sku)
        ->assertJsonPath('data.0.attributes.nombreCategoria', $published->metadata['NOMBRE_CATEGORIA'])
        ->assertJsonStructure(['data', 'links', 'meta' => ['current_page', 'total']]);
});

test('filters products by brand and stock', function () {
    $shineray = Product::factory()->create(['metadata' => ['CODIGO_MARCA' => 'SHINERAY']]);
    ProductVariant::factory()->for($shineray)->create(['inventory_quantity' => 4]);
    $other = Product::factory()->create(['metadata' => ['CODIGO_MARCA' => 'OTRA']]);
    ProductVariant::factory()->for($other)->create(['inventory_quantity' => 4]);
    $empty = Product::factory()->create(['metadata' => ['CODIGO_MARCA' => 'SHINERAY']]);
    ProductVariant::factory()->for($empty)->outOfStock()->create();

    $response = $this->getJson(route('store.products.index', ['brand' => 'SHINERAY', 'in_stock' => 1]));

    $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $shineray->id);
});

test('rejects an unknown sort option', function () {
    $this->getJson(route('store.products.index', ['sort' => 'random']))->assertUnprocessable();
});

test('shows a published product by handle and hides drafts', function () {
    $product = Product::factory()->withVariant()->create(['handle' => 'bujia-ngk-b1']);
    $draft = Product::factory()->draft()->withVariant()->create(['handle' => 'oculto-b2']);

    $this->getJson(route('store.products.show', $product))->assertOk()->assertJsonPath('data.handle', 'bujia-ngk-b1');
    $this->getJson(route('store.products.show', $draft))->assertNotFound();
    $this->getJson('/api/store/products/no-existe')->assertNotFound();
});

test('search requires a query and returns a paginated collection', function () {
    $this->getJson(route('store.search'))->assertUnprocessable();

    $this->getJson(route('store.search', ['q' => 'bujia']))
        ->assertOk()
        ->assertJsonStructure(['data', 'meta']);
});

test('returns live stock from the ERP', function () {
    $erp = new FakeErpClient;
    $erp->stock['MOT-1'] = new StockResult('MOT-1', true, 8);
    $this->app->instance(ErpClientContract::class, $erp);

    $this->postJson(route('store.stock'), ['sku' => 'MOT-1'])
        ->assertOk()
        ->assertExactJson(['complete_purchase' => true, 'stock' => 8]);
});

test('answers no stock and logs the failure when the ERP is down', function () {
    $erp = new FakeErpClient;
    $erp->failWith = new RuntimeException('ERP down');
    $this->app->instance(ErpClientContract::class, $erp);

    $this->postJson(route('store.stock'), ['sku' => 'MOT-1'])
        ->assertOk()
        ->assertExactJson(['complete_purchase' => false, 'stock' => 0]);

    expect(IntegrationLog::query()->where('event', 'stock_check_fail')->exists())->toBeTrue();
});

test('stock requires a sku', function () {
    $this->postJson(route('store.stock'), [])->assertUnprocessable();
});

<?php

use App\Models\Customer;
use App\Models\Product;
use Illuminate\Support\Facades\Hash;

test('CORS only allows the configured storefront origins on the API', function () {
    config()->set('cors.allowed_origins', ['https://tienda.test', 'https://landing.test']);
    Product::factory()->withVariant()->create();

    $this->withHeader('Origin', 'https://tienda.test')->getJson(route('store.products.index'))
        ->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', 'https://tienda.test')
        ->assertHeader('Access-Control-Allow-Credentials', 'true');

    $this->withHeader('Origin', 'https://otro.test')->getJson(route('store.products.index'))
        ->assertOk()
        ->assertHeaderMissing('Access-Control-Allow-Origin');
});

test('every response carries the defensive security headers', function () {
    $this->get('/')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

test('customer login is rate limited per email and IP', function () {
    Customer::factory()->create(['email' => 'ana@example.com', 'password' => Hash::make('secret-password')]);

    foreach (range(1, 5) as $attempt) {
        $this->postJson(route('store.auth.login'), ['email' => 'ana@example.com', 'password' => 'wrong'])->assertUnprocessable();
    }

    $this->postJson(route('store.auth.login'), ['email' => 'ana@example.com', 'password' => 'secret-password'])->assertTooManyRequests();
    $this->postJson(route('store.auth.login'), ['email' => 'otra@example.com', 'password' => 'x'])->assertUnprocessable();
});

test('the DeUna webhook and the storefront order lookup are throttled', function () {
    config()->set('services.deuna.webhook_secret', 'hook');

    foreach (range(1, 30) as $attempt) {
        $this->getJson('/api/store/orders/SH-NOPE?email=x@example.com')->assertNotFound();
    }

    $this->getJson('/api/store/orders/SH-NOPE?email=x@example.com')->assertTooManyRequests();
});

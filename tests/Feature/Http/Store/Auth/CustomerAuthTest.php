<?php

use App\Models\Customer;
use App\Notifications\CustomerResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;

test('registers a B2C customer and returns a token', function () {
    $response = $this->postJson(route('store.auth.register'), [
        'email' => 'ana@example.com', 'password' => 'secret-password', 'password_confirmation' => 'secret-password',
        'first_name' => 'Ana', 'last_name' => 'Pérez', 'phone' => '099',
    ]);

    $response->assertCreated()
        ->assertJsonPath('customer.email', 'ana@example.com')
        ->assertJsonPath('customer.is_b2b', false);
    expect($response->json('token'))->toBeString();
    expect(Customer::query()->where('email', 'ana@example.com')->first()->hasAccount())->toBeTrue();
});

test('rejects a duplicate email and a weak payload', function () {
    Customer::factory()->create(['email' => 'ana@example.com']);

    $this->postJson(route('store.auth.register'), ['email' => 'ana@example.com', 'password' => 'x', 'password_confirmation' => 'y'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password', 'first_name', 'last_name']);
});

test('logs in with valid credentials and rejects invalid ones or passwordless accounts', function () {
    Customer::factory()->create(['email' => 'ana@example.com', 'password' => Hash::make('secret-password')]);
    Customer::factory()->withoutPassword()->create(['email' => 'invitado@example.com']);

    $this->postJson(route('store.auth.login'), ['email' => 'ANA@example.com', 'password' => 'secret-password'])
        ->assertOk()
        ->assertJsonPath('customer.email', 'ana@example.com')
        ->assertJsonStructure(['token']);

    $this->postJson(route('store.auth.login'), ['email' => 'ana@example.com', 'password' => 'wrong'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    $this->postJson(route('store.auth.login'), ['email' => 'invitado@example.com', 'password' => 'anything'])
        ->assertUnprocessable();
});

test('returns the authenticated customer and revokes the token on logout', function () {
    $customer = Customer::factory()->create();
    $token = $customer->createToken('test')->plainTextToken;

    $this->withToken($token)->getJson(route('store.auth.me'))->assertOk()->assertJsonPath('data.id', $customer->id);
    $this->withToken($token)->postJson(route('store.auth.logout'))->assertOk();

    expect($customer->tokens()->count())->toBe(0);

    $this->flushHeaders();
    app('auth')->forgetGuards();
    $this->getJson(route('store.auth.me'))->assertUnauthorized();
});

test('sends a reset link without revealing whether the email exists', function () {
    Notification::fake();
    $customer = Customer::factory()->create(['email' => 'ana@example.com']);

    $this->postJson(route('store.auth.forgot-password'), ['email' => 'ana@example.com'])->assertOk();
    $this->postJson(route('store.auth.forgot-password'), ['email' => 'nadie@example.com'])->assertOk();

    Notification::assertSentTo($customer, CustomerResetPasswordNotification::class);
    Notification::assertCount(1);
});

test('resets the password with a valid token and revokes existing tokens', function () {
    $customer = Customer::factory()->withoutPassword()->create(['email' => 'ana@example.com']);
    $customer->createToken('old');
    $token = Password::broker('customers')->createToken($customer);

    $this->postJson(route('store.auth.reset-password'), ['token' => $token, 'email' => 'ana@example.com', 'password' => 'new-secret-pass', 'password_confirmation' => 'new-secret-pass'])
        ->assertOk();

    expect(Hash::check('new-secret-pass', (string) $customer->fresh()->password))->toBeTrue();
    expect($customer->tokens()->count())->toBe(0);

    $this->postJson(route('store.auth.reset-password'), ['token' => 'bad', 'email' => 'ana@example.com', 'password' => 'new-secret-pass', 'password_confirmation' => 'new-secret-pass'])
        ->assertUnprocessable();
});

test('sanctum tokens authenticate the customer guard for store routes', function () {
    Sanctum::actingAs(Customer::factory()->create(), guard: 'customer');

    $this->getJson(route('store.auth.me'))->assertOk();
});

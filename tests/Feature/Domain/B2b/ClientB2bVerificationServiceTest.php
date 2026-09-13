<?php

use App\Domain\B2b\Exceptions\B2bException;
use App\Domain\B2b\Services\ClientB2bVerificationService;
use App\Models\B2bClient;
use App\Models\Customer;
use App\Notifications\CustomerResetPasswordNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->service = app(ClientB2bVerificationService::class);
});

function verificationData(array $overrides = []): array
{
    return array_merge(['email' => 'Ana@Example.com', 'name' => 'Ana', 'last_name' => 'Pérez', 'phone' => '099', 'identification' => 'RUC1', 'dni' => '0999'], $overrides);
}

test('creates a passwordless B2B customer linked to the ERP client and sends the invitation', function () {
    $client = B2bClient::factory()->create(['id_client' => 'RUC1', 'type_client' => 'DM', 'address' => ['city' => 'Quito']]);

    $customer = $this->service->verify(verificationData());

    expect($customer->fresh())
        ->email->toBe('ana@example.com')
        ->password->toBeNull()
        ->is_b2b->toBeTrue()
        ->hasAccount()->toBeFalse();
    expect($customer->metadata)->toHaveKey('ruc', 'RUC1')->toHaveKey('cod_client', 'DM')->toHaveKey('b2b', true);
    expect($client->fresh()->customer_id)->toBe($customer->id);
    Notification::assertSentTo($customer, CustomerResetPasswordNotification::class);
});

test('rejects an identification the ERP does not know', function () {
    expect(fn () => $this->service->verify(verificationData()))->toThrow(B2bException::class);
    expect(Customer::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

test('re-invites an existing customer without account but refuses one that already has a password', function () {
    B2bClient::factory()->create(['id_client' => 'RUC1']);
    $pending = Customer::factory()->withoutPassword()->create(['email' => 'ana@example.com', 'first_name' => 'Viejo']);

    $customer = $this->service->verify(verificationData());
    expect($customer->id)->toBe($pending->id);
    expect($customer->first_name)->toBe('Ana');

    Customer::factory()->create(['email' => 'luis@example.com']);
    expect(fn () => $this->service->verify(verificationData(['email' => 'luis@example.com'])))
        ->toThrow(B2bException::class, 'ya existe con una cuenta');
});

test('the invitation email links to the storefront reset page', function () {
    config()->set('shineray.storefront_url', 'https://tienda.test');
    $customer = Customer::factory()->create(['email' => 'ana@example.com', 'first_name' => 'Ana']);

    $mail = (new CustomerResetPasswordNotification('tok123'))->toMail($customer);

    expect($mail->actionUrl)->toBe('https://tienda.test/reset-password?token=tok123&email=ana%40example.com');
});

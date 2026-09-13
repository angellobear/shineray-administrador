<?php

use App\Enums\Integration;
use App\Models\IntegrationLog;
use App\Models\Payment;

test('records an integration failure linked to the affected payment', function () {
    $payment = Payment::factory()->create();

    $log = IntegrationLog::record(Integration::Servientrega, 'servientrega_fail', ['reason' => 'timeout'], $payment);

    $this->assertModelExists($log);
    expect($log->fresh())
        ->integration->toBe(Integration::Servientrega)
        ->level->toBe('error')
        ->payload->toBe(['reason' => 'timeout']);
    expect($log->loggable)->toBeInstanceOf(Payment::class);
    expect($payment->integrationLogs)->toHaveCount(1);
});

test('records an event without a related model', function () {
    $log = IntegrationLog::record(Integration::ShinerayErp, 'token_refreshed', level: 'info');

    expect($log->fresh())
        ->loggable_id->toBeNull()
        ->level->toBe('info');
});

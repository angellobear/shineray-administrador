<?php

/*
|--------------------------------------------------------------------------
| Reglas de arquitectura (checklist de modulos/11-auth-seguridad.md)
|--------------------------------------------------------------------------
*/

arch('las credenciales solo se leen en config, nunca con env() en la aplicación')
    ->expect('env')
    ->not->toBeUsedIn('app');

arch('no se depura con dd/dump/var_dump en el código de la aplicación')
    ->expect(['dd', 'dump', 'var_dump', 'ray'])
    ->not->toBeUsed();

arch('las integraciones externas se implementan solo detrás de sus contratos')
    ->expect('App\Domain\Payments\Gateways')
    ->toImplement('App\Domain\Payments\Contracts\PaymentGatewayContract')
    ->ignoring('App\Domain\Payments\Gateways\Concerns');

arch('los controladores no llaman a los clientes HTTP de integración directamente')
    ->expect('App\Http\Controllers')
    ->not->toUse([
        'App\Domain\ErpSync\Clients\ShinerayErpClient',
        'App\Domain\Shipping\Providers\ServientregaProvider',
        'App\Domain\Payments\Gateways\DatafastGateway',
        'App\Domain\Payments\Gateways\DeunaGateway',
    ]);

arch('los DTOs de integración son inmutables')
    ->expect(['App\Domain\Payments\DTOs', 'App\Domain\Shipping\DTOs', 'App\Domain\ErpSync\DTOs'])
    ->toBeReadonly();

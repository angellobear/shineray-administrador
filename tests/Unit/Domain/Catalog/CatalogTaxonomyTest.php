<?php

use App\Domain\Catalog\CatalogTaxonomy;

test('derives the subsystem from the ERP category', function (?string $category, ?string $expected) {
    expect(CatalogTaxonomy::deriveSubsistema($category))->toBe($expected);
})->with([
    'tanque anywhere in the name' => ['TANQUE DE GASOLINA', 'TANQUE'],
    'motor category' => ['CARTER Y TAPAS', 'MOTOR'],
    'lowercase input' => ['embrague y transmision', 'MOTOR'],
    'estetico category' => ['GUARDAFANGOS Y PROTECTORES', 'ESTETICO'],
    'tren delantero by keyword' => ['SUSPENSION Y DIRECCION DELANTERA', 'TREN DELANTERO'],
    'tren posterior' => ['SISTEMA DE FRENO', 'TREN POSTERIOR'],
    'estructural' => ['AROS', 'ESTRUCTURAL'],
    'electrico by prefix' => ['E-BATERIAS', 'ELECTRICO'],
    'electrico by name' => ['INSTRUMENTAL', 'ELECTRICO'],
    'unknown category' => ['HERRAMIENTAS', null],
    'empty' => ['', null],
    'null' => [null, null],
]);

test('prefers the real level 3, then the ERP subsystem name, then the derived value', function () {
    expect(CatalogTaxonomy::subsistemaFor(['NIVEL_3' => 'ILUMINACION', 'NOMBRE_SUBSISTEMA' => 'X', 'NOMBRE_CATEGORIA' => 'AROS']))->toBe('ILUMINACION');
    expect(CatalogTaxonomy::subsistemaFor(['NIVEL_3' => null, 'NOMBRE_SUBSISTEMA' => 'FRENOS', 'NOMBRE_CATEGORIA' => 'AROS']))->toBe('FRENOS');
    expect(CatalogTaxonomy::subsistemaFor(['NOMBRE_CATEGORIA' => 'AROS']))->toBe('ESTRUCTURAL');
    expect(CatalogTaxonomy::subsistemaFor([]))->toBeNull();
});

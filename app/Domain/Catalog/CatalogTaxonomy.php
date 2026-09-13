<?php

namespace App\Domain\Catalog;

use Illuminate\Support\Str;

/**
 * Taxonomía del catálogo. `deriveSubsistema()` porta el fallback del
 * transformer de Meilisearch (medusa-config.js 155-178): el ERP no siempre
 * manda `NOMBRE_SUBSISTEMA`, así que se deriva de la categoría.
 */
final class CatalogTaxonomy
{
    public const SUBSISTEMA_TANQUE = 'TANQUE';

    public const SUBSISTEMA_MOTOR = 'MOTOR';

    public const SUBSISTEMA_ESTETICO = 'ESTETICO';

    public const SUBSISTEMA_TREN_DELANTERO = 'TREN DELANTERO';

    public const SUBSISTEMA_TREN_POSTERIOR = 'TREN POSTERIOR';

    public const SUBSISTEMA_ESTRUCTURAL = 'ESTRUCTURAL';

    public const SUBSISTEMA_ELECTRICO = 'ELECTRICO';

    /** @var array<string, list<string>> */
    private const CATEGORY_MAP = [
        self::SUBSISTEMA_MOTOR => [
            'CONJUNTO DE MOTOR', 'CARTER Y TAPAS', 'DISTRIBUCION', 'LUBRICACION', 'LUBRICANTES', 'ARRANQUE',
            'ADMISION / COMBUSTION', 'CAJA DE CAMBIO', 'EMBRAGUE Y TRANSMISION', 'FILTROS Y ESCAPE',
            'EJE PEDAL DE CAMBIO', 'UNETAS Y SELECTOR DE CAMBIO', 'RETENEDORES Y RULIMANES',
        ],
        self::SUBSISTEMA_ESTETICO => [
            'MASCARILLA Y LATERALES', 'DECORATIVO DE CARROCERIA', 'COBERTORES Y COMPLEMENTOS',
            'GUARDAFANGOS Y PROTECTORES', 'ACCESORIOS EXTERNOS', 'CONFORT Y ESTRUCTURA',
        ],
        self::SUBSISTEMA_TREN_POSTERIOR => ['CADENA Y CATALINA', 'CONTROLES', 'MANDOS', 'SISTEMA DE FRENO'],
        self::SUBSISTEMA_ESTRUCTURAL => ['CHASIS Y ESTRUCTURAL', 'AROS', 'ADITAMENTO'],
        self::SUBSISTEMA_ELECTRICO => [
            'SISTEMA DE LUCES', 'SISTEMA ENCENDIDO', 'SISTEMA DE CARGA Y ENERGIA', 'CABLES', 'CABLES Y SENSORES',
            'INSTRUMENTAL', 'SISTEMA DE SONIDO',
        ],
    ];

    /**
     * Subsistema mostrado en el filtro "Tipo de repuesto" del storefront.
     * Prioridad: `NIVEL_3` real → `NOMBRE_SUBSISTEMA` → derivado de la categoría.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function subsistemaFor(array $metadata): ?string
    {
        foreach (['NIVEL_3', 'NOMBRE_SUBSISTEMA'] as $key) {
            if (filled($metadata[$key] ?? null)) {
                return (string) $metadata[$key];
            }
        }

        return self::deriveSubsistema(isset($metadata['NOMBRE_CATEGORIA']) ? (string) $metadata['NOMBRE_CATEGORIA'] : null);
    }

    public static function deriveSubsistema(?string $category): ?string
    {
        if (blank($category)) {
            return null;
        }

        $normalized = Str::upper(trim((string) $category));

        if (str_contains($normalized, 'TANQUE')) {
            return self::SUBSISTEMA_TANQUE;
        }

        if (str_contains($normalized, 'TREN DELANTERO') || str_contains($normalized, 'SUSPENSION Y DIRECCION')) {
            return self::SUBSISTEMA_TREN_DELANTERO;
        }

        if (str_starts_with($normalized, 'E-')) {
            return self::SUBSISTEMA_ELECTRICO;
        }

        foreach (self::CATEGORY_MAP as $subsistema => $categories) {
            if (in_array($normalized, $categories, true)) {
                return $subsistema;
            }
        }

        return null;
    }
}

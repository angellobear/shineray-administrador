<?php

namespace App\Enums;

/**
 * Sistemas externos con los que se integra el backend. Se usa para etiquetar
 * `integration_logs` y las corridas de sincronización.
 */
enum Integration: string
{
    case Datafast = 'datafast';
    case Deuna = 'deuna';
    case Servientrega = 'servientrega';
    case ShinerayErp = 'shineray_erp';
    case Meilisearch = 'meilisearch';
}

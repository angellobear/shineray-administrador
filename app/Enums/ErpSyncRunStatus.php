<?php

namespace App\Enums;

enum ErpSyncRunStatus: string
{
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    /** El sync corrió pero decidió no aplicar cambios (ej. guarda de conteo mínimo). */
    case Skipped = 'skipped';
}

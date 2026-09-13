<?php

namespace App\Console\Commands;

use RuntimeException;

/** Señal interna para revertir la transacción de un `--dry-run`. */
final class DryRunRollback extends RuntimeException {}

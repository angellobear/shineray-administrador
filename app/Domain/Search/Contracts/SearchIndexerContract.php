<?php

namespace App\Domain\Search\Contracts;

use App\Models\Product;

/**
 * Capa fina sobre el motor de búsqueda. El dominio depende de esto, no de
 * Scout/Meilisearch directamente.
 */
interface SearchIndexerContract
{
    public function index(Product $product): void;

    public function remove(Product $product): void;

    public function reindexAll(): void;
}

<?php

namespace App\Domain\Search\Indexers;

use App\Domain\Search\Contracts\SearchIndexerContract;
use App\Models\Product;

final class ScoutSearchIndexer implements SearchIndexerContract
{
    public function index(Product $product): void
    {
        $product->searchable();
    }

    public function remove(Product $product): void
    {
        $product->unsearchable();
    }

    public function reindexAll(): void
    {
        Product::makeAllSearchable();
    }
}

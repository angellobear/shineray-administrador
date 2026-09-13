<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Búsqueda vía Scout (Meilisearch en producción). El storefront puede seguir
 * consultando Meilisearch directo con la API key de solo lectura; este
 * endpoint existe para no exponer la key cuando no haga falta.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:200'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $results = Product::search($validated['q'])
            ->query(fn ($query) => $query->with('variant'))
            ->paginate((int) ($validated['per_page'] ?? 24))
            ->withQueryString();

        return ProductResource::collection($results);
    }
}

<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'brand' => ['sometimes', 'string', 'max:50'],
            'nivel1' => ['sometimes', 'string', 'max:100'],
            'nivel2' => ['sometimes', 'string', 'max:100'],
            'b2b' => ['sometimes', 'boolean'],
            'in_stock' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'in:title,-title,price,-price,newest'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $products = Product::query()
            ->published()
            ->with('variant')
            ->when(isset($validated['brand']), fn ($query) => $query->where('metadata->CODIGO_MARCA', $validated['brand']))
            ->when(isset($validated['nivel1']), fn ($query) => $query->where('metadata->NIVEL_1', $validated['nivel1']))
            ->when(isset($validated['nivel2']), fn ($query) => $query->where('metadata->NIVEL_2', $validated['nivel2']))
            ->when(array_key_exists('b2b', $validated), fn ($query) => $query->where('metadata->is_b2b', $request->boolean('b2b')))
            ->when($request->boolean('in_stock'), fn ($query) => $query->whereHas('variants', fn ($variants) => $variants->where('inventory_quantity', '>', 0)))
            ->tap(function ($query) use ($validated): void {
                match ($validated['sort'] ?? 'title') {
                    '-title' => $query->orderByDesc('title'),
                    'price' => $query->orderBy(fn ($sub) => $sub->select('price')->from('product_variants')->whereColumn('product_id', 'products.id')->limit(1)),
                    '-price' => $query->orderByDesc(fn ($sub) => $sub->select('price')->from('product_variants')->whereColumn('product_id', 'products.id')->limit(1)),
                    'newest' => $query->latest(),
                    default => $query->orderBy('title'),
                };
            })
            ->orderBy('id')
            ->paginate((int) ($validated['per_page'] ?? 24))
            ->withQueryString();

        return ProductResource::collection($products);
    }

    public function show(Product $product): ProductResource
    {
        abort_unless($product->isPublished(), 404);

        return new ProductResource($product->load('variant'));
    }
}

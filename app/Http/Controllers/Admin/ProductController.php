<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'nullable', 'in:draft,published'],
            'b2b' => ['sometimes', 'nullable', 'boolean'],
        ]);

        $products = Product::query()
            ->with('variant')
            ->when($filters['q'] ?? null, fn ($query, string $q) => $query->where(fn ($sub) => $sub
                ->where('title', 'like', "%{$q}%")
                ->orWhereHas('variants', fn ($variants) => $variants->where('sku', 'like', "%{$q}%"))))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when(isset($filters['b2b']), fn ($query) => $query->where('metadata->is_b2b', $request->boolean('b2b')))
            ->orderBy('title')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Product $product): array => [
                'id' => $product->id,
                'title' => $product->title,
                'handle' => $product->handle,
                'thumbnail' => $product->thumbnail,
                'status' => $product->status->value,
                'sku' => $product->variant?->sku,
                'price' => $product->variant?->price,
                'inventory_quantity' => $product->variant?->inventory_quantity,
                'is_b2b' => (bool) ($product->metadata['is_b2b'] ?? false),
                'is_promo' => (bool) ($product->metadata['IS_PROMO'] ?? false),
                'erp_synced_at' => $product->erp_synced_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/products/index', [
            'products' => $products,
            'filters' => $filters,
        ]);
    }

    public function edit(Product $product): Response
    {
        $product->load('variant');

        return Inertia::render('admin/products/edit', [
            'product' => [
                'id' => $product->id,
                'title' => $product->title,
                'description' => $product->description,
                'handle' => $product->handle,
                'thumbnail' => $product->thumbnail,
                'status' => $product->status->value,
                'sku' => $product->variant?->sku,
                'price' => $product->variant?->price,
                'inventory_quantity' => $product->variant?->inventory_quantity,
                'weight_kg' => $product->variant?->weight_kg,
                'is_b2b' => (bool) ($product->metadata['is_b2b'] ?? false),
                'is_promo' => (bool) ($product->metadata['IS_PROMO'] ?? false),
                'old_price' => $product->metadata['OLD_PRICE'] ?? null,
                'metadata' => $product->metadata ?? [],
                'erp_synced_at' => $product->erp_synced_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Solo se editan los campos que no controla el ERP: descripción, estado y
     * los flags comerciales (`is_b2b`, `IS_PROMO`, `OLD_PRICE`) que el sync preserva.
     */
    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $validated = $request->validated();
        $metadata = $product->metadata ?? [];
        $metadata['is_b2b'] = (bool) ($validated['is_b2b'] ?? false);
        $metadata['IS_PROMO'] = (bool) ($validated['is_promo'] ?? false);
        $metadata['OLD_PRICE'] = $validated['old_price'] ?? null;

        $product->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => ProductStatus::from($validated['status']),
            'metadata' => $metadata,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Producto actualizado.']);

        return to_route('admin.products.edit', $product);
    }
}

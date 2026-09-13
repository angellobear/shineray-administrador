<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DiscountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DiscountRequest;
use App\Models\Discount;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DiscountController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/discounts/index', [
            'discounts' => Discount::query()->latest('id')->get()->map(fn (Discount $discount): array => $this->present($discount)),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/discounts/form', ['discount' => null, 'types' => $this->types()]);
    }

    public function store(DiscountRequest $request): RedirectResponse
    {
        Discount::create($this->attributes($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cupón creado.']);

        return to_route('admin.discounts.index');
    }

    public function edit(Discount $discount): Response
    {
        return Inertia::render('admin/discounts/form', ['discount' => $this->present($discount), 'types' => $this->types()]);
    }

    public function update(DiscountRequest $request, Discount $discount): RedirectResponse
    {
        $discount->update($this->attributes($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cupón actualizado.']);

        return to_route('admin.discounts.index');
    }

    public function destroy(Discount $discount): RedirectResponse
    {
        $discount->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cupón eliminado.']);

        return to_route('admin.discounts.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(DiscountRequest $request): array
    {
        $validated = $request->validated();

        return [
            'code' => mb_strtoupper(trim($validated['code'])),
            'type' => DiscountType::from($validated['type']),
            'value' => (int) ($validated['value'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'usage_limit' => $validated['usage_limit'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Discount $discount): array
    {
        return [
            'id' => $discount->id,
            'code' => $discount->code,
            'type' => $discount->type->value,
            'value' => $discount->value,
            'is_active' => $discount->is_active,
            'starts_at' => $discount->starts_at?->toDateString(),
            'ends_at' => $discount->ends_at?->toDateString(),
            'usage_limit' => $discount->usage_limit,
            'usage_count' => $discount->usage_count,
            'is_usable' => $discount->isUsable(),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function types(): array
    {
        return [
            ['value' => DiscountType::Percentage->value, 'label' => 'Porcentaje (%)'],
            ['value' => DiscountType::Fixed->value, 'label' => 'Monto fijo (centavos)'],
            ['value' => DiscountType::FreeShipping->value, 'label' => 'Envío gratis'],
        ];
    }
}

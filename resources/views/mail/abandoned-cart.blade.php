<x-mail::message>
# {{ $header }}

@if ($firstName)
Hola {{ $firstName }},
@else
Hola,
@endif

Dejaste estos repuestos en tu carrito de Shineray Repuestos:

<x-mail::table>
| Producto | Cantidad | Precio |
|:---------|:--------:|-------:|
@foreach ($items as $item)
| {{ $item->variant?->product?->title ?? $item->variant?->sku }} | {{ $item->quantity }} | ${{ number_format($item->unit_price / 100, 2) }} |
@endforeach
</x-mail::table>

**Subtotal:** ${{ number_format($total / 100, 2) }}

<x-mail::button :url="$recoveryUrl">
Completar mi compra
</x-mail::button>

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>

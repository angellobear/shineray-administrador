<x-mail::message>
# ¡Gracias por tu compra!

Hola {{ $address?->first_name ?? '' }},

Recibimos tu orden **#{{ $order->order_number }}**. Te avisaremos cuando salga de bodega.

<x-mail::table>
| Producto | Cantidad | Precio |
|:---------|:--------:|-------:|
@foreach ($items as $item)
| {{ $item->title }} | {{ $item->quantity }} | {{ $money($item->unit_price) }} |
@endforeach
</x-mail::table>

| | |
|:--|--:|
| Subtotal | {{ $money($order->subtotal) }} |
@if ($order->discount_total > 0)
| Descuento | -{{ $money($order->discount_total) }} |
@endif
| Envío | {{ $money($order->shipping_total) }} |
| IVA | {{ $money($order->tax_total) }} |
| **Total** | **{{ $money($order->total) }}** |

@if ($address)
**Dirección de envío**
{{ $address->first_name }} {{ $address->last_name }}
{{ $address->address_1 }}, {{ $address->city }}, {{ $address->province }}
Tel. {{ $address->phone }}
@endif

@if ($guideNumber)
**Guía Servientrega:** {{ $guideNumber }}

<x-mail::button :url="$trackingUrl">
Rastrear mi envío
</x-mail::button>
@else
Tu guía de envío se generará en las próximas horas.
@endif

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>

import { Form, Head, Link } from '@inertiajs/react';
import OrderController from '@/actions/App/Http/Controllers/Admin/OrderController';
import { StatusBadge } from '@/components/admin/status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDateTime, money, orderStatusLabels } from '@/lib/format';
import { dashboard } from '@/routes';
import { index } from '@/routes/admin/orders';

type Order = {
    id: number;
    order_number: string;
    email: string;
    customer_name: string | null;
    status: string;
    gateway: string | null;
    is_b2b: boolean;
    subtotal: number;
    discount_total: number;
    shipping_total: number;
    tax_total: number;
    total: number;
    metadata: {
        dni?: string | null;
        erp_invoice_number?: string | null;
        [key: string]: unknown;
    };
    created_at: string | null;
    paid_at: string | null;
    fulfilled_at: string | null;
    canceled_at: string | null;
    allowed_transitions: string[];
    items: {
        id: number;
        sku: string;
        title: string;
        quantity: number;
        unit_price: number;
        line_total: number;
    }[];
    shipping_address: {
        first_name: string;
        last_name: string;
        phone: string | null;
        address_1: string;
        city: string;
        province: string;
        metadata: Record<string, unknown> | null;
    } | null;
    payments: {
        id: number;
        gateway: string;
        status: string;
        amount: number;
        gateway_reference: string | null;
        response_code: string | null;
        authorized_at: string | null;
    }[];
    shipments: {
        id: number;
        provider: string;
        status: string;
        guide_number: string | null;
        weight_kg: number | null;
    }[];
    discounts: { code: string; type: string; value: number }[];
    logs: {
        id: number;
        integration: string;
        event: string;
        level: string;
        payload: Record<string, unknown> | null;
        created_at: string | null;
    }[];
};

const transitionLabels: Record<string, string> = {
    fulfilled: 'Marcar enviada',
    canceled: 'Cancelar',
    refunded: 'Marcar reembolsada',
};

export default function OrderShow({ order }: { order: Order }) {
    const guide =
        order.shipments.find((s) => s.guide_number)?.guide_number ?? null;

    return (
        <>
            <Head title={order.order_number} />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Orden {order.order_number}
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            {formatDateTime(order.created_at)} ·{' '}
                            {order.gateway ?? 'sin gateway'}
                            {order.is_b2b ? ' · B2B' : ''}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <StatusBadge
                            status={order.status}
                            label={orderStatusLabels[order.status]}
                        />
                        {order.allowed_transitions
                            .filter((status) => status !== 'paid')
                            .map((status) => (
                                <Form
                                    key={status}
                                    {...OrderController.transition.form(
                                        order.order_number,
                                    )}
                                    options={{ preserveScroll: true }}
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <input
                                                type="hidden"
                                                name="status"
                                                value={status}
                                            />
                                            <Button
                                                type="submit"
                                                variant={
                                                    status === 'canceled'
                                                        ? 'destructive'
                                                        : 'secondary'
                                                }
                                                size="sm"
                                                disabled={processing}
                                            >
                                                {transitionLabels[status] ??
                                                    status}
                                            </Button>
                                            <InputError
                                                message={errors.status}
                                            />
                                        </>
                                    )}
                                </Form>
                            ))}
                        {order.status !== 'pending' && (
                            <Form
                                {...OrderController.resendConfirmation.form(
                                    order.order_number,
                                )}
                                options={{ preserveScroll: true }}
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        size="sm"
                                        disabled={processing}
                                    >
                                        Reenviar confirmación
                                    </Button>
                                )}
                            </Form>
                        )}
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Items</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <table className="w-full text-sm">
                                <thead className="text-muted-foreground text-left text-xs uppercase">
                                    <tr>
                                        <th className="py-1">SKU</th>
                                        <th className="py-1">Producto</th>
                                        <th className="py-1 text-right">
                                            Cant.
                                        </th>
                                        <th className="py-1 text-right">
                                            P. unit.
                                        </th>
                                        <th className="py-1 text-right">
                                            Total
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {order.items.map((item) => (
                                        <tr key={item.id} className="border-t">
                                            <td className="py-1 font-mono text-xs">
                                                {item.sku}
                                            </td>
                                            <td className="py-1">
                                                {item.title}
                                            </td>
                                            <td className="py-1 text-right">
                                                {item.quantity}
                                            </td>
                                            <td className="py-1 text-right">
                                                {money(item.unit_price)}
                                            </td>
                                            <td className="py-1 text-right">
                                                {money(item.line_total)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            <dl className="mt-4 ml-auto grid max-w-xs grid-cols-2 gap-1 text-sm">
                                <dt className="text-muted-foreground">
                                    Subtotal
                                </dt>
                                <dd className="text-right">
                                    {money(order.subtotal)}
                                </dd>
                                <dt className="text-muted-foreground">
                                    Descuento
                                </dt>
                                <dd className="text-right">
                                    -{money(order.discount_total)}
                                </dd>
                                <dt className="text-muted-foreground">Envío</dt>
                                <dd className="text-right">
                                    {money(order.shipping_total)}
                                </dd>
                                <dt className="text-muted-foreground">IVA</dt>
                                <dd className="text-right">
                                    {money(order.tax_total)}
                                </dd>
                                <dt className="font-medium">Total</dt>
                                <dd className="text-right font-medium">
                                    {money(order.total)}
                                </dd>
                            </dl>
                            {order.discounts.length > 0 && (
                                <p className="text-muted-foreground mt-2 text-xs">
                                    Cupones:{' '}
                                    {order.discounts
                                        .map(
                                            (d) =>
                                                `${d.code} (${d.type} ${d.value})`,
                                        )
                                        .join(', ')}
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <div className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Cliente y envío</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-1 text-sm">
                                <p className="font-medium">
                                    {order.customer_name ??
                                        order.shipping_address?.first_name}
                                </p>
                                <p className="text-muted-foreground">
                                    {order.email}
                                </p>
                                {order.shipping_address && (
                                    <p className="pt-2">
                                        {order.shipping_address.first_name}{' '}
                                        {order.shipping_address.last_name}
                                        <br />
                                        {order.shipping_address.address_1}
                                        <br />
                                        {order.shipping_address.city},{' '}
                                        {order.shipping_address.province}
                                        <br />
                                        Tel.{' '}
                                        {order.shipping_address.phone ?? '—'} ·
                                        DNI {order.metadata.dni ?? '—'}
                                    </p>
                                )}
                                <p className="pt-2">
                                    Guía Servientrega:{' '}
                                    {guide ? (
                                        <a
                                            href={`https://www.servientrega.com.ec/Tracking/?guia=${guide}&tipo=GUIA`}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="underline"
                                        >
                                            {guide}
                                        </a>
                                    ) : (
                                        <span className="text-muted-foreground">
                                            sin guía
                                        </span>
                                    )}
                                </p>
                                <p>
                                    Factura ERP:{' '}
                                    {order.metadata.erp_invoice_number ??
                                        'pendiente'}
                                </p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Pagos y envíos</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2 text-sm">
                                {order.payments.map((payment) => (
                                    <div
                                        key={payment.id}
                                        className="flex flex-wrap items-center justify-between gap-2 border-b pb-2"
                                    >
                                        <span>
                                            {payment.gateway} ·{' '}
                                            {money(payment.amount)}
                                            <br />
                                            <span className="text-muted-foreground font-mono text-xs">
                                                {payment.gateway_reference ??
                                                    '—'}{' '}
                                                {payment.response_code
                                                    ? `(${payment.response_code})`
                                                    : ''}
                                            </span>
                                        </span>
                                        <StatusBadge status={payment.status} />
                                    </div>
                                ))}
                                {order.shipments.map((shipment) => (
                                    <div
                                        key={shipment.id}
                                        className="flex items-center justify-between gap-2"
                                    >
                                        <span>
                                            {shipment.provider} ·{' '}
                                            {shipment.guide_number ??
                                                'sin guía'}{' '}
                                            · {shipment.weight_kg ?? '—'} kg
                                        </span>
                                        <StatusBadge status={shipment.status} />
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>
                            Bitácora de integraciones de esta orden
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {order.logs.length === 0 && (
                            <p className="text-muted-foreground text-sm">
                                Sin eventos.
                            </p>
                        )}
                        <ul className="divide-y text-sm">
                            {order.logs.map((log) => (
                                <li
                                    key={log.id}
                                    className="flex flex-wrap items-start gap-3 py-2"
                                >
                                    <span className="text-muted-foreground w-36 shrink-0 text-xs">
                                        {formatDateTime(log.created_at)}
                                    </span>
                                    <StatusBadge
                                        status={log.level}
                                        label={log.integration}
                                    />
                                    <span className="font-medium">
                                        {log.event}
                                    </span>
                                    <code className="text-muted-foreground max-w-full truncate text-xs">
                                        {JSON.stringify(log.payload)}
                                    </code>
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>

                <Link href={index()} className="text-sm underline">
                    ← Volver a órdenes
                </Link>
            </div>
        </>
    );
}

OrderShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Órdenes', href: index() },
        { title: 'Detalle', href: '#' },
    ],
};

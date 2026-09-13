import { Form, Head, Link } from '@inertiajs/react';
import CustomerController from '@/actions/App/Http/Controllers/Admin/CustomerController';
import { StatusBadge } from '@/components/admin/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDateTime, money, orderStatusLabels } from '@/lib/format';
import { dashboard } from '@/routes';
import { index } from '@/routes/admin/customers';
import { show as orderShow } from '@/routes/admin/orders';

type Customer = {
    id: number;
    email: string;
    name: string;
    phone: string | null;
    dni: string | null;
    is_b2b: boolean;
    has_account: boolean;
    metadata: Record<string, unknown>;
    created_at: string | null;
    b2b: {
        id_client: string;
        type_client: string | null;
        policy_type: string | null;
        active: boolean;
        address: Record<string, unknown> | null;
    } | null;
    orders: {
        order_number: string;
        status: string;
        total: number;
        created_at: string | null;
    }[];
    addresses: {
        first_name: string;
        last_name: string;
        phone: string | null;
        address_1: string;
        city: string;
        province: string;
    }[];
};

export default function CustomerShow({ customer }: { customer: Customer }) {
    return (
        <>
            <Head title={customer.name || customer.email} />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">
                            {customer.name || customer.email}
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            {customer.email} · registrado{' '}
                            {formatDateTime(customer.created_at)}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <StatusBadge
                            status={customer.is_b2b ? 'published' : 'draft'}
                            label={customer.is_b2b ? 'B2B' : 'B2C'}
                        />
                        {!customer.has_account && (
                            <StatusBadge
                                status="pending"
                                label="Sin contraseña"
                            />
                        )}
                        <Form
                            {...CustomerController.destroy.form(customer.id)}
                            onBefore={() =>
                                confirm(
                                    '¿Eliminar este cliente? Sus órdenes se conservan.',
                                )
                            }
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    size="sm"
                                    disabled={processing}
                                >
                                    Eliminar
                                </Button>
                            )}
                        </Form>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle>Datos</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-1 text-sm">
                            <p>Teléfono: {customer.phone ?? '—'}</p>
                            <p>Cédula: {customer.dni ?? '—'}</p>
                            {customer.b2b && (
                                <div className="mt-3 space-y-1 border-t pt-3">
                                    <p className="font-medium">
                                        Cliente B2B (ERP)
                                    </p>
                                    <p>RUC: {customer.b2b.id_client}</p>
                                    <p>
                                        Tipo: {customer.b2b.type_client ?? '—'}{' '}
                                        → políticas{' '}
                                        {customer.b2b.policy_type ?? '—'}
                                    </p>
                                    <p>
                                        Activo en ERP:{' '}
                                        {customer.b2b.active ? 'sí' : 'no'}
                                    </p>
                                    <pre className="bg-muted mt-1 overflow-x-auto rounded p-2 text-xs">
                                        {JSON.stringify(
                                            customer.b2b.address,
                                            null,
                                            2,
                                        )}
                                    </pre>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Órdenes recientes</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {customer.orders.length === 0 && (
                                <p className="text-muted-foreground text-sm">
                                    Sin órdenes.
                                </p>
                            )}
                            <ul className="divide-y text-sm">
                                {customer.orders.map((order) => (
                                    <li
                                        key={order.order_number}
                                        className="flex flex-wrap items-center justify-between gap-2 py-2"
                                    >
                                        <Link
                                            href={orderShow(order.order_number)}
                                            className="font-medium hover:underline"
                                        >
                                            {order.order_number}
                                        </Link>
                                        <span className="text-muted-foreground text-xs">
                                            {formatDateTime(order.created_at)}
                                        </span>
                                        <StatusBadge
                                            status={order.status}
                                            label={
                                                orderStatusLabels[order.status]
                                            }
                                        />
                                        <span>{money(order.total)}</span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                </div>

                {customer.addresses.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Direcciones</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
                            {customer.addresses.map((address, i) => (
                                <p key={i} className="rounded border p-2">
                                    {address.first_name} {address.last_name}
                                    <br />
                                    {address.address_1}, {address.city},{' '}
                                    {address.province}
                                    <br />
                                    {address.phone}
                                </p>
                            ))}
                        </CardContent>
                    </Card>
                )}

                <Link href={index()} className="text-sm underline">
                    ← Volver a clientes
                </Link>
            </div>
        </>
    );
}

CustomerShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Clientes', href: index() },
        { title: 'Detalle', href: '#' },
    ],
};

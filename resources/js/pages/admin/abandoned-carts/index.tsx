import { Form, Head } from '@inertiajs/react';
import AbandonedCartController from '@/actions/App/Http/Controllers/Admin/AbandonedCartController';
import { DataTable, type Column } from '@/components/admin/data-table';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { StatusBadge } from '@/components/admin/status-badge';
import { Button } from '@/components/ui/button';
import { formatDateTime, money } from '@/lib/format';
import { dashboard } from '@/routes';
import { index } from '@/routes/admin/abandoned-carts';
import type { Paginated } from '@/types/admin';

type CartRow = {
    id: number;
    public_id: string;
    email: string | null;
    name: string | null;
    phone: string | null;
    item_count: number;
    items: { title: string; quantity: number; unit_price: number }[];
    subtotal: number;
    abandoned_count: number;
    abandoned_lastdate: string | null;
    abandoned_completed_at: string | null;
    last_activity_at: string | null;
    created_at: string | null;
};

const columns: Column<CartRow>[] = [
    {
        key: 'customer',
        header: 'Cliente',
        render: (c) => (
            <div>
                <div className="font-medium">{c.name ?? '—'}</div>
                <div className="text-muted-foreground text-xs">{c.email}</div>
                {c.phone && (
                    <div className="text-muted-foreground text-xs">
                        {c.phone}
                    </div>
                )}
            </div>
        ),
    },
    {
        key: 'items',
        header: 'Items',
        render: (c) => (
            <ul className="text-xs">
                {c.items.slice(0, 4).map((item, i) => (
                    <li key={i}>
                        {item.quantity} × {item.title}
                    </li>
                ))}
                {c.items.length > 4 && (
                    <li className="text-muted-foreground">
                        +{c.items.length - 4} más
                    </li>
                )}
            </ul>
        ),
    },
    {
        key: 'subtotal',
        header: 'Subtotal',
        className: 'text-right',
        render: (c) => money(c.subtotal),
    },
    {
        key: 'activity',
        header: 'Última actividad',
        render: (c) => (
            <span className="text-xs">
                {formatDateTime(c.last_activity_at ?? c.created_at)}
            </span>
        ),
    },
    {
        key: 'emails',
        header: 'Emails',
        render: (c) => (
            <div className="text-xs">
                {c.abandoned_count} enviado(s)
                {c.abandoned_lastdate && (
                    <div className="text-muted-foreground">
                        último {formatDateTime(c.abandoned_lastdate)}
                    </div>
                )}
                {c.abandoned_completed_at && (
                    <StatusBadge status="skipped" label="Secuencia cerrada" />
                )}
            </div>
        ),
    },
    {
        key: 'actions',
        header: '',
        render: (c) => (
            <Form
                {...AbandonedCartController.send.form(c.public_id)}
                options={{ preserveScroll: true }}
            >
                {({ processing }) => (
                    <Button
                        type="submit"
                        size="sm"
                        variant="outline"
                        disabled={processing || !c.email}
                    >
                        Enviar recordatorio
                    </Button>
                )}
            </Form>
        ),
    },
];

export default function AbandonedCartsIndex({
    carts,
    intervals,
    enabled,
}: {
    carts: Paginated<CartRow>;
    intervals: number[];
    enabled: boolean;
}) {
    return (
        <>
            <Head title="Carritos abandonados" />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title="Carritos abandonados"
                    description={
                        enabled && intervals.length > 0
                            ? `Envío automático activo: ${intervals.map((m) => `${m} min`).join(', ')} desde la última actividad.`
                            : 'Envío automático desactivado (sin intervalos configurados). Solo envío manual.'
                    }
                />
                <DataTable
                    columns={columns}
                    rows={carts.data}
                    emptyMessage="No hay carritos con email e items pendientes."
                />
                <Pagination paginator={carts} />
            </div>
        </>
    );
}

AbandonedCartsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Carritos abandonados', href: index() },
    ],
};

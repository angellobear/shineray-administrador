import { Head, Link } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { DataTable, type Column } from '@/components/admin/data-table';
import { FilterBar } from '@/components/admin/filter-bar';
import { NativeSelect } from '@/components/admin/native-select';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { StatusBadge } from '@/components/admin/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDateTime, money, orderStatusLabels } from '@/lib/format';
import { dashboard } from '@/routes';
import {
    exportMethod as exportOrders,
    index,
    show,
} from '@/routes/admin/orders';
import type { Filters, Paginated } from '@/types/admin';

type OrderRow = {
    id: number;
    order_number: string;
    email: string;
    customer_name: string | null;
    status: string;
    gateway: string | null;
    is_b2b: boolean;
    total: number;
    created_at: string | null;
};

const columns: Column<OrderRow>[] = [
    {
        key: 'number',
        header: 'Orden',
        render: (o) => (
            <Link
                href={show(o.order_number)}
                className="font-medium hover:underline"
            >
                {o.order_number}
            </Link>
        ),
    },
    {
        key: 'date',
        header: 'Fecha',
        render: (o) => formatDateTime(o.created_at),
    },
    {
        key: 'customer',
        header: 'Cliente',
        render: (o) => (
            <div>
                <div>{o.customer_name ?? '—'}</div>
                <div className="text-muted-foreground text-xs">{o.email}</div>
            </div>
        ),
    },
    {
        key: 'gateway',
        header: 'Pago',
        render: (o) => `${o.gateway ?? '—'}${o.is_b2b ? ' · B2B' : ''}`,
    },
    {
        key: 'status',
        header: 'Estado',
        render: (o) => (
            <StatusBadge
                status={o.status}
                label={orderStatusLabels[o.status]}
            />
        ),
    },
    {
        key: 'total',
        header: 'Total',
        className: 'text-right',
        render: (o) => money(o.total),
    },
];

export default function OrdersIndex({
    orders,
    filters,
}: {
    orders: Paginated<OrderRow>;
    filters: Filters;
}) {
    const exportUrl = exportOrders.url({
        query: Object.fromEntries(
            Object.entries(filters).filter(
                ([, v]) => v !== null && v !== undefined && v !== '',
            ),
        ),
    });

    return (
        <>
            <Head title="Órdenes" />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title="Órdenes"
                    actions={
                        <Button variant="outline" asChild>
                            <a href={exportUrl}>
                                <Download /> Exportar CSV
                            </a>
                        </Button>
                    }
                />
                <FilterBar action={index.url()}>
                    <Input
                        name="q"
                        placeholder="Nº de orden o email"
                        defaultValue={String(filters.q ?? '')}
                        className="w-64"
                    />
                    <NativeSelect
                        name="status"
                        defaultValue={String(filters.status ?? '')}
                    >
                        <option value="">Todos los estados</option>
                        {Object.entries(orderStatusLabels).map(
                            ([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ),
                        )}
                    </NativeSelect>
                    <Input
                        name="from"
                        type="date"
                        defaultValue={String(filters.from ?? '')}
                    />
                    <Input
                        name="to"
                        type="date"
                        defaultValue={String(filters.to ?? '')}
                    />
                    <NativeSelect
                        name="b2b"
                        defaultValue={
                            filters.b2b === undefined || filters.b2b === null
                                ? ''
                                : String(Number(filters.b2b))
                        }
                    >
                        <option value="">B2C y B2B</option>
                        <option value="1">Solo B2B</option>
                        <option value="0">Solo B2C</option>
                    </NativeSelect>
                </FilterBar>
                <DataTable columns={columns} rows={orders.data} />
                <Pagination paginator={orders} />
            </div>
        </>
    );
}

OrdersIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Órdenes', href: index() },
    ],
};

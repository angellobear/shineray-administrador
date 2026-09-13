import { Head, Link } from '@inertiajs/react';
import { DataTable, type Column } from '@/components/admin/data-table';
import { FilterBar } from '@/components/admin/filter-bar';
import { NativeSelect } from '@/components/admin/native-select';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { StatusBadge } from '@/components/admin/status-badge';
import { Input } from '@/components/ui/input';
import { formatDateTime } from '@/lib/format';
import { dashboard } from '@/routes';
import { index, show } from '@/routes/admin/customers';
import type { Filters, Paginated } from '@/types/admin';

type CustomerRow = {
    id: number;
    email: string;
    name: string;
    phone: string | null;
    dni: string | null;
    is_b2b: boolean;
    has_account: boolean;
    orders_count: number;
    ruc: string | null;
    created_at: string | null;
};

const columns: Column<CustomerRow>[] = [
    {
        key: 'name',
        header: 'Cliente',
        render: (c) => (
            <div>
                <Link href={show(c.id)} className="font-medium hover:underline">
                    {c.name || c.email}
                </Link>
                <div className="text-muted-foreground text-xs">{c.email}</div>
            </div>
        ),
    },
    {
        key: 'dni',
        header: 'Identificación',
        render: (c) => c.ruc ?? c.dni ?? '—',
    },
    { key: 'phone', header: 'Teléfono', render: (c) => c.phone ?? '—' },
    {
        key: 'type',
        header: 'Tipo',
        render: (c) => (
            <span className="flex gap-1">
                <StatusBadge
                    status={c.is_b2b ? 'published' : 'draft'}
                    label={c.is_b2b ? 'B2B' : 'B2C'}
                />
                {!c.has_account && (
                    <StatusBadge status="pending" label="Sin contraseña" />
                )}
            </span>
        ),
    },
    {
        key: 'orders',
        header: 'Órdenes',
        className: 'text-right',
        render: (c) => c.orders_count,
    },
    {
        key: 'created',
        header: 'Registro',
        render: (c) => (
            <span className="text-muted-foreground text-xs">
                {formatDateTime(c.created_at)}
            </span>
        ),
    },
];

export default function CustomersIndex({
    customers,
    filters,
}: {
    customers: Paginated<CustomerRow>;
    filters: Filters;
}) {
    return (
        <>
            <Head title="Clientes" />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title="Clientes"
                    description="Clientes del storefront (B2C y B2B)."
                />
                <FilterBar action={index.url()}>
                    <Input
                        name="q"
                        placeholder="Email, nombre o cédula"
                        defaultValue={String(filters.q ?? '')}
                        className="w-64"
                    />
                    <NativeSelect
                        name="b2b"
                        defaultValue={
                            filters.b2b === undefined || filters.b2b === null
                                ? ''
                                : String(Number(filters.b2b))
                        }
                    >
                        <option value="">Todos</option>
                        <option value="1">Solo B2B</option>
                        <option value="0">Solo B2C</option>
                    </NativeSelect>
                </FilterBar>
                <DataTable columns={columns} rows={customers.data} />
                <Pagination paginator={customers} />
            </div>
        </>
    );
}

CustomersIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Clientes', href: index() },
    ],
};

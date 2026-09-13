import { Head, Link } from '@inertiajs/react';
import { DataTable, type Column } from '@/components/admin/data-table';
import { FilterBar } from '@/components/admin/filter-bar';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { StatusBadge } from '@/components/admin/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDateTime } from '@/lib/format';
import { dashboard } from '@/routes';
import { clients, policies, transportistas } from '@/routes/admin/b2b';
import type { Filters, Paginated } from '@/types/admin';

type ClientRow = {
    id: number;
    id_client: string;
    type_client: string | null;
    policy_type: string | null;
    name: string;
    email: string | null;
    phone_number: string | null;
    active: boolean;
    has_customer: boolean;
    customer_has_account: boolean;
    erp_synced_at: string | null;
};

const columns: Column<ClientRow>[] = [
    {
        key: 'ruc',
        header: 'RUC',
        render: (c) => <span className="font-mono text-xs">{c.id_client}</span>,
    },
    { key: 'name', header: 'Nombre', render: (c) => c.name },
    { key: 'email', header: 'Email', render: (c) => c.email ?? '—' },
    {
        key: 'type',
        header: 'Tipo',
        render: (c) =>
            `${c.type_client ?? '—'}${c.policy_type && c.policy_type !== c.type_client ? ` → ${c.policy_type}` : ''}`,
    },
    {
        key: 'state',
        header: 'Estado',
        render: (c) => (
            <span className="flex gap-1">
                <StatusBadge
                    status={c.active ? 'published' : 'draft'}
                    label={c.active ? 'Activo' : 'Inactivo'}
                />
                {c.has_customer ? (
                    <StatusBadge
                        status={
                            c.customer_has_account ? 'succeeded' : 'pending'
                        }
                        label={
                            c.customer_has_account ? 'Con cuenta' : 'Invitado'
                        }
                    />
                ) : (
                    <StatusBadge status="skipped" label="Sin cuenta web" />
                )}
            </span>
        ),
    },
    {
        key: 'synced',
        header: 'Sync',
        render: (c) => (
            <span className="text-muted-foreground text-xs">
                {formatDateTime(c.erp_synced_at)}
            </span>
        ),
    },
];

export function B2bTabs({
    current,
}: {
    current: 'clients' | 'policies' | 'transportistas';
}) {
    const tabs = [
        { key: 'clients', label: 'Clientes', href: clients() },
        { key: 'policies', label: 'Políticas de crédito', href: policies() },
        {
            key: 'transportistas',
            label: 'Transportistas',
            href: transportistas(),
        },
    ] as const;

    return (
        <div className="flex gap-2">
            {tabs.map((tab) => (
                <Button
                    key={tab.key}
                    variant={tab.key === current ? 'default' : 'outline'}
                    size="sm"
                    asChild
                >
                    <Link href={tab.href}>{tab.label}</Link>
                </Button>
            ))}
        </div>
    );
}

export default function B2bClients({
    clients: rows,
    filters,
}: {
    clients: Paginated<ClientRow>;
    filters: Filters;
}) {
    return (
        <>
            <Head title="Clientes B2B" />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title="B2B"
                    description="Datos sincronizados desde el ERP cada 5 minutos."
                    actions={<B2bTabs current="clients" />}
                />
                <FilterBar action={clients.url()}>
                    <Input
                        name="q"
                        placeholder="RUC, nombre o email"
                        defaultValue={String(filters.q ?? '')}
                        className="w-64"
                    />
                </FilterBar>
                <DataTable columns={columns} rows={rows.data} />
                <Pagination paginator={rows} />
            </div>
        </>
    );
}

B2bClients.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'B2B', href: clients() },
    ],
};

import { Head } from '@inertiajs/react';
import { DataTable, type Column } from '@/components/admin/data-table';
import { PageHeader } from '@/components/admin/page-header';
import { StatusBadge } from '@/components/admin/status-badge';
import { formatDateTime } from '@/lib/format';
import { B2bTabs } from '@/pages/admin/b2b/clients';
import { dashboard } from '@/routes';
import { clients, policies } from '@/routes/admin/b2b';

type PolicyRow = {
    id: number;
    client_type: string;
    installments: number;
    credit_factor: number;
    is_active: boolean;
    erp_synced_at: string | null;
};

const columns: Column<PolicyRow>[] = [
    { key: 'type', header: 'Tipo de cliente', render: (p) => p.client_type },
    {
        key: 'installments',
        header: 'Cuotas',
        className: 'text-right',
        render: (p) => p.installments,
    },
    {
        key: 'factor',
        header: 'Factor de crédito',
        className: 'text-right',
        render: (p) => p.credit_factor.toFixed(4),
    },
    {
        key: 'active',
        header: 'Estado',
        render: (p) => (
            <StatusBadge
                status={p.is_active ? 'published' : 'draft'}
                label={p.is_active ? 'Activa' : 'Inactiva'}
            />
        ),
    },
    {
        key: 'synced',
        header: 'Sync',
        render: (p) => (
            <span className="text-muted-foreground text-xs">
                {formatDateTime(p.erp_synced_at)}
            </span>
        ),
    },
];

export default function B2bPolicies({
    policies: rows,
}: {
    policies: PolicyRow[];
}) {
    return (
        <>
            <Head title="Políticas de crédito B2B" />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title="Políticas de crédito"
                    description="Por tipo de cliente (COD_CLIENTEH del ERP). Los clientes DI usan las políticas de DM."
                    actions={<B2bTabs current="policies" />}
                />
                <DataTable columns={columns} rows={rows} />
            </div>
        </>
    );
}

B2bPolicies.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'B2B', href: clients() },
        { title: 'Políticas', href: policies() },
    ],
};

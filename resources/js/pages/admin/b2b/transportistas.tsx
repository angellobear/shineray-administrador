import { Head } from '@inertiajs/react';
import { DataTable, type Column } from '@/components/admin/data-table';
import { PageHeader } from '@/components/admin/page-header';
import { formatDateTime } from '@/lib/format';
import { B2bTabs } from '@/pages/admin/b2b/clients';
import { dashboard } from '@/routes';
import { clients, transportistas } from '@/routes/admin/b2b';

type TransportistaRow = {
    id: number;
    ruc: string;
    business_name: string;
    erp_synced_at: string | null;
};

const columns: Column<TransportistaRow>[] = [
    {
        key: 'ruc',
        header: 'RUC',
        render: (t) => <span className="font-mono text-xs">{t.ruc}</span>,
    },
    { key: 'name', header: 'Razón social', render: (t) => t.business_name },
    {
        key: 'synced',
        header: 'Sync',
        render: (t) => (
            <span className="text-muted-foreground text-xs">
                {formatDateTime(t.erp_synced_at)}
            </span>
        ),
    },
];

export default function B2bTransportistas({
    transportistas: rows,
}: {
    transportistas: TransportistaRow[];
}) {
    return (
        <>
            <Head title="Transportistas B2B" />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title="Transportistas"
                    description="Agencias de transporte disponibles para pedidos B2B."
                    actions={<B2bTabs current="transportistas" />}
                />
                <DataTable columns={columns} rows={rows} />
            </div>
        </>
    );
}

B2bTransportistas.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'B2B', href: clients() },
        { title: 'Transportistas', href: transportistas() },
    ],
};

import { Head } from '@inertiajs/react';
import { DataTable, type Column } from '@/components/admin/data-table';
import { FilterBar } from '@/components/admin/filter-bar';
import { NativeSelect } from '@/components/admin/native-select';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { StatusBadge } from '@/components/admin/status-badge';
import { Input } from '@/components/ui/input';
import { formatDateTime } from '@/lib/format';
import { dashboard } from '@/routes';
import { index } from '@/routes/admin/logs';
import type { Filters, Paginated } from '@/types/admin';

type LogRow = {
    id: number;
    integration: string;
    event: string;
    level: string;
    loggable_type: string | null;
    loggable_id: number | null;
    payload: Record<string, unknown> | null;
    created_at: string | null;
};

const columns: Column<LogRow>[] = [
    {
        key: 'date',
        header: 'Fecha',
        render: (l) => (
            <span className="text-xs whitespace-nowrap">
                {formatDateTime(l.created_at)}
            </span>
        ),
    },
    {
        key: 'integration',
        header: 'Integración',
        render: (l) => <StatusBadge status={l.level} label={l.integration} />,
    },
    {
        key: 'event',
        header: 'Evento',
        render: (l) => <span className="font-medium">{l.event}</span>,
    },
    {
        key: 'ref',
        header: 'Referencia',
        render: (l) =>
            l.loggable_type ? `${l.loggable_type} #${l.loggable_id}` : '—',
    },
    {
        key: 'payload',
        header: 'Detalle',
        render: (l) => (
            <code className="text-muted-foreground block max-w-xl truncate text-xs">
                {JSON.stringify(l.payload)}
            </code>
        ),
    },
];

export default function LogsIndex({
    logs,
    filters,
    integrations,
}: {
    logs: Paginated<LogRow>;
    filters: Filters;
    integrations: string[];
}) {
    return (
        <>
            <Head title="Bitácora de integraciones" />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title="Bitácora de integraciones"
                    description="Eventos y fallos de Datafast, DeUna, Servientrega, ERP y Meilisearch (reemplaza logs/payments.jsonl)."
                />
                <FilterBar action={index.url()}>
                    <NativeSelect
                        name="integration"
                        defaultValue={String(filters.integration ?? '')}
                    >
                        <option value="">Todas las integraciones</option>
                        {integrations.map((integration) => (
                            <option key={integration} value={integration}>
                                {integration}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect
                        name="level"
                        defaultValue={String(filters.level ?? '')}
                    >
                        <option value="">Info y errores</option>
                        <option value="error">Solo errores</option>
                        <option value="info">Solo info</option>
                    </NativeSelect>
                    <Input
                        name="event"
                        placeholder="Evento (ej. invoice_fail)"
                        defaultValue={String(filters.event ?? '')}
                        className="w-56"
                    />
                </FilterBar>
                <DataTable columns={columns} rows={logs.data} />
                <Pagination paginator={logs} />
            </div>
        </>
    );
}

LogsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Bitácora', href: index() },
    ],
};

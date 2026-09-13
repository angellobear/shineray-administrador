import { Form, Head } from '@inertiajs/react';
import ErpSyncController from '@/actions/App/Http/Controllers/Admin/ErpSyncController';
import { DataTable, type Column } from '@/components/admin/data-table';
import { FilterBar } from '@/components/admin/filter-bar';
import { NativeSelect } from '@/components/admin/native-select';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { StatusBadge } from '@/components/admin/status-badge';
import { Button } from '@/components/ui/button';
import { formatDateTime, syncStatusLabels } from '@/lib/format';
import { dashboard } from '@/routes';
import { index } from '@/routes/admin/sync';
import type { Filters, Paginated } from '@/types/admin';

type RunRow = {
    id: number;
    type: string;
    status: string;
    started_at: string;
    finished_at: string | null;
    fetched_count: number;
    created_count: number;
    updated_count: number;
    unpublished_count: number;
    error: string | null;
    metadata: Record<string, unknown> | null;
};

const typeLabels: Record<string, string> = {
    products: 'Productos',
    images: 'Imágenes',
    clients_b2b: 'Clientes B2B',
    policies_b2b: 'Políticas B2B',
    transportistas_b2b: 'Transportistas B2B',
    abandoned_carts: 'Carritos abandonados',
};

const columns: Column<RunRow>[] = [
    {
        key: 'type',
        header: 'Tipo',
        render: (r) => typeLabels[r.type] ?? r.type,
    },
    {
        key: 'status',
        header: 'Estado',
        render: (r) => (
            <StatusBadge status={r.status} label={syncStatusLabels[r.status]} />
        ),
    },
    {
        key: 'started',
        header: 'Inicio',
        render: (r) => (
            <span className="text-xs">{formatDateTime(r.started_at)}</span>
        ),
    },
    {
        key: 'finished',
        header: 'Fin',
        render: (r) => (
            <span className="text-xs">{formatDateTime(r.finished_at)}</span>
        ),
    },
    {
        key: 'counts',
        header: 'Conteos',
        render: (r) => (
            <span className="text-xs">
                {r.fetched_count} recibidos · {r.created_count} nuevos ·{' '}
                {r.updated_count} actualizados
                {r.unpublished_count > 0 &&
                    ` · ${r.unpublished_count} despublicados`}
                {r.metadata &&
                    Object.keys(r.metadata).length > 0 &&
                    ` · ${JSON.stringify(r.metadata)}`}
            </span>
        ),
    },
    {
        key: 'error',
        header: 'Error',
        render: (r) => (
            <span className="text-destructive text-xs">{r.error ?? ''}</span>
        ),
    },
];

export default function SyncIndex({
    runs,
    filters,
    types,
    enabled,
    crons,
}: {
    runs: Paginated<RunRow>;
    filters: Filters;
    types: string[];
    enabled: boolean;
    crons: Record<string, string>;
}) {
    return (
        <>
            <Head title="Sincronización ERP" />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title="Sincronización con el ERP"
                    description={`Programada: ${enabled ? 'activa' : 'desactivada (SHINERAY_ERP_SYNC_ENABLED)'} · productos ${crons.products} · imágenes ${crons.images} · B2B ${crons.b2b}`}
                />
                <div className="flex flex-wrap gap-2 rounded-xl border p-3">
                    <span className="self-center text-sm font-medium">
                        Sincronizar ahora:
                    </span>
                    {types.map((type) => (
                        <Form
                            key={type}
                            {...ErpSyncController.run.form()}
                            options={{ preserveScroll: true }}
                        >
                            {({ processing }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="type"
                                        value={type}
                                    />
                                    <Button
                                        type="submit"
                                        size="sm"
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        {typeLabels[type] ?? type}
                                    </Button>
                                </>
                            )}
                        </Form>
                    ))}
                </div>
                <FilterBar action={index.url()}>
                    <NativeSelect
                        name="type"
                        defaultValue={String(filters.type ?? '')}
                    >
                        <option value="">Todos los tipos</option>
                        {types
                            .filter((t) => t !== 'abandoned_carts')
                            .map((type) => (
                                <option key={type} value={type}>
                                    {typeLabels[type] ?? type}
                                </option>
                            ))}
                    </NativeSelect>
                </FilterBar>
                <DataTable
                    columns={columns}
                    rows={runs.data}
                    emptyMessage="Todavía no hay corridas registradas."
                />
                <Pagination paginator={runs} />
            </div>
        </>
    );
}

SyncIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Sincronización ERP', href: index() },
    ],
};

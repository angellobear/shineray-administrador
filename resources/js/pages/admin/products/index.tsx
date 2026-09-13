import { Head, Link } from '@inertiajs/react';
import { DataTable, type Column } from '@/components/admin/data-table';
import { FilterBar } from '@/components/admin/filter-bar';
import { NativeSelect } from '@/components/admin/native-select';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { StatusBadge } from '@/components/admin/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDateTime, money } from '@/lib/format';
import { dashboard } from '@/routes';
import { edit, index } from '@/routes/admin/products';
import type { Filters, Paginated } from '@/types/admin';

type ProductRow = {
    id: number;
    title: string;
    handle: string;
    thumbnail: string | null;
    status: string;
    sku: string | null;
    price: number | null;
    inventory_quantity: number | null;
    is_b2b: boolean;
    is_promo: boolean;
    erp_synced_at: string | null;
};

const columns: Column<ProductRow>[] = [
    {
        key: 'sku',
        header: 'SKU',
        render: (p) => (
            <span className="font-mono text-xs">{p.sku ?? '—'}</span>
        ),
    },
    {
        key: 'title',
        header: 'Producto',
        render: (p) => (
            <div className="flex items-center gap-2">
                {p.thumbnail && (
                    <img
                        src={p.thumbnail}
                        alt=""
                        className="size-8 rounded object-cover"
                        loading="lazy"
                    />
                )}
                <Link href={edit(p.id)} className="font-medium hover:underline">
                    {p.title}
                </Link>
            </div>
        ),
    },
    {
        key: 'price',
        header: 'Precio',
        className: 'text-right',
        render: (p) => money(p.price),
    },
    {
        key: 'stock',
        header: 'Stock ERP',
        className: 'text-right',
        render: (p) => p.inventory_quantity ?? '—',
    },
    {
        key: 'flags',
        header: 'Flags',
        render: (p) => (
            <span className="flex gap-1">
                {p.is_b2b && <StatusBadge status="published" label="B2B" />}
                {p.is_promo && <StatusBadge status="pending" label="Promo" />}
            </span>
        ),
    },
    {
        key: 'status',
        header: 'Estado',
        render: (p) => (
            <StatusBadge
                status={p.status}
                label={p.status === 'published' ? 'Publicado' : 'Borrador'}
            />
        ),
    },
    {
        key: 'synced',
        header: 'Último sync',
        render: (p) => (
            <span className="text-muted-foreground text-xs">
                {formatDateTime(p.erp_synced_at)}
            </span>
        ),
    },
];

export default function ProductsIndex({
    products,
    filters,
}: {
    products: Paginated<ProductRow>;
    filters: Filters;
}) {
    return (
        <>
            <Head title="Productos" />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title="Productos"
                    description="Catálogo sincronizado desde el ERP. Aquí solo se editan descripción, estado y flags comerciales."
                />
                <FilterBar action={index.url()}>
                    <Input
                        name="q"
                        placeholder="Título o SKU"
                        defaultValue={String(filters.q ?? '')}
                        className="w-64"
                    />
                    <NativeSelect
                        name="status"
                        defaultValue={String(filters.status ?? '')}
                    >
                        <option value="">Todos los estados</option>
                        <option value="published">Publicados</option>
                        <option value="draft">Borradores</option>
                    </NativeSelect>
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
                <DataTable columns={columns} rows={products.data} />
                <Pagination paginator={products} />
                <p className="text-muted-foreground text-xs">
                    Para crear productos nuevos use el ERP: el sync los trae
                    automáticamente.{' '}
                    <Button
                        variant="link"
                        size="sm"
                        className="h-auto p-0"
                        asChild
                    >
                        <Link href="/admin/sync">Ver sincronizaciones</Link>
                    </Button>
                </p>
            </div>
        </>
    );
}

ProductsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Productos', href: index() },
    ],
};

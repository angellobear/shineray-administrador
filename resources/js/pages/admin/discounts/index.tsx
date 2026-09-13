import { Form, Head, Link } from '@inertiajs/react';
import DiscountController from '@/actions/App/Http/Controllers/Admin/DiscountController';
import { DataTable, type Column } from '@/components/admin/data-table';
import { PageHeader } from '@/components/admin/page-header';
import { StatusBadge } from '@/components/admin/status-badge';
import { Button } from '@/components/ui/button';
import { formatDate, money } from '@/lib/format';
import { dashboard } from '@/routes';
import { create, edit, index } from '@/routes/admin/discounts';

export type DiscountRow = {
    id: number;
    code: string;
    type: string;
    value: number;
    is_active: boolean;
    starts_at: string | null;
    ends_at: string | null;
    usage_limit: number | null;
    usage_count: number;
    is_usable: boolean;
};

function describeValue(d: DiscountRow): string {
    if (d.type === 'percentage') {
        return `${d.value}%`;
    }
    if (d.type === 'fixed') {
        return money(d.value);
    }
    return 'Envío gratis';
}

const columns: Column<DiscountRow>[] = [
    {
        key: 'code',
        header: 'Código',
        render: (d) => (
            <Link
                href={edit(d.id)}
                className="font-mono font-medium hover:underline"
            >
                {d.code}
            </Link>
        ),
    },
    { key: 'value', header: 'Descuento', render: describeValue },
    {
        key: 'validity',
        header: 'Vigencia',
        render: (d) => `${formatDate(d.starts_at)} → ${formatDate(d.ends_at)}`,
    },
    {
        key: 'usage',
        header: 'Usos',
        render: (d) =>
            `${d.usage_count}${d.usage_limit ? ` / ${d.usage_limit}` : ''}`,
    },
    {
        key: 'state',
        header: 'Estado',
        render: (d) => (
            <StatusBadge
                status={d.is_usable ? 'published' : 'draft'}
                label={
                    d.is_usable
                        ? 'Vigente'
                        : d.is_active
                          ? 'Fuera de vigencia'
                          : 'Inactivo'
                }
            />
        ),
    },
    {
        key: 'actions',
        header: '',
        render: (d) => (
            <Form
                {...DiscountController.destroy.form(d.id)}
                onBefore={() => confirm(`¿Eliminar el cupón ${d.code}?`)}
            >
                {({ processing }) => (
                    <Button
                        type="submit"
                        size="sm"
                        variant="ghost"
                        disabled={processing}
                    >
                        Eliminar
                    </Button>
                )}
            </Form>
        ),
    },
];

export default function DiscountsIndex({
    discounts,
}: {
    discounts: DiscountRow[];
}) {
    return (
        <>
            <Head title="Cupones" />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title="Cupones de descuento"
                    description="Porcentaje, monto fijo o envío gratis. Se aplican en el carrito del storefront."
                    actions={
                        <Button asChild>
                            <Link href={create()}>Nuevo cupón</Link>
                        </Button>
                    }
                />
                <DataTable
                    columns={columns}
                    rows={discounts}
                    emptyMessage="Todavía no hay cupones."
                />
            </div>
        </>
    );
}

DiscountsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Cupones', href: index() },
    ],
};

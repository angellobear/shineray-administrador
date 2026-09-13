import { Form, Head } from '@inertiajs/react';
import ProductController from '@/actions/App/Http/Controllers/Admin/ProductController';
import { NativeSelect } from '@/components/admin/native-select';
import { PageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDateTime, money } from '@/lib/format';
import { dashboard } from '@/routes';
import { index } from '@/routes/admin/products';

type Product = {
    id: number;
    title: string;
    description: string | null;
    handle: string;
    thumbnail: string | null;
    status: string;
    sku: string | null;
    price: number | null;
    inventory_quantity: number | null;
    weight_kg: number | null;
    is_b2b: boolean;
    is_promo: boolean;
    old_price: number | null;
    metadata: Record<string, string | number | boolean | null | undefined>;
    erp_synced_at: string | null;
};

export default function ProductEdit({ product }: { product: Product }) {
    const erpFields = [
        'CODIGO_MARCA',
        'NOMBRE_MARCA',
        'NOMBRE_CATEGORIA',
        'MOTO_MODELO',
        'NIVEL_1',
        'NIVEL_2',
        'NIVEL_3',
        'NIVEL_4',
        'ANIO_DESDE',
        'ANIO_HASTA',
    ];

    return (
        <>
            <Head title={product.title} />
            <div className="flex flex-col gap-6 p-4">
                <PageHeader
                    title={product.title}
                    description={`SKU ${product.sku ?? '—'} · ${money(product.price)} · stock ERP ${product.inventory_quantity ?? '—'} · último sync ${formatDateTime(product.erp_synced_at)}`}
                />

                <div className="grid gap-6 lg:grid-cols-3">
                    <Form
                        {...ProductController.update.form(product.id)}
                        className="space-y-5 lg:col-span-2"
                        options={{ preserveScroll: true }}
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="title">Título</Label>
                                    <Input
                                        id="title"
                                        name="title"
                                        defaultValue={product.title}
                                        required
                                    />
                                    <InputError message={errors.title} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="description">
                                        Descripción
                                    </Label>
                                    <textarea
                                        id="description"
                                        name="description"
                                        defaultValue={product.description ?? ''}
                                        rows={6}
                                        className="border-input focus-visible:ring-ring/50 rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                                    />
                                    <InputError message={errors.description} />
                                </div>
                                <div className="grid gap-2 sm:max-w-xs">
                                    <Label htmlFor="status">Estado</Label>
                                    <NativeSelect
                                        id="status"
                                        name="status"
                                        defaultValue={product.status}
                                    >
                                        <option value="published">
                                            Publicado
                                        </option>
                                        <option value="draft">
                                            Borrador (oculto en la tienda)
                                        </option>
                                    </NativeSelect>
                                    <InputError message={errors.status} />
                                </div>
                                <fieldset className="grid gap-3 rounded-lg border p-4">
                                    <legend className="px-1 text-sm font-medium">
                                        Flags comerciales (sobreviven al sync)
                                    </legend>
                                    <label className="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            name="is_b2b"
                                            value="1"
                                            defaultChecked={product.is_b2b}
                                        />{' '}
                                        Producto B2B
                                    </label>
                                    <label className="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            name="is_promo"
                                            value="1"
                                            defaultChecked={product.is_promo}
                                        />{' '}
                                        Promoción de landing
                                    </label>
                                    <div className="grid gap-2 sm:max-w-xs">
                                        <Label htmlFor="old_price">
                                            Precio anterior (centavos, para
                                            tachar)
                                        </Label>
                                        <Input
                                            id="old_price"
                                            name="old_price"
                                            type="number"
                                            min={0}
                                            defaultValue={
                                                product.old_price ?? ''
                                            }
                                        />
                                        <InputError
                                            message={errors.old_price}
                                        />
                                    </div>
                                </fieldset>
                                <Button type="submit" disabled={processing}>
                                    Guardar
                                </Button>
                            </>
                        )}
                    </Form>

                    <aside className="space-y-4">
                        {product.thumbnail && (
                            <img
                                src={product.thumbnail}
                                alt={product.title}
                                className="w-full rounded-xl border object-cover"
                            />
                        )}
                        <dl className="rounded-xl border p-4 text-sm">
                            <dt className="mb-2 font-medium">
                                Datos del ERP (solo lectura)
                            </dt>
                            {erpFields.map((field) => (
                                <div
                                    key={field}
                                    className="flex justify-between gap-2 border-t py-1"
                                >
                                    <span className="text-muted-foreground">
                                        {field}
                                    </span>
                                    <span className="text-right">
                                        {product.metadata[field] ?? '—'}
                                    </span>
                                </div>
                            ))}
                            <div className="flex justify-between gap-2 border-t py-1">
                                <span className="text-muted-foreground">
                                    Peso (kg)
                                </span>
                                <span>{product.weight_kg ?? '—'}</span>
                            </div>
                            <div className="flex justify-between gap-2 border-t py-1">
                                <span className="text-muted-foreground">
                                    Handle
                                </span>
                                <span className="font-mono text-xs">
                                    {product.handle}
                                </span>
                            </div>
                        </dl>
                    </aside>
                </div>
            </div>
        </>
    );
}

ProductEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Productos', href: index() },
        { title: 'Editar', href: '#' },
    ],
};

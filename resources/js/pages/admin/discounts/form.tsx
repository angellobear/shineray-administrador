import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import DiscountController from '@/actions/App/Http/Controllers/Admin/DiscountController';
import { NativeSelect } from '@/components/admin/native-select';
import { PageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { DiscountRow } from '@/pages/admin/discounts/index';
import { dashboard } from '@/routes';
import { index } from '@/routes/admin/discounts';

export default function DiscountForm({
    discount,
    types,
}: {
    discount: DiscountRow | null;
    types: { value: string; label: string }[];
}) {
    const [type, setType] = useState(discount?.type ?? 'percentage');
    const formProps = discount
        ? DiscountController.update.form(discount.id)
        : DiscountController.store.form();

    return (
        <>
            <Head title={discount ? `Cupón ${discount.code}` : 'Nuevo cupón'} />
            <div className="flex flex-col gap-6 p-4">
                <PageHeader
                    title={discount ? `Cupón ${discount.code}` : 'Nuevo cupón'}
                />
                <Form {...formProps} className="max-w-lg space-y-5">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="code">Código</Label>
                                <Input
                                    id="code"
                                    name="code"
                                    defaultValue={discount?.code ?? ''}
                                    required
                                    className="uppercase"
                                />
                                <InputError message={errors.code} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="type">Tipo</Label>
                                <NativeSelect
                                    id="type"
                                    name="type"
                                    value={type}
                                    onChange={(e) => setType(e.target.value)}
                                >
                                    {types.map((t) => (
                                        <option key={t.value} value={t.value}>
                                            {t.label}
                                        </option>
                                    ))}
                                </NativeSelect>
                                <InputError message={errors.type} />
                            </div>
                            {type !== 'free_shipping' && (
                                <div className="grid gap-2">
                                    <Label htmlFor="value">
                                        {type === 'percentage'
                                            ? 'Porcentaje (0-100)'
                                            : 'Monto en centavos (ej. 500 = $5.00)'}
                                    </Label>
                                    <Input
                                        id="value"
                                        name="value"
                                        type="number"
                                        min={0}
                                        max={
                                            type === 'percentage'
                                                ? 100
                                                : undefined
                                        }
                                        defaultValue={discount?.value ?? ''}
                                        required
                                    />
                                    <InputError message={errors.value} />
                                </div>
                            )}
                            <div className="grid gap-2 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="starts_at">Desde</Label>
                                    <Input
                                        id="starts_at"
                                        name="starts_at"
                                        type="date"
                                        defaultValue={discount?.starts_at ?? ''}
                                    />
                                    <InputError message={errors.starts_at} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="ends_at">Hasta</Label>
                                    <Input
                                        id="ends_at"
                                        name="ends_at"
                                        type="date"
                                        defaultValue={discount?.ends_at ?? ''}
                                    />
                                    <InputError message={errors.ends_at} />
                                </div>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="usage_limit">
                                    Límite de usos (vacío = ilimitado)
                                </Label>
                                <Input
                                    id="usage_limit"
                                    name="usage_limit"
                                    type="number"
                                    min={1}
                                    defaultValue={discount?.usage_limit ?? ''}
                                />
                                <InputError message={errors.usage_limit} />
                            </div>
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    name="is_active"
                                    value="1"
                                    defaultChecked={discount?.is_active ?? true}
                                />{' '}
                                Activo
                            </label>
                            <Button type="submit" disabled={processing}>
                                {discount ? 'Guardar cambios' : 'Crear cupón'}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

DiscountForm.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Cupones', href: index() },
        { title: 'Formulario', href: '#' },
    ],
};

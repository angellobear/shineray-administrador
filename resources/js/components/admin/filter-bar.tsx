import { router } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { Button } from '@/components/ui/button';

/**
 * Formulario GET de filtros: serializa los campos y navega con Inertia
 * preservando el estado de la página.
 */
export function FilterBar({
    action,
    children,
}: {
    action: string;
    children: ReactNode;
}) {
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const data: Record<string, string> = {};

        new FormData(event.currentTarget).forEach((value, key) => {
            if (typeof value === 'string' && value !== '') {
                data[key] = value;
            }
        });

        router.get(action, data, { preserveState: true, replace: true });
    }

    return (
        <form onSubmit={submit} className="flex flex-wrap items-end gap-3">
            {children}
            <Button type="submit" variant="secondary">
                Filtrar
            </Button>
            <Button
                type="button"
                variant="ghost"
                onClick={() => router.get(action)}
            >
                Limpiar
            </Button>
        </form>
    );
}

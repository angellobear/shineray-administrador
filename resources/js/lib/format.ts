const currency = new Intl.NumberFormat('es-EC', {
    style: 'currency',
    currency: 'USD',
});
const dateTime = new Intl.DateTimeFormat('es-EC', {
    dateStyle: 'short',
    timeStyle: 'short',
});
const dateOnly = new Intl.DateTimeFormat('es-EC', { dateStyle: 'medium' });

/** Centavos → "$12,34". Todos los montos del backend vienen en centavos. */
export function money(cents: number | null | undefined): string {
    return currency.format((cents ?? 0) / 100);
}

export function formatDateTime(value: string | null | undefined): string {
    return value ? dateTime.format(new Date(value)) : '—';
}

export function formatDate(value: string | null | undefined): string {
    return value ? dateOnly.format(new Date(value)) : '—';
}

export const orderStatusLabels: Record<string, string> = {
    pending: 'Pendiente',
    paid: 'Pagada',
    fulfilled: 'Enviada',
    canceled: 'Cancelada',
    refunded: 'Reembolsada',
};

export const syncStatusLabels: Record<string, string> = {
    running: 'En curso',
    succeeded: 'OK',
    failed: 'Falló',
    skipped: 'Omitida',
};

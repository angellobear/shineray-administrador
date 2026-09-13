import { Badge } from '@/components/ui/badge';

const tones: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    paid: 'default',
    fulfilled: 'default',
    succeeded: 'default',
    created: 'default',
    authorized: 'default',
    published: 'default',
    pending: 'secondary',
    running: 'secondary',
    draft: 'outline',
    skipped: 'outline',
    canceled: 'destructive',
    refunded: 'destructive',
    failed: 'destructive',
    error: 'destructive',
};

export function StatusBadge({
    status,
    label,
}: {
    status: string;
    label?: string;
}) {
    return (
        <Badge variant={tones[status] ?? 'outline'}>{label ?? status}</Badge>
    );
}

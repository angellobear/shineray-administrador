import type { ReactNode } from 'react';
import Heading from '@/components/heading';

export function PageHeader({
    title,
    description,
    actions,
}: {
    title: string;
    description?: string;
    actions?: ReactNode;
}) {
    return (
        <div className="flex flex-wrap items-start justify-between gap-4">
            <Heading title={title} description={description} />
            {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
        </div>
    );
}

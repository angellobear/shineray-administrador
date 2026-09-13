import type { SelectHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

/** Select nativo para filtros GET (se serializa con FormData sin JS extra). */
export function NativeSelect({
    className,
    ...props
}: SelectHTMLAttributes<HTMLSelectElement>) {
    return (
        <select
            className={cn(
                'border-input focus-visible:ring-ring/50 h-9 rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px]',
                className,
            )}
            {...props}
        />
    );
}

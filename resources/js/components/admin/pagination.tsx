import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types/admin';

export function Pagination({ paginator }: { paginator: Paginated<unknown> }) {
    if (paginator.last_page <= 1) {
        return null;
    }

    return (
        <nav
            className="flex flex-wrap items-center justify-between gap-2 text-sm"
            aria-label="Paginación"
        >
            <span className="text-muted-foreground">
                {paginator.from ?? 0}–{paginator.to ?? 0} de {paginator.total}
            </span>
            <ul className="flex flex-wrap gap-1">
                {paginator.links.map((link, index) => (
                    <li key={index}>
                        {link.url ? (
                            <Link
                                href={link.url}
                                preserveScroll
                                preserveState
                                className={cn(
                                    'inline-block rounded-md border px-2.5 py-1',
                                    link.active
                                        ? 'bg-primary text-primary-foreground'
                                        : 'hover:bg-muted',
                                )}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ) : (
                            <span
                                className="text-muted-foreground inline-block rounded-md border px-2.5 py-1 opacity-50"
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        )}
                    </li>
                ))}
            </ul>
        </nav>
    );
}

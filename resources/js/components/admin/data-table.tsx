import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type Column<T> = {
    key: string;
    header: string;
    className?: string;
    render: (row: T) => ReactNode;
};

export function DataTable<T extends { id: number | string }>({
    columns,
    rows,
    emptyMessage = 'Sin resultados.',
}: {
    columns: Column<T>[];
    rows: T[];
    emptyMessage?: string;
}) {
    return (
        <div className="overflow-x-auto rounded-xl border">
            <table className="w-full text-sm">
                <thead className="bg-muted/50 text-muted-foreground text-left text-xs tracking-wide uppercase">
                    <tr>
                        {columns.map((column) => (
                            <th
                                key={column.key}
                                className={cn(
                                    'px-3 py-2 font-medium',
                                    column.className,
                                )}
                            >
                                {column.header}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.length === 0 && (
                        <tr>
                            <td
                                colSpan={columns.length}
                                className="text-muted-foreground px-3 py-8 text-center"
                            >
                                {emptyMessage}
                            </td>
                        </tr>
                    )}
                    {rows.map((row) => (
                        <tr key={row.id} className="hover:bg-muted/30 border-t">
                            {columns.map((column) => (
                                <td
                                    key={column.key}
                                    className={cn(
                                        'px-3 py-2 align-top',
                                        column.className,
                                    )}
                                >
                                    {column.render(row)}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

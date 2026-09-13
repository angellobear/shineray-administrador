import { Head, Link } from '@inertiajs/react';
import { StatusBadge } from '@/components/admin/status-badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    formatDateTime,
    money,
    orderStatusLabels,
    syncStatusLabels,
} from '@/lib/format';
import { dashboard } from '@/routes';
import { show as orderShow } from '@/routes/admin/orders';
import { index as syncIndex } from '@/routes/admin/sync';

type Stats = {
    orders_today: number;
    revenue_today: number;
    orders_month: number;
    revenue_month: number;
    pending_orders: number;
    published_products: number;
    active_carts: number;
    integration_errors_24h: number;
};

type RecentOrder = {
    order_number: string;
    email: string;
    status: string;
    total: number;
    created_at: string | null;
};
type SyncRun = {
    id: number;
    type: string;
    status: string;
    started_at: string;
    fetched_count: number;
};

export default function Dashboard({
    stats,
    recentOrders,
    lastSyncRuns,
}: {
    stats: Stats;
    recentOrders: RecentOrder[];
    lastSyncRuns: SyncRun[];
}) {
    const tiles = [
        {
            label: 'Ventas hoy',
            value: money(stats.revenue_today),
            hint: `${stats.orders_today} órdenes`,
        },
        {
            label: 'Ventas del mes',
            value: money(stats.revenue_month),
            hint: `${stats.orders_month} órdenes`,
        },
        {
            label: 'Órdenes pendientes de pago',
            value: String(stats.pending_orders),
            hint: 'Checkout iniciado sin pago',
        },
        {
            label: 'Productos publicados',
            value: String(stats.published_products),
            hint: `${stats.active_carts} carritos activos`,
        },
        {
            label: 'Errores de integración (24 h)',
            value: String(stats.integration_errors_24h),
            hint: 'Datafast, DeUna, Servientrega, ERP',
        },
    ];

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-col gap-6 p-4">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    {tiles.map((tile) => (
                        <Card key={tile.label}>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-muted-foreground text-sm font-medium">
                                    {tile.label}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-2xl font-semibold">
                                    {tile.value}
                                </p>
                                <p className="text-muted-foreground text-xs">
                                    {tile.hint}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Últimas órdenes</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="divide-y text-sm">
                                {recentOrders.length === 0 && (
                                    <li className="text-muted-foreground py-2">
                                        Todavía no hay órdenes.
                                    </li>
                                )}
                                {recentOrders.map((order) => (
                                    <li
                                        key={order.order_number}
                                        className="flex flex-wrap items-center justify-between gap-2 py-2"
                                    >
                                        <Link
                                            href={orderShow(order.order_number)}
                                            className="font-medium hover:underline"
                                        >
                                            {order.order_number}
                                        </Link>
                                        <span className="text-muted-foreground">
                                            {order.email}
                                        </span>
                                        <StatusBadge
                                            status={order.status}
                                            label={
                                                orderStatusLabels[order.status]
                                            }
                                        />
                                        <span>{money(order.total)}</span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>
                                <Link
                                    href={syncIndex()}
                                    className="hover:underline"
                                >
                                    Sincronizaciones ERP
                                </Link>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="divide-y text-sm">
                                {lastSyncRuns.length === 0 && (
                                    <li className="text-muted-foreground py-2">
                                        Sin corridas registradas.
                                    </li>
                                )}
                                {lastSyncRuns.map((run) => (
                                    <li
                                        key={run.id}
                                        className="flex items-center justify-between gap-2 py-2"
                                    >
                                        <span>{run.type}</span>
                                        <StatusBadge
                                            status={run.status}
                                            label={syncStatusLabels[run.status]}
                                        />
                                        <span className="text-muted-foreground text-xs">
                                            {formatDateTime(run.started_at)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};

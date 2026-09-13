import { Link } from '@inertiajs/react';
import {
    BadgePercent,
    Boxes,
    Building2,
    ClipboardList,
    LayoutGrid,
    RefreshCw,
    ShoppingBasket,
    ShoppingCart,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as abandonedCarts } from '@/routes/admin/abandoned-carts';
import { clients as b2bClients } from '@/routes/admin/b2b';
import { index as customers } from '@/routes/admin/customers';
import { index as discounts } from '@/routes/admin/discounts';
import { index as logs } from '@/routes/admin/logs';
import { index as orders } from '@/routes/admin/orders';
import { index as products } from '@/routes/admin/products';
import { index as sync } from '@/routes/admin/sync';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
    { title: 'Órdenes', href: orders(), icon: ShoppingCart },
    { title: 'Productos', href: products(), icon: Boxes },
    { title: 'Clientes', href: customers(), icon: Users },
    { title: 'B2B', href: b2bClients(), icon: Building2 },
    {
        title: 'Carritos abandonados',
        href: abandonedCarts(),
        icon: ShoppingBasket,
    },
    { title: 'Cupones', href: discounts(), icon: BadgePercent },
    { title: 'Sincronización ERP', href: sync(), icon: RefreshCw },
    { title: 'Bitácora integraciones', href: logs(), icon: ClipboardList },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

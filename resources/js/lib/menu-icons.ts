import {
    BookOpen,
    Boxes,
    Briefcase,
    Building2,
    Calendar,
    ChartColumn,
    CircleHelp,
    CreditCard,
    FileText,
    FolderOpen,
    Globe,
    Heart,
    House,
    Image,
    Info,
    KeyRound,
    LayoutGrid,
    LifeBuoy,
    Link,
    ListTree,
    Mail,
    MapPin,
    MessageSquare,
    Newspaper,
    Package,
    Phone,
    Plug,
    Receipt,
    Settings,
    ShieldCheck,
    ShoppingCart,
    Star,
    Store,
    Tag,
    Truck,
    Users,
    Wrench,
    Bell,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

/**
 * Icon name (stored in the `menus.icon` column) → lucide component.
 *
 * MUST have the same keys as App\Support\MenuIcons::NAMES
 * (checked by tests/Feature/Admin/MenuManagementTest.php).
 * Importing only these icons keeps the bundle small.
 */
export const MENU_ICONS: Record<string, LucideIcon> = {
    LayoutGrid,
    House,
    Package,
    Boxes,
    FolderOpen,
    Tag,
    Store,
    ShoppingCart,
    Receipt,
    CreditCard,
    Truck,
    ChartColumn,
    Users,
    ShieldCheck,
    KeyRound,
    ListTree,
    Settings,
    Wrench,
    Bell,
    Mail,
    Phone,
    MessageSquare,
    Info,
    CircleHelp,
    LifeBuoy,
    FileText,
    BookOpen,
    Newspaper,
    Image,
    Calendar,
    Globe,
    Link,
    Plug,
    MapPin,
    Building2,
    Briefcase,
    Star,
    Heart,
};

/** Unknown or empty names return null (the item renders without an icon). */
export function menuIcon(name: string | null | undefined): LucideIcon | null {
    return name ? (MENU_ICONS[name] ?? null) : null;
}

/** Internal path (Inertia <Link>) vs external URL / anchor (plain <a>). */
export function isInternalUrl(url: string): boolean {
    return url.startsWith('/') && !url.startsWith('//') && !url.includes('#');
}

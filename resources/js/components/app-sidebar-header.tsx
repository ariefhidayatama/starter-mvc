import { Breadcrumbs } from '@/components/breadcrumbs';
import { ThemeToggle } from '@/components/theme-toggle';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        // sticky + backdrop-blur: header tetap terlihat saat halaman di-scroll.
        <header className="sticky top-0 z-20 flex h-16 shrink-0 items-center justify-between gap-2 rounded-t-xl border-b border-border/60 bg-background/80 px-4 backdrop-blur-md transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-6">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Separator orientation="vertical" className="mr-1 h-4!" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <ThemeToggle />
        </header>
    );
}

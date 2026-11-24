import { ChevronRight } from 'lucide-react';
import { MenuLink } from '@/components/menu-link';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { menuIcon } from '@/lib/menu-icons';
import type { MenuNode } from '@/types';

/**
 * Sidebar menu built from the database (Admin → Menus).
 *
 * - Item without submenu → a link.
 * - Item with submenu    → collapsible group (opened automatically when
 *   one of its pages is active). When the sidebar is collapsed to icons,
 *   the submenu opens as a dropdown to the right instead.
 *
 * Items are already filtered by permission on the server.
 */
export function NavMain({ items }: { items: MenuNode[] }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { state, isMobile } = useSidebar();
    const iconOnly = state === 'collapsed' && !isMobile;

    const isActive = (node: MenuNode) =>
        node.url !== null && isInternalPath(node.url)
            ? isCurrentOrParentUrl(node.url)
            : false;

    if (items.length === 0) {
        return null;
    }

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarMenu>
                {items.map((item) => {
                    const Icon = menuIcon(item.icon);

                    if (item.children.length === 0) {
                        return item.url === null ? null : (
                            <SidebarMenuItem key={item.id}>
                                <SidebarMenuButton
                                    asChild
                                    isActive={isActive(item)}
                                    tooltip={{ children: item.title }}
                                >
                                    <MenuLink
                                        url={item.url}
                                        newTab={item.open_in_new_tab}
                                    >
                                        {Icon && <Icon />}
                                        <span>{item.title}</span>
                                    </MenuLink>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        );
                    }

                    // A parent that has its own URL shows it as the first submenu.
                    const children: MenuNode[] =
                        item.url !== null
                            ? [{ ...item, children: [] }, ...item.children]
                            : item.children;
                    const childActive = children.some(isActive);

                    if (iconOnly) {
                        return (
                            <SidebarMenuItem key={item.id}>
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <SidebarMenuButton
                                            isActive={childActive}
                                            tooltip={{ children: item.title }}
                                        >
                                            {Icon && <Icon />}
                                            <span>{item.title}</span>
                                        </SidebarMenuButton>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        side="right"
                                        align="start"
                                        className="w-48"
                                    >
                                        <DropdownMenuLabel className="text-xs text-muted-foreground">
                                            {item.title}
                                        </DropdownMenuLabel>
                                        {children.map((child) => (
                                            <DropdownMenuItem
                                                key={child.id}
                                                asChild
                                            >
                                                <MenuLink
                                                    url={child.url ?? '#'}
                                                    newTab={
                                                        child.open_in_new_tab
                                                    }
                                                    className="cursor-pointer"
                                                >
                                                    {child.title}
                                                </MenuLink>
                                            </DropdownMenuItem>
                                        ))}
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </SidebarMenuItem>
                        );
                    }

                    return (
                        <Collapsible
                            key={item.id}
                            asChild
                            defaultOpen={childActive}
                            className="group/collapsible"
                        >
                            <SidebarMenuItem>
                                <CollapsibleTrigger asChild>
                                    <SidebarMenuButton
                                        tooltip={{ children: item.title }}
                                    >
                                        {Icon && <Icon />}
                                        <span>{item.title}</span>
                                        <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                                    </SidebarMenuButton>
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <SidebarMenuSub>
                                        {children.map((child) => {
                                            const ChildIcon = menuIcon(
                                                child.icon,
                                            );

                                            return (
                                                <SidebarMenuSubItem
                                                    key={child.id}
                                                >
                                                    <SidebarMenuSubButton
                                                        asChild
                                                        isActive={isActive(
                                                            child,
                                                        )}
                                                    >
                                                        <MenuLink
                                                            url={
                                                                child.url ?? '#'
                                                            }
                                                            newTab={
                                                                child.open_in_new_tab
                                                            }
                                                        >
                                                            {ChildIcon && (
                                                                <ChildIcon />
                                                            )}
                                                            <span>
                                                                {child.title}
                                                            </span>
                                                        </MenuLink>
                                                    </SidebarMenuSubButton>
                                                </SidebarMenuSubItem>
                                            );
                                        })}
                                    </SidebarMenuSub>
                                </CollapsibleContent>
                            </SidebarMenuItem>
                        </Collapsible>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}

/** Only internal paths can be "the current page". */
function isInternalPath(url: string): boolean {
    return url.startsWith('/') && !url.startsWith('//');
}

import { ArrowUpRight, Menu as MenuIcon } from 'lucide-react';
import { Fragment } from 'react';
import { MenuLink } from '@/components/menu-link';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    NavigationMenu,
    NavigationMenuContent,
    NavigationMenuItem,
    NavigationMenuLink,
    NavigationMenuList,
    NavigationMenuTrigger,
    navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';
import { cn } from '@/lib/utils';
import type { MenuNode } from '@/types';

/**
 * Public website navbar built from the database (Admin → Menus → Website).
 *
 * Desktop: links + dropdowns for submenus. Mobile: one "☰" dropdown.
 */
export function SiteNav({ items }: { items: MenuNode[] }) {
    if (items.length === 0) {
        return null;
    }

    return (
        <>
            <NavigationMenu viewport={false} className="hidden md:flex">
                <NavigationMenuList>
                    {items.map((item) =>
                        item.children.length === 0 ? (
                            item.url !== null && (
                                <NavigationMenuItem key={item.id}>
                                    <NavigationMenuLink
                                        asChild
                                        className={cn(
                                            navigationMenuTriggerStyle(),
                                            'bg-transparent',
                                        )}
                                    >
                                        <MenuLink
                                            url={item.url}
                                            newTab={item.open_in_new_tab}
                                        >
                                            {item.title}
                                        </MenuLink>
                                    </NavigationMenuLink>
                                </NavigationMenuItem>
                            )
                        ) : (
                            <NavigationMenuItem key={item.id}>
                                <NavigationMenuTrigger className="bg-transparent">
                                    {item.title}
                                </NavigationMenuTrigger>
                                <NavigationMenuContent>
                                    <ul className="grid w-56 gap-0.5">
                                        {withParentLink(item).map((child) => (
                                            <li key={child.id}>
                                                <NavigationMenuLink asChild>
                                                    <MenuLink
                                                        url={child.url ?? '#'}
                                                        newTab={
                                                            child.open_in_new_tab
                                                        }
                                                        className="flex-row items-center justify-between"
                                                    >
                                                        {child.title}
                                                        {child.open_in_new_tab && (
                                                            <ArrowUpRight className="size-3.5" />
                                                        )}
                                                    </MenuLink>
                                                </NavigationMenuLink>
                                            </li>
                                        ))}
                                    </ul>
                                </NavigationMenuContent>
                            </NavigationMenuItem>
                        ),
                    )}
                </NavigationMenuList>
            </NavigationMenu>

            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="md:hidden"
                        aria-label="Open menu"
                    >
                        <MenuIcon />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-60">
                    {items.map((item, i) =>
                        item.children.length === 0 ? (
                            item.url !== null && (
                                <DropdownMenuItem key={item.id} asChild>
                                    <MenuLink
                                        url={item.url}
                                        newTab={item.open_in_new_tab}
                                        className="cursor-pointer"
                                    >
                                        {item.title}
                                    </MenuLink>
                                </DropdownMenuItem>
                            )
                        ) : (
                            <Fragment key={item.id}>
                                {i > 0 && <DropdownMenuSeparator />}
                                <DropdownMenuLabel className="text-xs text-muted-foreground">
                                    {item.title}
                                </DropdownMenuLabel>
                                {withParentLink(item).map((child) => (
                                    <DropdownMenuItem key={child.id} asChild>
                                        <MenuLink
                                            url={child.url ?? '#'}
                                            newTab={child.open_in_new_tab}
                                            className="cursor-pointer justify-between"
                                        >
                                            {child.title}
                                            {child.open_in_new_tab && (
                                                <ArrowUpRight className="size-3.5" />
                                            )}
                                        </MenuLink>
                                    </DropdownMenuItem>
                                ))}
                            </Fragment>
                        ),
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
        </>
    );
}

/** A parent that has its own URL is listed as the first submenu entry. */
function withParentLink(item: MenuNode): MenuNode[] {
    return item.url !== null
        ? [{ ...item, children: [] }, ...item.children]
        : item.children;
}

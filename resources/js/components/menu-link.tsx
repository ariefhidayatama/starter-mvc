import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import { isInternalUrl } from '@/lib/menu-icons';

/**
 * Link for a menu item stored in the database:
 * - internal path (/admin/products) → Inertia <Link> (no full page reload)
 * - external URL or anchor (https://..., /#features) → plain <a>
 */
export function MenuLink({
    url,
    newTab = false,
    ...props
}: Omit<ComponentProps<'a'>, 'href'> & { url: string; newTab?: boolean }) {
    if (isInternalUrl(url) && !newTab) {
        // Props come from Radix `asChild` (className, onClick, data-*),
        // typed for <a>; Inertia's Link renders an <a> with the same props.
        return (
            <Link
                href={url}
                prefetch="click"
                {...(props as Omit<InertiaLinkProps, 'href'>)}
            />
        );
    }

    return (
        <a
            href={url}
            {...(newTab
                ? { target: '_blank', rel: 'noopener noreferrer' }
                : {})}
            {...props}
        />
    );
}

import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

type QueryValue = string | number | null | undefined | QueryObject;
type QueryObject = { [key: string]: QueryValue };

/** Delay after the last keystroke before the search is sent. */
const SEARCH_DEBOUNCE_MS = 500;

/** Drop empty values so the URL stays clean (?search=&role= → nothing). */
function clean(query: QueryObject): QueryObject {
    const result: QueryObject = {};

    for (const [key, value] of Object.entries(query)) {
        if (value !== null && typeof value === 'object') {
            const nested = clean(value);

            if (Object.keys(nested).length > 0) {
                result[key] = nested;
            }
        } else if (value !== '' && value !== null && value !== undefined) {
            result[key] = value;
        }
    }

    return result;
}

/**
 * Keeps a server-side table (search, filters, sort, per page) in sync
 * with the server. The values are sent as a query string; on pages with the
 * "remember.table" middleware the server stores them in the session and
 * the address bar stays clean (e.g. /admin/products).
 *
 * - Every change triggers an Inertia partial reload (`only`), so only the
 *   table data is fetched again, not the whole page.
 * - The search box is debounced (SEARCH_DEBOUNCE_MS) and skipped when the
 *   trimmed text equals the search already applied.
 * - `page` is never carried over, so any change goes back to page 1.
 *
 * @param url     Page URL, e.g. admin.products.index.url()
 * @param filters Current filters as sent by the controller (`filters` prop)
 * @param only    Props to reload, e.g. ['products', 'filters']
 */
export function useTableQuery<
    TFilters extends QueryObject & { search?: string },
>(url: string, filters: TFilters, only: string[]) {
    const [search, setSearch] = useState(filters.search ?? '');
    const firstRender = useRef(true);

    const apply = (next: Partial<TFilters>) => {
        router.get(url, clean({ ...filters, search: search.trim(), ...next }), {
            only,
            preserveState: true,
            preserveScroll: true,
            replace: true,
            // Pages with the "remember.table" middleware keep these filters
            // in the session and show a clean URL (no query string). The
            // header marks them as the user's choice, even when all are
            // cleared (an empty query would otherwise restore the old ones).
            headers: { 'X-Table-Query': '1' },
        });
    };

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const query = search.trim();

        // Nothing new to fetch (e.g. only a trailing space was typed, or the
        // text was changed back to what the table already shows).
        if (query === (filters.search ?? '').trim()) {
            return;
        }

        // Wait until the user stops typing, so "coco" is one request instead
        // of several that cancel each other.
        const timer = setTimeout(
            () => apply({ search: query } as Partial<TFilters>),
            SEARCH_DEBOUNCE_MS,
        );

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    return { search, setSearch, apply };
}

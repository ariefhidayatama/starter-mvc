import { Link, router } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

/** Must match the whitelist in the Form Request (e.g. IndexUserRequest). */
export const PER_PAGE_OPTIONS = [10, 25, 50, 100] as const;

/**
 * Pagination for a Laravel paginate() result.
 * Page links already carry the filter query string (withQueryString()),
 * except on "remember.table" pages: there they are just ?page=N and the
 * filters come from the session.
 *
 * Pass `onPerPageChange` to show the "Rows per page" selector.
 */
export function Pagination<T>({
    page,
    onPerPageChange,
}: {
    page: Paginated<T>;
    onPerPageChange?: (perPage: number) => void;
}) {
    return (
        <div className="flex flex-col items-center justify-between gap-3 sm:flex-row">
            <div className="flex flex-wrap items-center gap-x-6 gap-y-2">
                {onPerPageChange && (
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <span>Rows per page</span>
                        <Select
                            value={String(page.per_page)}
                            onValueChange={(v) => onPerPageChange(Number(v))}
                        >
                            <SelectTrigger
                                className="h-8 w-[72px] rounded-lg"
                                aria-label="Rows per page"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            {/* side="top": this control sits in the table footer,
                                so the list opens upwards instead of being clipped. */}
                            <SelectContent
                                side="top"
                                align="start"
                                className="min-w-[72px]"
                            >
                                {PER_PAGE_OPTIONS.map((n) => (
                                    <SelectItem key={n} value={String(n)}>
                                        {n}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                )}

                <p className="text-sm text-muted-foreground">
                    {page.total === 0 ? (
                        'No results'
                    ) : (
                        <>
                            Showing{' '}
                            <span className="font-medium text-foreground">
                                {page.from}–{page.to}
                            </span>{' '}
                            of{' '}
                            <span className="font-medium text-foreground">
                                {page.total}
                            </span>
                        </>
                    )}
                </p>
            </div>

            {page.last_page > 1 && (
                <PageNav key={page.current_page} page={page} />
            )}
        </div>
    );
}

/**
 * URL for page N, based on the page-1 link from Laravel so the query string
 * (filters, custom page name) is kept. The page parameter is the one that is
 * "1" in the first-page link and last_page in the last-page link.
 */
function pageUrlBuilder<T>(page: Paginated<T>): (n: number) => string | null {
    const first = page.links[1]?.url;
    const last = page.links[page.links.length - 2]?.url;

    if (!first || !last) {
        return () => null;
    }

    const firstUrl = new URL(first, window.location.origin);
    const lastUrl = new URL(last, window.location.origin);
    const key =
        [...firstUrl.searchParams.keys()].find(
            (k) =>
                firstUrl.searchParams.get(k) === '1' &&
                lastUrl.searchParams.get(k) === String(page.last_page),
        ) ?? 'page';

    return (n) => {
        const url = new URL(firstUrl);
        url.searchParams.set(key, String(n));

        return url.pathname + url.search;
    };
}

/** « ‹ [ 6 ] of 50 › » — compact navigation with a page-number input. */
function PageNav<T>({ page }: { page: Paginated<T> }) {
    const current = page.current_page;
    const lastPage = page.last_page;
    // Reset when the page changes: the parent remounts this via key={current_page}.
    const [value, setValue] = useState(String(current));

    const urlFor = pageUrlBuilder(page);

    const goTo = (n: number) => {
        const target = Math.min(Math.max(Math.trunc(n), 1), lastPage);
        const url = urlFor(target);
        setValue(String(target));

        if (target !== current && url) {
            router.get(url, {}, { preserveScroll: true, preserveState: true });
        }
    };

    const submit = () => {
        const n = Number(value);

        if (value.trim() === '' || Number.isNaN(n)) {
            setValue(String(current));
        } else {
            goTo(n);
        }
    };

    const button = (
        target: number,
        disabled: boolean,
        label: string,
        icon: ReactNode,
    ) => {
        const url = disabled ? null : urlFor(target);
        const className = cn(
            'flex size-8 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-muted hover:text-foreground',
            !url && 'pointer-events-none opacity-40',
        );

        return url ? (
            <Link
                href={url}
                preserveScroll
                preserveState
                className={className}
                aria-label={label}
            >
                {icon}
            </Link>
        ) : (
            <span className={className} aria-hidden="true">
                {icon}
            </span>
        );
    };

    return (
        <nav className="flex items-center gap-1" aria-label="Pagination">
            {button(
                1,
                current <= 1,
                'First page',
                <ChevronsLeft className="size-4" />,
            )}
            {button(
                current - 1,
                current <= 1,
                'Previous page',
                <ChevronLeft className="size-4" />,
            )}

            <div className="flex items-center gap-2 px-1 text-sm text-muted-foreground">
                <Input
                    type="text"
                    inputMode="numeric"
                    value={value}
                    onChange={(e) =>
                        setValue(e.target.value.replace(/\D/g, ''))
                    }
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            submit();
                        }
                    }}
                    onBlur={submit}
                    onFocus={(e) => e.target.select()}
                    className="h-8 w-14 rounded-lg px-2 text-center"
                    aria-label={`Page number, 1 to ${lastPage}`}
                />
                <span className="whitespace-nowrap">of {lastPage}</span>
            </div>

            {button(
                current + 1,
                current >= lastPage,
                'Next page',
                <ChevronRight className="size-4" />,
            )}
            {button(
                lastPage,
                current >= lastPage,
                'Last page',
                <ChevronsRight className="size-4" />,
            )}
        </nav>
    );
}

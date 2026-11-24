import {
    rowSortingFeature,
    tableFeatures,
    useTable,
} from '@tanstack/react-table';
import type {
    ColumnDef,
    RowData,
    SortingState,
    Updater,
} from '@tanstack/react-table';
import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';

/**
 * Features registered on every data table. Keep at module scope:
 * TanStack Table v9 requires stable `features`, `columns` and `data`.
 */
export const dataTableFeatures = tableFeatures({ rowSortingFeature });

export type DataTableColumn<TData extends RowData> = ColumnDef<
    typeof dataTableFeatures,
    TData,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any -- each column has its own value type
    any
>;

/** "-price" → [{ id: 'price', desc: true }] */
function toSorting(sort: string): SortingState {
    if (!sort) {
        return [];
    }

    return [{ id: sort.replace(/^-/, ''), desc: sort.startsWith('-') }];
}

/** [{ id: 'price', desc: true }] → "-price" */
function toSort(sorting: SortingState): string {
    const first = sorting[0];

    return first ? `${first.desc ? '-' : ''}${first.id}` : '';
}

/**
 * Server-side data table built on TanStack Table (headless) + shadcn/ui table.
 *
 * The SERVER sorts and paginates (`manualSorting`); TanStack only manages
 * sort state & header behaviour. Sorting is mirrored to the URL as
 * `?sort=-price` through `onSortChange`.
 *
 * A column is sortable when `enableSorting: true` AND its id matches a
 * sort key whitelisted by the backend (e.g. ProductQueryService::SORTS).
 */
export function DataTable<TData extends RowData>({
    columns,
    data,
    sort = '',
    onSortChange,
    getRowId,
    empty,
    columnClassNames = {},
    rowClassName,
}: {
    columns: DataTableColumn<TData>[];
    data: TData[];
    /** Omit both for a table without sortable columns. */
    sort?: string;
    onSortChange?: (sort: string) => void;
    getRowId?: (row: TData) => string;
    /** Rendered instead of the table when there are no rows. */
    empty?: ReactNode;
    /** Extra classes per column id, e.g. { actions: 'w-16 text-right' }. */
    columnClassNames?: Record<string, string>;
    /** Extra classes per row, e.g. to dim inactive rows. */
    rowClassName?: (row: TData) => string | undefined;
}) {
    const sorting = toSorting(sort);

    const table = useTable({
        features: dataTableFeatures,
        columns,
        data,
        getRowId: getRowId ? (row) => getRowId(row) : undefined,
        state: { sorting },
        onSortingChange: (updater: Updater<SortingState>) => {
            const next =
                typeof updater === 'function' ? updater(sorting) : updater;
            onSortChange?.(toSort(next));
        },
        manualSorting: true,
        // Columns are NOT sortable unless they opt in with
        // `enableSorting: true` (TanStack's default is sortable). Only
        // backend-whitelisted sort keys may be sent, or the server answers 422.
        defaultColumn: { enableSorting: false },
        enableSortingRemoval: false, // a sortable column toggles asc ↔ desc
        enableMultiSort: false,
    });

    if (data.length === 0 && empty) {
        return <>{empty}</>;
    }

    return (
        <Table>
            <TableHeader>
                {table.getHeaderGroups().map((group) => (
                    <TableRow key={group.id}>
                        {group.headers.map((header) => {
                            const column = header.column;
                            const sorted = column.getIsSorted();

                            return (
                                <TableHead
                                    key={header.id}
                                    className={columnClassNames[column.id]}
                                    aria-sort={
                                        sorted === 'asc'
                                            ? 'ascending'
                                            : sorted === 'desc'
                                              ? 'descending'
                                              : undefined
                                    }
                                >
                                    {header.isPlaceholder ? null : column.getCanSort() ? (
                                        <button
                                            type="button"
                                            onClick={column.getToggleSortingHandler()}
                                            className={cn(
                                                '-ml-2 inline-flex items-center gap-1 rounded-md px-2 py-1 uppercase transition-colors hover:bg-muted hover:text-foreground',
                                                sorted && 'text-foreground',
                                            )}
                                        >
                                            <table.FlexRender header={header} />
                                            {sorted === 'asc' ? (
                                                <ArrowUp className="size-3.5" />
                                            ) : sorted === 'desc' ? (
                                                <ArrowDown className="size-3.5" />
                                            ) : (
                                                <ChevronsUpDown className="size-3.5 opacity-50" />
                                            )}
                                        </button>
                                    ) : (
                                        <table.FlexRender header={header} />
                                    )}
                                </TableHead>
                            );
                        })}
                    </TableRow>
                ))}
            </TableHeader>
            <TableBody>
                {table.getRowModel().rows.map((row) => (
                    <TableRow
                        key={row.id}
                        className={rowClassName?.(row.original)}
                    >
                        {row.getAllCells().map((cell) => (
                            <TableCell
                                key={cell.id}
                                className={columnClassNames[cell.column.id]}
                            >
                                <table.FlexRender cell={cell} />
                            </TableCell>
                        ))}
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

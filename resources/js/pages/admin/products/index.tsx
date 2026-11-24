import { Head, Link, router, usePage } from '@inertiajs/react';
import type { CheckedState } from '@radix-ui/react-checkbox';
import { createColumnHelper } from '@tanstack/react-table';
import {
    createContext,
    useContext,
    useEffect,
    useMemo,
    useState,
} from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import {
    FileDown,
    FileSpreadsheet,
    FileText,
    Package,
    PackageX,
    Pencil,
    Plus,
    Search,
    Trash2,
} from 'lucide-react';
import ProductController from '@/actions/App/Http/Controllers/Admin/ProductController';
import {
    DataTable,
    dataTableFeatures,
} from '@/components/data-table/data-table';
import { ProductImportDialog } from '@/components/admin/product-import-dialog';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { RowActions } from '@/components/row-actions';
import type { RowAction } from '@/components/row-actions';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useCan } from '@/hooks/use-can';
import { useTableQuery } from '@/hooks/use-table-query';
import { formatCurrency } from '@/lib/format';
import admin from '@/routes/admin';
import type { Option, Paginated, Product, ProductFilters } from '@/types';

const ALL = 'all';

const RANGE_KEYS = ['price_min', 'price_max', 'stock_min', 'stock_max'] as const;
type RangeKey = (typeof RANGE_KEYS)[number];
type RangeValues = Record<RangeKey, string>;

function trimRange(range: RangeValues): RangeValues {
    return {
        price_min: range.price_min.trim(),
        price_max: range.price_max.trim(),
        stock_min: range.stock_min.trim(),
        stock_max: range.stock_max.trim(),
    };
}

/** "[min] – [max]" pair of number inputs with a leading label. */
function RangeInputs({
    label,
    min,
    max,
    step,
    onMinChange,
    onMaxChange,
}: {
    label: string;
    min: string;
    max: string;
    step: string;
    onMinChange: (value: string) => void;
    onMaxChange: (value: string) => void;
}) {
    return (
        <div className="flex items-center gap-2">
            <span className="w-10 shrink-0 text-sm text-muted-foreground sm:w-auto">
                {label}
            </span>
            <Input
                type="number"
                inputMode="numeric"
                min={0}
                step={step}
                value={min}
                onChange={(e) => onMinChange(e.target.value)}
                placeholder="Min"
                aria-label={`Minimum ${label.toLowerCase()}`}
                className="h-9 w-full rounded-lg sm:w-28"
            />
            <span className="text-muted-foreground">–</span>
            <Input
                type="number"
                inputMode="numeric"
                min={0}
                step={step}
                value={max}
                onChange={(e) => onMaxChange(e.target.value)}
                placeholder="Max"
                aria-label={`Maximum ${label.toLowerCase()}`}
                className="h-9 w-full rounded-lg sm:w-28"
            />
        </div>
    );
}

/** Row actions as a component, so it can use hooks (useCan). */
function ProductActions({ product }: { product: Product }) {
    const can = useCan();
    const actions: RowAction[] = [];

    if (can('products.update')) {
        actions.push({
            type: 'link',
            label: 'Edit',
            icon: Pencil,
            href: admin.products.edit(product.ulid),
        });
    }

    if (can('products.delete')) {
        actions.push({
            type: 'confirm',
            label: 'Delete',
            icon: Trash2,
            destructive: true,
            confirm: {
                title: `Delete ${product.name}?`,
                description:
                    'This product will be permanently deleted. This action cannot be undone.',
                confirmLabel: 'Delete',
            },
            onConfirm: (close) =>
                router.visit(ProductController.destroy(product.ulid), {
                    preserveScroll: true,
                    onFinish: close,
                }),
        });
    }

    return (
        <RowActions label={`Actions for ${product.name}`} actions={actions} />
    );
}

/**
 * Checklist state for bulk actions. Columns are module-scoped (TanStack v9
 * needs a stable reference), so the checkbox cells read the selection from
 * context instead of closing over component state.
 */
type Selection = {
    enabled: boolean;
    selected: Set<string>;
    pageIds: string[];
    toggle: (ulid: string, checked: boolean) => void;
    togglePage: (checked: boolean) => void;
};

const SelectionContext = createContext<Selection | null>(null);

function useSelection(): Selection {
    const ctx = useContext(SelectionContext);

    if (!ctx) {
        throw new Error('useSelection must be used inside SelectionContext');
    }

    return ctx;
}

function SelectPageCheckbox() {
    const { enabled, selected, pageIds, togglePage } = useSelection();
    const count = pageIds.filter((id) => selected.has(id)).length;
    const state: CheckedState =
        count === 0 ? false : count === pageIds.length ? true : 'indeterminate';

    if (!enabled) {
        return null;
    }

    return (
        <Checkbox
            checked={state}
            onCheckedChange={(v) => togglePage(v === true)}
            aria-label="Select all products on this page"
            className="translate-y-0.5"
        />
    );
}

function SelectRowCheckbox({ product }: { product: Product }) {
    const { enabled, selected, toggle } = useSelection();

    if (!enabled) {
        return null;
    }

    return (
        <Checkbox
            checked={selected.has(product.ulid)}
            onCheckedChange={(v) => toggle(product.ulid, v === true)}
            aria-label={`Select ${product.name}`}
            className="translate-y-0.5"
        />
    );
}

/**
 * Columns live at module scope (stable reference, required by TanStack v9).
 * Column ids that are sortable must match ProductQueryService::SORTS.
 */
const column = createColumnHelper<typeof dataTableFeatures, Product>();

const columns = column.columns([
    column.display({
        id: 'select',
        header: () => <SelectPageCheckbox />,
        cell: ({ row }) => <SelectRowCheckbox product={row.original} />,
    }),
    // First column: the row's actions are the first thing the eye lands on.
    column.display({
        id: 'actions',
        header: () => <span className="sr-only">Actions</span>,
        cell: ({ row }) => <ProductActions product={row.original} />,
    }),
    column.accessor('name', {
        header: 'Product',
        enableSorting: true,
        cell: ({ row }) => (
            <div className="min-w-0">
                <p className="truncate font-medium">{row.original.name}</p>
                <p className="font-mono text-xs text-muted-foreground">
                    {row.original.sku}
                </p>
            </div>
        ),
    }),
    column.display({
        id: 'category',
        header: 'Category',
        cell: ({ row }) => (
            <span className="text-muted-foreground">
                {row.original.category?.name ?? '-'}
            </span>
        ),
    }),
    column.accessor('price', {
        header: 'Price',
        enableSorting: true,
        cell: ({ row }) => (
            <span className="font-medium tabular-nums">
                {formatCurrency(row.original.price)}
            </span>
        ),
    }),
    column.accessor('stock', {
        header: 'Stock',
        enableSorting: true,
        cell: ({ row }) =>
            row.original.stock > 0 ? (
                <span className="tabular-nums">{row.original.stock}</span>
            ) : (
                <StatusBadge tone="warning" dot={false}>
                    Out of stock
                </StatusBadge>
            ),
    }),
    column.accessor('is_active', {
        header: 'Status',
        cell: ({ row }) => (
            <StatusBadge tone={row.original.is_active ? 'success' : 'neutral'}>
                {row.original.is_active ? 'Active' : 'Inactive'}
            </StatusBadge>
        ),
    }),
]);

export default function ProductsIndex({
    products,
    filters,
    categoryOptions,
}: {
    products: Paginated<Product>;
    filters: ProductFilters;
    categoryOptions: Option[];
}) {
    const can = useCan();
    const { search, setSearch, apply } = useTableQuery(
        admin.products.index.url(),
        filters,
        ['products', 'filters'],
    );
    const hasRange = RANGE_KEYS.some((key) => filters.filter[key] !== '');
    const hasFilter = Boolean(
        filters.search ||
            filters.filter.category_id ||
            filters.filter.status ||
            hasRange,
    );

    const setFilter = (key: keyof ProductFilters['filter'], value: string) =>
        apply({
            filter: { ...filters.filter, [key]: value === ALL ? '' : value },
        });

    // --- Price & stock range form ----------------------------------------
    // Typed values stay local until "Apply" (or Enter), so the table is not
    // reloaded on every keystroke. Re-synced when the server filters change.
    const pickRange = (f: ProductFilters['filter']): RangeValues => ({
        price_min: f.price_min,
        price_max: f.price_max,
        stock_min: f.stock_min,
        stock_max: f.stock_max,
    });
    const [range, setRange] = useState<RangeValues>(() =>
        pickRange(filters.filter),
    );

    useEffect(() => {
        setRange(pickRange(filters.filter));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        filters.filter.price_min,
        filters.filter.price_max,
        filters.filter.stock_min,
        filters.filter.stock_max,
    ]);

    const { errors } = usePage().props;
    const rangeErrors = RANGE_KEYS.map(
        (key) => errors[`filter.${key}`] as string | undefined,
    ).filter(Boolean);

    const setRangeValue = (key: RangeKey, value: string) =>
        setRange((prev) => ({ ...prev, [key]: value }));

    const applyRange = (e: FormEvent) => {
        e.preventDefault();
        apply({ filter: { ...filters.filter, ...trimRange(range) } });
    };

    const clearRange = () => {
        const empty = { price_min: '', price_max: '', stock_min: '', stock_max: '' };
        setRange(empty);
        apply({ filter: { ...filters.filter, ...empty } });
    };

    const rangeDirty = RANGE_KEYS.some(
        (key) => range[key].trim() !== filters.filter[key],
    );

    const resetFilters = () => {
        setSearch('');
        setRange({ price_min: '', price_max: '', stock_min: '', stock_max: '' });
        apply({
            search: '',
            filter: {
                category_id: '',
                status: '',
                price_min: '',
                price_max: '',
                stock_min: '',
                stock_max: '',
            },
        });
    };

    // --- Checklist & bulk delete ---------------------------------------
    // Selection is per page: it is cleared whenever the rows change
    // (paging, sorting, filtering, or after a delete).
    const canBulkDelete = can('products.delete');
    const [selected, setSelected] = useState<Set<string>>(() => new Set());
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const pageIds = useMemo(
        () => products.data.map((p) => p.ulid),
        [products.data],
    );

    useEffect(() => {
        setSelected(new Set());
    }, [products.data]);

    const selection = useMemo<Selection>(
        () => ({
            // Every viewer may print a PDF of the checked rows; deleting
            // is gated separately (canBulkDelete) on the action bar.
            enabled: true,
            selected,
            pageIds,
            toggle: (ulid, checked) =>
                setSelected((prev) => {
                    const next = new Set(prev);

                    if (checked) {
                        next.add(ulid);
                    } else {
                        next.delete(ulid);
                    }

                    return next;
                }),
            togglePage: (checked) =>
                setSelected(checked ? new Set(pageIds) : new Set()),
        }),
        [selected, pageIds],
    );

    // PDF of the checked rows, opened in a new tab (print/download from the
    // browser's PDF viewer). Rows are sorted by name on the server.
    const openPdf = () => {
        const url = ProductController.pdf.url({
            query: { ids: [...selected] },
        });
        window.open(url, '_blank', 'noopener');
    };

    // Excel / CSV of the checked rows. The server answers with an attachment,
    // so the browser downloads it and the page itself stays where it is.
    const download = (format: 'excel' | 'csv') => {
        const options = { query: { ids: [...selected] } };
        window.location.assign(
            format === 'excel'
                ? ProductController.excel.url(options)
                : ProductController.csv.url(options),
        );
    };

    const bulkDelete = () => {
        setDeleting(true);
        router.visit(ProductController.bulkDestroy(), {
            data: { ids: [...selected] },
            preserveScroll: true,
            onSuccess: () => setConfirmOpen(false),
            onError: (errors) => {
                setConfirmOpen(false);
                toast.error(
                    Object.values(errors)[0] ??
                        'Failed to delete the selected products.',
                );
            },
            onFinish: () => setDeleting(false),
        });
    };

    const selectedCount = selected.size;

    return (
        <SelectionContext.Provider value={selection}>
            <Head title="Products" />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    icon={Package}
                    title="Products"
                    description="Manage the product catalog, prices, and stock."
                />

                {/* Range search: price & stock (min – max), outside the table card */}
                <Card className="gap-0 px-4 py-3">
                    <form
                        onSubmit={applyRange}
                        className="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-center"
                    >
                        <RangeInputs
                            label="Price"
                            step="any"
                            min={range.price_min}
                            max={range.price_max}
                            onMinChange={(v) => setRangeValue('price_min', v)}
                            onMaxChange={(v) => setRangeValue('price_max', v)}
                        />
                        <RangeInputs
                            label="Stock"
                            step="1"
                            min={range.stock_min}
                            max={range.stock_max}
                            onMinChange={(v) => setRangeValue('stock_min', v)}
                            onMaxChange={(v) => setRangeValue('stock_max', v)}
                        />
                        <div className="flex items-center gap-2">
                            <Button
                                type="submit"
                                variant="secondary"
                                size="sm"
                                disabled={!rangeDirty}
                            >
                                <Search /> Filter
                            </Button>
                            {hasRange && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={clearRange}
                                >
                                    Clear
                                </Button>
                            )}
                        </div>
                        {rangeErrors.length > 0 && (
                            <p className="w-full text-sm text-destructive">
                                {rangeErrors[0]}
                            </p>
                        )}
                    </form>
                </Card>

                <Card className="gap-0 overflow-hidden py-0">
                    {/* Toolbar: [Add] [Search] on the left, filters on the right */}
                    <div className="flex flex-col gap-3 border-b p-4 xl:flex-row xl:items-center xl:justify-between">
                        <div className="flex w-full flex-col gap-2 sm:flex-row sm:items-center xl:w-auto">
                            {can('products.create') && (
                                <Button
                                    asChild
                                    className="shrink-0 shadow-sm shadow-primary/30"
                                >
                                    <Link href={admin.products.create()}>
                                        <Plus /> Add product
                                    </Link>
                                </Button>
                            )}
                            {(can('products.create') ||
                                can('products.update')) && (
                                <ProductImportDialog />
                            )}
                            <div className="relative w-full sm:max-w-xs lg:w-72">
                                <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search name or SKU…"
                                    className="h-9 rounded-lg pl-9"
                                    aria-label="Search products"
                                />
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <Select
                                value={filters.filter.category_id || ALL}
                                onValueChange={(v) =>
                                    setFilter('category_id', v)
                                }
                            >
                                <SelectTrigger
                                    className="h-9 w-44 rounded-lg"
                                    aria-label="Filter by category"
                                >
                                    <SelectValue placeholder="Category" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>
                                        All categories
                                    </SelectItem>
                                    {categoryOptions.map((c) => (
                                        <SelectItem
                                            key={c.value}
                                            value={c.value}
                                        >
                                            {c.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <Select
                                value={filters.filter.status || ALL}
                                onValueChange={(v) => setFilter('status', v)}
                            >
                                <SelectTrigger
                                    className="h-9 w-40 rounded-lg"
                                    aria-label="Filter by status"
                                >
                                    <SelectValue placeholder="Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>
                                        All statuses
                                    </SelectItem>
                                    <SelectItem value="active">
                                        Active
                                    </SelectItem>
                                    <SelectItem value="inactive">
                                        Inactive
                                    </SelectItem>
                                </SelectContent>
                            </Select>

                            {hasFilter && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={resetFilters}
                                >
                                    Reset
                                </Button>
                            )}
                        </div>
                    </div>

                    {/* Bulk action bar: visible while at least one row is checked */}
                    {selectedCount > 0 && (
                        <div className="flex flex-wrap items-center gap-2 border-b bg-primary/5 px-4 py-2">
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setSelected(new Set())}
                            >
                                Clear selection
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={openPdf}
                            >
                                <FileDown /> Generate PDF
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => download('excel')}
                            >
                                <FileSpreadsheet /> Excel
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => download('csv')}
                            >
                                <FileText /> CSV
                            </Button>
                            {canBulkDelete && (
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    onClick={() => setConfirmOpen(true)}
                                >
                                    <Trash2 /> Delete selected
                                </Button>
                            )}
                            <p className="ml-1 text-sm">
                                <span className="font-medium tabular-nums">
                                    {selectedCount}
                                </span>{' '}
                                {selectedCount === 1 ? 'product' : 'products'}{' '}
                                selected
                            </p>
                        </div>
                    )}

                    <DataTable
                        columns={columns}
                        data={products.data}
                        getRowId={(p) => String(p.id)}
                        sort={filters.sort}
                        onSortChange={(sort) => apply({ sort })}
                        columnClassNames={{
                            select: 'w-10 pr-0',
                            actions: 'w-12 pr-0',
                        }}
                        rowClassName={(p) =>
                            selected.has(p.ulid) ? 'bg-primary/5' : undefined
                        }
                        empty={
                            <EmptyState
                                icon={PackageX}
                                title="No products found"
                                description={
                                    hasFilter
                                        ? 'Try a different keyword or filter.'
                                        : 'Add your first product to get started.'
                                }
                                action={
                                    hasFilter && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={resetFilters}
                                        >
                                            Reset filters
                                        </Button>
                                    )
                                }
                            />
                        }
                    />

                    <div className="border-t bg-muted/20 px-4 py-3">
                        <Pagination
                            page={products}
                            onPerPageChange={(perPage) =>
                                apply({ per_page: perPage })
                            }
                        />
                    </div>
                </Card>
            </div>

            <ConfirmDialog
                open={confirmOpen}
                onOpenChange={(open) => !deleting && setConfirmOpen(open)}
                title={`Delete ${selectedCount} ${selectedCount === 1 ? 'product' : 'products'}?`}
                description="The selected products will be permanently deleted. This action cannot be undone."
                confirmLabel={deleting ? 'Deleting…' : 'Delete'}
                onConfirm={() => {
                    if (!deleting) {
                        bulkDelete();
                    }
                }}
            />
        </SelectionContext.Provider>
    );
}

ProductsIndex.layout = {
    breadcrumbs: [{ title: 'Products', href: admin.products.index() }],
};

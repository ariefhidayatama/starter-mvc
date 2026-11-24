import { Head, Link, router } from '@inertiajs/react';
import { createColumnHelper } from '@tanstack/react-table';
import {
    FolderOpen,
    FolderX,
    Pencil,
    Plus,
    Search,
    Trash2,
} from 'lucide-react';
import CategoryController from '@/actions/App/Http/Controllers/Admin/CategoryController';
import {
    DataTable,
    dataTableFeatures,
} from '@/components/data-table/data-table';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { RowActions } from '@/components/row-actions';
import type { RowAction } from '@/components/row-actions';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useCan } from '@/hooks/use-can';
import { useTableQuery } from '@/hooks/use-table-query';
import { formatDate } from '@/lib/format';
import admin from '@/routes/admin';
import type { Category, CategoryFilters, Paginated } from '@/types';

function CategoryActions({ category }: { category: Category }) {
    const can = useCan();
    const actions: RowAction[] = [];

    if (can('categories.update')) {
        actions.push({
            type: 'link',
            label: 'Edit',
            icon: Pencil,
            href: admin.categories.edit(category.ulid),
        });
    }

    if (can('categories.delete')) {
        actions.push({
            type: 'confirm',
            label: 'Delete',
            icon: Trash2,
            destructive: true,
            confirm: {
                title: `Delete "${category.name}"?`,
                description:
                    'Only empty categories can be deleted. Move or delete its products first.',
                confirmLabel: 'Delete',
            },
            onConfirm: (close) =>
                router.visit(CategoryController.destroy(category.ulid), {
                    preserveScroll: true,
                    onFinish: close,
                }),
        });
    }

    return (
        <RowActions label={`Actions for ${category.name}`} actions={actions} />
    );
}

const column = createColumnHelper<typeof dataTableFeatures, Category>();

const columns = column.columns([
    // First column: the row's actions are the first thing the eye lands on.
    column.display({
        id: 'actions',
        header: () => <span className="sr-only">Actions</span>,
        cell: ({ row }) => <CategoryActions category={row.original} />,
    }),
    column.accessor('name', {
        header: 'Category',
        enableSorting: true,
        cell: ({ row }) => (
            <div className="min-w-0">
                <p className="font-medium">{row.original.name}</p>
                <p className="font-mono text-xs text-muted-foreground">
                    /{row.original.slug}
                </p>
            </div>
        ),
    }),
    column.accessor('description', {
        header: 'Description',
        cell: ({ row }) => (
            <span className="line-clamp-1 max-w-sm whitespace-normal text-muted-foreground">
                {row.original.description || '-'}
            </span>
        ),
    }),
    column.accessor('products_count', {
        header: 'Products',
        enableSorting: true,
        cell: ({ row }) => (
            <span className="tabular-nums">{row.original.products_count}</span>
        ),
    }),
    column.accessor('created_at', {
        header: 'Created',
        enableSorting: true,
        cell: ({ row }) => (
            <span className="text-muted-foreground">
                {formatDate(row.original.created_at)}
            </span>
        ),
    }),
]);

export default function CategoriesIndex({
    categories,
    filters,
}: {
    categories: Paginated<Category>;
    filters: CategoryFilters;
}) {
    const can = useCan();
    const { search, setSearch, apply } = useTableQuery(
        admin.categories.index.url(),
        filters,
        ['categories', 'filters'],
    );

    return (
        <>
            <Head title="Categories" />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    icon={FolderOpen}
                    title="Categories"
                    description="Group products so customers can find them easily."
                />

                <Card className="gap-0 overflow-hidden py-0">
                    {/* Toolbar: [Add] [Search] on the left */}
                    <div className="flex flex-col gap-2 border-b p-4 sm:flex-row sm:items-center">
                        {can('categories.create') && (
                            <Button
                                asChild
                                className="shrink-0 shadow-sm shadow-primary/30"
                            >
                                <Link href={admin.categories.create()}>
                                    <Plus /> Add category
                                </Link>
                            </Button>
                        )}
                        <div className="relative w-full sm:max-w-xs lg:w-72">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search categories…"
                                className="h-9 rounded-lg pl-9"
                                aria-label="Search categories"
                            />
                        </div>
                    </div>

                    <DataTable
                        columns={columns}
                        data={categories.data}
                        getRowId={(c) => String(c.id)}
                        sort={filters.sort}
                        onSortChange={(sort) => apply({ sort })}
                        columnClassNames={{ actions: 'w-12 pr-0' }}
                        empty={
                            <EmptyState
                                icon={FolderX}
                                title="No categories found"
                                description={
                                    filters.search
                                        ? 'Try a different keyword.'
                                        : 'Create your first category.'
                                }
                            />
                        }
                    />

                    <div className="border-t bg-muted/20 px-4 py-3">
                        <Pagination
                            page={categories}
                            onPerPageChange={(perPage) =>
                                apply({ per_page: perPage })
                            }
                        />
                    </div>
                </Card>
            </div>
        </>
    );
}

CategoriesIndex.layout = {
    breadcrumbs: [{ title: 'Categories', href: admin.categories.index() }],
};

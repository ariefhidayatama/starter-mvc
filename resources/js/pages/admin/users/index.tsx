import { Head, Link, router } from '@inertiajs/react';
import { createColumnHelper } from '@tanstack/react-table';
import {
    Pencil,
    Plus,
    Search,
    Trash2,
    Users as UsersIcon,
    UserX,
} from 'lucide-react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import {
    DataTable,
    dataTableFeatures,
} from '@/components/data-table/data-table';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { RowActions } from '@/components/row-actions';
import type { RowAction } from '@/components/row-actions';
import { roleTone, StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { UserIdentity } from '@/components/user-avatar';
import { useCan } from '@/hooks/use-can';
import { useTableQuery } from '@/hooks/use-table-query';
import { formatDate } from '@/lib/format';
import admin from '@/routes/admin';
import type { AdminUserRow, Option, Paginated, UserFilters } from '@/types';

const ALL = 'all';

/** Row actions as a component, so it can use hooks (useCan). */
function UserActions({ user }: { user: AdminUserRow }) {
    const can = useCan();
    const actions: RowAction[] = [];

    // Force-logout lives on the Edit page ("Sessions" card), not here.
    if (can('users.update')) {
        actions.push({
            type: 'link',
            label: 'Edit',
            icon: Pencil,
            href: admin.users.edit(user.ulid),
        });
    }

    if (can('users.delete')) {
        actions.push({
            type: 'confirm',
            label: 'Delete',
            icon: Trash2,
            destructive: true,
            confirm: {
                title: `Delete ${user.name}?`,
                description:
                    'This account will be permanently deleted and logged out from every device. This action cannot be undone.',
                confirmLabel: 'Delete',
            },
            onConfirm: (close) =>
                router.visit(UserController.destroy(user.ulid), {
                    preserveScroll: true,
                    onFinish: close,
                }),
        });
    }

    return <RowActions label={`Actions for ${user.name}`} actions={actions} />;
}

/** Sortable column ids must match UserQueryService::SORTS. */
const column = createColumnHelper<typeof dataTableFeatures, AdminUserRow>();

const columns = column.columns([
    // First column: the row's actions are the first thing the eye lands on.
    column.display({
        id: 'actions',
        header: () => <span className="sr-only">Actions</span>,
        cell: ({ row }) => <UserActions user={row.original} />,
    }),
    column.accessor('name', {
        header: 'User',
        enableSorting: true,
        cell: ({ row }) => (
            <UserIdentity name={row.original.name} email={row.original.email} />
        ),
    }),
    column.accessor('roles', {
        header: 'Role',
        cell: ({ row }) => (
            <div className="flex flex-wrap gap-1">
                {row.original.roles.map((r) => (
                    <StatusBadge key={r} tone={roleTone(r)} dot={false}>
                        {r}
                    </StatusBadge>
                ))}
            </div>
        ),
    }),
    column.accessor('is_active', {
        header: 'Status',
        cell: ({ row }) => (
            <div className="flex flex-col items-start gap-1">
                <StatusBadge
                    tone={row.original.is_active ? 'success' : 'danger'}
                >
                    {row.original.is_active ? 'Active' : 'Inactive'}
                </StatusBadge>
                {!row.original.email_verified && (
                    <span className="text-xs text-muted-foreground">
                        Email not verified
                    </span>
                )}
            </div>
        ),
    }),
    column.accessor('created_at', {
        header: 'Joined',
        enableSorting: true,
        cell: ({ row }) => (
            <span className="text-muted-foreground">
                {formatDate(row.original.created_at)}
            </span>
        ),
    }),
]);

export default function UsersIndex({
    users,
    filters,
    roleOptions,
}: {
    users: Paginated<AdminUserRow>;
    filters: UserFilters;
    roleOptions: Option[];
}) {
    const can = useCan();
    const { search, setSearch, apply } = useTableQuery(
        admin.users.index.url(),
        filters,
        ['users', 'filters'],
    );
    const hasFilter = Boolean(filters.search || filters.role || filters.status);

    const resetFilters = () => {
        setSearch('');
        apply({ search: '', role: '', status: '' });
    };

    return (
        <>
            <Head title="Users" />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    icon={UsersIcon}
                    title="Users"
                    description="Manage accounts, roles, and user status."
                />

                <Card className="gap-0 overflow-hidden py-0">
                    {/* Toolbar: [Add] [Search] on the left, filters on the right */}
                    <div className="flex flex-col gap-3 border-b p-4 xl:flex-row xl:items-center xl:justify-between">
                        <div className="flex w-full flex-col gap-2 sm:flex-row sm:items-center xl:w-auto">
                            {can('users.create') && (
                                <Button
                                    asChild
                                    className="shrink-0 shadow-sm shadow-primary/30"
                                >
                                    <Link href={admin.users.create()}>
                                        <Plus /> Add user
                                    </Link>
                                </Button>
                            )}
                            <div className="relative w-full sm:max-w-xs lg:w-72">
                                <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search name or email…"
                                    className="h-9 rounded-lg pl-9"
                                    aria-label="Search users"
                                />
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <Select
                                value={filters.role || ALL}
                                onValueChange={(v) =>
                                    apply({ role: v === ALL ? '' : v })
                                }
                            >
                                <SelectTrigger
                                    className="h-9 w-40 rounded-lg"
                                    aria-label="Filter by role"
                                >
                                    <SelectValue placeholder="Role" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>
                                        All roles
                                    </SelectItem>
                                    {roleOptions.map((r) => (
                                        <SelectItem
                                            key={r.value}
                                            value={r.value}
                                        >
                                            {r.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <Select
                                value={filters.status || ALL}
                                onValueChange={(v) =>
                                    apply({ status: v === ALL ? '' : v })
                                }
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

                    <DataTable
                        columns={columns}
                        data={users.data}
                        getRowId={(u) => String(u.id)}
                        sort={filters.sort}
                        onSortChange={(sort) => apply({ sort })}
                        columnClassNames={{ actions: 'w-12 pr-0' }}
                        empty={
                            <EmptyState
                                icon={UserX}
                                title="No users found"
                                description={
                                    hasFilter
                                        ? 'Try a different keyword or filter.'
                                        : 'No users have been registered yet.'
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
                            page={users}
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

UsersIndex.layout = {
    breadcrumbs: [{ title: 'Users', href: admin.users.index() }],
};

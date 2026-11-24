import { Head, Link, router } from '@inertiajs/react';
import {
    Database,
    Globe,
    GripVertical,
    LayoutDashboard,
    ListPlus,
    ListTree,
    Pencil,
    Plus,
    Trash2,
    Wand2,
} from 'lucide-react';
import { useState } from 'react';
import MenuController from '@/actions/App/Http/Controllers/Admin/MenuController';
import { MenuPreviewPanel } from '@/components/admin/menu-preview-panel';
import { MenuSortableList } from '@/components/admin/menu-sortable-list';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { RowActions } from '@/components/row-actions';
import type { RowAction } from '@/components/row-actions';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { useCan } from '@/hooks/use-can';
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';
import type {
    GeneratedCrud,
    MenuLocation,
    MenuPreview,
    MenuRow,
} from '@/types';

const LOCATIONS: {
    value: MenuLocation;
    label: string;
    icon: typeof Globe;
}[] = [
    { value: 'admin', label: 'Dashboard sidebar', icon: LayoutDashboard },
    { value: 'website', label: 'Website navbar', icon: Globe },
];

/** Delete dialog options for a menu whose page came from the CRUD generator. */
function GeneratedCodeOptions({
    crud,
    removeCode,
    dropTable,
    onRemoveCodeChange,
    onDropTableChange,
}: {
    crud: GeneratedCrud;
    removeCode: boolean;
    dropTable: boolean;
    onRemoveCodeChange: (value: boolean) => void;
    onDropTableChange: (value: boolean) => void;
}) {
    return (
        <div className="space-y-3 rounded-xl border bg-muted/30 p-4 text-sm">
            <p className="flex items-center gap-2 font-medium">
                <Wand2 className="size-4 text-primary" />
                This page was made by the CRUD generator
            </p>
            <label className="flex items-start gap-2.5">
                <Checkbox
                    checked={removeCode}
                    onCheckedChange={(v) => onRemoveCodeChange(v === true)}
                    className="mt-0.5"
                />
                <span>
                    Also delete the generated code
                    <span className="block text-xs text-muted-foreground">
                        Controller, model, request, policy, factory, React
                        pages, test, routes &amp; <code>
                            {crud.table}.*
                        </code>{' '}
                        permissions. Files you have edited are kept.
                    </span>
                </span>
            </label>
            {crud.can_drop ? (
                <label
                    className={cn(
                        'flex items-start gap-2.5',
                        !removeCode && 'opacity-50',
                    )}
                >
                    <Checkbox
                        checked={dropTable}
                        disabled={!removeCode}
                        onCheckedChange={(v) => onDropTableChange(v === true)}
                        className="mt-0.5"
                    />
                    <span>
                        Also drop the <code>{crud.db_table}</code> table
                        <span className="block text-xs font-medium text-destructive">
                            All its data is deleted permanently.
                        </span>
                    </span>
                </label>
            ) : (
                <p className="flex items-start gap-2.5 text-xs text-muted-foreground">
                    <Database className="mt-0.5 size-4 shrink-0" />
                    <span>
                        The <code>{crud.db_table}</code> table existed before
                        the generator, so it and its data are always kept.
                    </span>
                </p>
            )}
        </div>
    );
}

/** Row actions as a component, so it can use hooks (useCan). */
function MenuActions({ menu }: { menu: MenuRow }) {
    const can = useCan();
    const actions: RowAction[] = [];
    // Only for menus made by the CRUD generator (development).
    const [removeCode, setRemoveCode] = useState(false);
    const [dropTable, setDropTable] = useState(false);

    if (can('menus.update')) {
        actions.push({
            type: 'link',
            label: 'Edit',
            icon: Pencil,
            href: admin.menus.edit(menu.ulid),
        });
    }

    // Only top-level menus can have submenus (max 2 levels).
    if (can('menus.create') && menu.depth === 0) {
        actions.push({
            type: 'link',
            label: 'Add submenu',
            icon: ListPlus,
            href: admin.menus.create({
                query: { location: menu.location, parent: menu.ulid },
            }),
        });
    }

    // Built-in menus cannot be deleted, so the option is not offered.
    if (can('menus.delete') && !menu.is_system) {
        actions.push({
            type: 'confirm',
            label: 'Delete',
            icon: Trash2,
            destructive: true,
            confirm: {
                title: `Delete "${menu.title}"?`,
                description:
                    menu.children_count > 0
                        ? `This menu still has ${menu.children_count} submenu(s). Delete or move them first.`
                        : 'This menu will be removed from navigation. This action cannot be undone.',
                confirmLabel: 'Delete',
                body: menu.generated && menu.children_count === 0 && (
                    <GeneratedCodeOptions
                        crud={menu.generated}
                        removeCode={removeCode}
                        dropTable={dropTable}
                        onRemoveCodeChange={(v) => {
                            setRemoveCode(v);

                            if (!v) {
                                setDropTable(false);
                            }
                        }}
                        onDropTableChange={setDropTable}
                    />
                ),
            },
            onConfirm: (close) =>
                router.visit(MenuController.destroy(menu.ulid), {
                    data: menu.generated
                        ? {
                              remove_code: removeCode ? 1 : 0,
                              drop_table:
                                  dropTable && menu.generated.can_drop ? 1 : 0,
                          }
                        : {},
                    preserveScroll: true,
                    onFinish: close,
                }),
        });
    }

    return <RowActions label={`Actions for ${menu.title}`} actions={actions} />;
}

export default function MenusIndex({
    location,
    rows,
    counts,
    previews,
}: {
    location: MenuLocation;
    rows: MenuRow[];
    counts: Partial<Record<MenuLocation, number>>;
    previews: MenuPreview[];
}) {
    const can = useCan();
    const isAdmin = location === 'admin';

    return (
        <>
            <Head title="Menus" />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    icon={ListTree}
                    title="Menus"
                    description="Manage the dashboard sidebar and the website navbar, including submenus."
                />

                <div
                    className={cn(
                        'grid items-start gap-6',
                        isAdmin && '2xl:grid-cols-[minmax(0,1fr)_20rem]',
                    )}
                >
                    <Card className="gap-0 overflow-hidden py-0">
                        {/* Toolbar: [Add] [location tabs] on the left */}
                        <div className="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center">
                            {can('menus.create') && (
                                <Button
                                    asChild
                                    className="shrink-0 shadow-sm shadow-primary/30"
                                >
                                    <Link
                                        href={admin.menus.create({
                                            query: { location },
                                        })}
                                    >
                                        <Plus /> Add menu
                                    </Link>
                                </Button>
                            )}

                            <div
                                role="tablist"
                                aria-label="Menu location"
                                className="inline-flex w-fit rounded-lg bg-muted p-1"
                            >
                                {LOCATIONS.map(
                                    ({ value, label, icon: Icon }) => (
                                        <Link
                                            key={value}
                                            role="tab"
                                            aria-selected={location === value}
                                            href={admin.menus.index({
                                                query: { location: value },
                                            })}
                                            preserveScroll
                                            className={cn(
                                                'inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                                location === value
                                                    ? 'bg-background text-foreground shadow-sm'
                                                    : 'text-muted-foreground hover:text-foreground',
                                            )}
                                        >
                                            <Icon className="size-4" />
                                            {label}
                                            <span className="rounded-full bg-muted-foreground/10 px-1.5 text-xs tabular-nums">
                                                {counts[value] ?? 0}
                                            </span>
                                        </Link>
                                    ),
                                )}
                            </div>
                        </div>

                        {can('menus.update') && rows.length > 0 && (
                            <div className="flex items-center gap-2 border-b bg-primary/5 px-4 py-2.5 text-sm text-muted-foreground">
                                <GripVertical className="size-4 shrink-0 text-primary" />
                                <span>
                                    <strong className="text-foreground">
                                        Drag
                                    </strong>{' '}
                                    up or down to reorder. Drag{' '}
                                    <strong className="text-foreground">
                                        right
                                    </strong>{' '}
                                    to make it a submenu,{' '}
                                    <strong className="text-foreground">
                                        left
                                    </strong>{' '}
                                    to make it a main menu.
                                </span>
                            </div>
                        )}

                        <MenuSortableList
                            location={location}
                            rows={rows}
                            canReorder={can('menus.update')}
                            showAudience={isAdmin}
                            renderActions={(menu) => (
                                <MenuActions menu={menu} />
                            )}
                            empty={
                                <EmptyState
                                    icon={ListTree}
                                    title="No menus yet"
                                    description="Add the first menu for this location."
                                />
                            }
                        />

                        <div className="border-t bg-muted/20 px-4 py-3 text-sm text-muted-foreground">
                            A menu without a URL only opens its submenus. Hiding
                            a menu does not block access to the page.
                            Permissions do.
                        </div>
                    </Card>

                    {isAdmin && previews.length > 0 && (
                        <aside className="2xl:sticky 2xl:top-4">
                            <MenuPreviewPanel previews={previews} />
                        </aside>
                    )}
                </div>
            </div>
        </>
    );
}

MenusIndex.layout = {
    breadcrumbs: [{ title: 'Menus', href: admin.menus.index() }],
};

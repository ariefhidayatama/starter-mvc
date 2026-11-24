import {
    closestCenter,
    DndContext,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import type {
    DragEndEvent,
    DragMoveEvent,
    DragOverEvent,
    DragStartEvent,
    UniqueIdentifier,
} from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { router } from '@inertiajs/react';
import {
    ArrowUpRight,
    CornerDownRight,
    GripVertical,
    ListTree,
    TriangleAlert,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import MenuController from '@/actions/App/Http/Controllers/Admin/MenuController';
import { roleTone, StatusBadge } from '@/components/status-badge';
import { menuIcon } from '@/lib/menu-icons';
import { cn } from '@/lib/utils';
import type { MenuLocation, MenuRow } from '@/types';

/** Horizontal distance (px) that moves an item one level in or out. */
const INDENT = 28;

type Projection = { depth: 0 | 1; parentId: number | null };

/**
 * Menu list with drag & drop.
 *
 * - Drag up/down     → change the order.
 * - Drag right/left  → turn into a submenu / back into a top-level menu.
 * - A parent is dragged together with its submenus.
 *
 * The whole list is saved in one request (PUT /admin/menus/reorder); the
 * server re-checks the rules (max 2 levels, submenus need a URL).
 */
export function MenuSortableList({
    location,
    rows,
    canReorder,
    renderActions,
    showAudience,
    empty,
}: {
    location: MenuLocation;
    rows: MenuRow[];
    canReorder: boolean;
    renderActions: (menu: MenuRow) => ReactNode;
    showAudience: boolean;
    empty: ReactNode;
}) {
    // Local copy for optimistic updates; reset whenever the server sends new rows.
    const [items, setItems] = useState(rows);
    const [prevRows, setPrevRows] = useState(rows);

    if (rows !== prevRows) {
        setPrevRows(rows);
        setItems(rows);
    }

    const [activeId, setActiveId] = useState<UniqueIdentifier | null>(null);
    const [overId, setOverId] = useState<UniqueIdentifier | null>(null);
    const [offsetLeft, setOffsetLeft] = useState(0);
    const [saving, setSaving] = useState(false);

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );

    // While a parent is dragged, its submenus are hidden and travel with it.
    const visibleItems = useMemo(
        () =>
            activeId === null
                ? items
                : items.filter((i) => i.parent_id !== activeId),
        [items, activeId],
    );

    const projection =
        activeId !== null && overId !== null
            ? project(visibleItems, activeId, overId, offsetLeft)
            : null;

    const reset = () => {
        setActiveId(null);
        setOverId(null);
        setOffsetLeft(0);
    };

    const handleDragEnd = ({ active, over }: DragEndEvent) => {
        const proj = projection;
        reset();

        if (!over || !proj) {
            return;
        }

        const activeIndex = visibleItems.findIndex((i) => i.id === active.id);
        const overIndex = visibleItems.findIndex((i) => i.id === over.id);
        const moved = visibleItems[activeIndex];

        let next = arrayMove(visibleItems, activeIndex, overIndex).map((i) =>
            i.id === moved.id
                ? { ...i, depth: proj.depth, parent_id: proj.parentId }
                : i,
        );

        // Put the dragged parent's submenus back right after it.
        const hiddenChildren = items.filter((i) => i.parent_id === moved.id);

        if (hiddenChildren.length > 0) {
            const at = next.findIndex((i) => i.id === moved.id) + 1;
            next = [...next.slice(0, at), ...hiddenChildren, ...next.slice(at)];
        }

        next = normalize(next);

        if (sameStructure(items, next)) {
            return;
        }

        const previous = items;
        setItems(next);
        setSaving(true);

        router.put(
            MenuController.reorder.url(),
            {
                location,
                items: next.map((i) => ({ id: i.id, parent_id: i.parent_id })),
            },
            {
                preserveScroll: true,
                onError: (errors) => {
                    setItems(previous);
                    toast.error(
                        errors.items ?? 'The new order could not be saved.',
                    );
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    if (items.length === 0) {
        return <>{empty}</>;
    }

    const gridCols = showAudience
        ? 'md:grid-cols-[2rem_2.5rem_minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1.2fr)_7rem]'
        : 'md:grid-cols-[2rem_2.5rem_minmax(0,1.5fr)_minmax(0,1.4fr)_7rem]';

    return (
        <div className={cn(saving && 'pointer-events-none opacity-70')}>
            {/* Header */}
            <div
                className={cn(
                    'hidden items-center gap-3 border-b bg-muted/40 px-4 py-2.5 text-xs font-medium tracking-wide text-muted-foreground uppercase md:grid',
                    gridCols,
                )}
            >
                {/* Empty cells keep the columns aligned (sr-only would leave the grid). */}
                <span aria-hidden="true" />
                <span aria-hidden="true" />
                <span>Menu</span>
                <span>URL</span>
                {showAudience && <span>Visible to</span>}
                <span>Status</span>
            </div>

            <DndContext
                sensors={sensors}
                collisionDetection={closestCenter}
                onDragStart={({ active }: DragStartEvent) => {
                    setActiveId(active.id);
                    setOverId(active.id);
                }}
                onDragMove={({ delta }: DragMoveEvent) =>
                    setOffsetLeft(delta.x)
                }
                onDragOver={({ over }: DragOverEvent) =>
                    setOverId(over?.id ?? null)
                }
                onDragEnd={handleDragEnd}
                onDragCancel={reset}
            >
                <SortableContext
                    items={visibleItems.map((i) => i.id)}
                    strategy={verticalListSortingStrategy}
                >
                    <ul className="divide-y">
                        {visibleItems.map((menu) => (
                            <SortableRow
                                key={menu.id}
                                menu={menu}
                                depth={
                                    menu.id === activeId && projection
                                        ? projection.depth
                                        : menu.depth
                                }
                                isDraggingParent={
                                    menu.id === activeId &&
                                    menu.children_count > 0
                                }
                                gridCols={gridCols}
                                canReorder={canReorder && !saving}
                                actions={renderActions(menu)}
                                showAudience={showAudience}
                            />
                        ))}
                    </ul>
                </SortableContext>
            </DndContext>
        </div>
    );
}

function SortableRow({
    menu,
    depth,
    isDraggingParent,
    gridCols,
    canReorder,
    actions,
    showAudience,
}: {
    menu: MenuRow;
    depth: 0 | 1;
    isDraggingParent: boolean;
    gridCols: string;
    canReorder: boolean;
    actions: ReactNode;
    showAudience: boolean;
}) {
    const {
        attributes,
        listeners,
        setNodeRef,
        setActivatorNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id: menu.id, disabled: !canReorder });

    const Icon = menuIcon(menu.icon);
    const emptyGroup =
        menu.depth === 0 && !menu.url && menu.children_count === 0;

    return (
        <li
            ref={setNodeRef}
            style={{
                // Vertical movement only; depth is shown by indentation.
                transform: CSS.Translate.toString(
                    transform ? { ...transform, x: 0 } : null,
                ),
                transition,
            }}
            className={cn(
                'relative grid grid-cols-[2rem_2.5rem_minmax(0,1fr)] items-center gap-3 bg-card px-4 py-3',
                gridCols,
                !menu.is_active && 'text-muted-foreground',
                isDragging &&
                    'z-10 rounded-lg shadow-lg ring-2 shadow-primary/10 ring-primary',
            )}
        >
            <button
                type="button"
                ref={setActivatorNodeRef}
                {...attributes}
                {...listeners}
                disabled={!canReorder}
                aria-label={`Drag to reorder ${menu.title}`}
                className="flex size-8 cursor-grab touch-none items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none active:cursor-grabbing disabled:cursor-not-allowed disabled:opacity-30"
            >
                <GripVertical className="size-4" />
            </button>

            <div>{actions}</div>

            {/* Menu */}
            <div
                className="flex min-w-0 items-center gap-2.5 transition-[padding]"
                style={{ paddingLeft: depth * INDENT }}
            >
                {depth === 1 && (
                    <CornerDownRight className="size-3.5 shrink-0 text-muted-foreground/60" />
                )}
                <span
                    className={cn(
                        'flex size-8 shrink-0 items-center justify-center rounded-lg',
                        depth === 0
                            ? 'bg-primary/10 text-primary'
                            : 'bg-muted text-muted-foreground',
                        !menu.is_active && 'opacity-60',
                    )}
                >
                    {Icon ? (
                        <Icon className="size-4" />
                    ) : (
                        <ListTree className="size-4 opacity-40" />
                    )}
                </span>
                <div className="min-w-0">
                    <p
                        className={cn(
                            'truncate',
                            depth === 0 && 'font-semibold',
                        )}
                    >
                        {menu.title}
                    </p>
                    {menu.children_count > 0 && (
                        <p className="text-xs text-muted-foreground">
                            {isDraggingParent
                                ? `Moving with ${menu.children_count} submenu${menu.children_count > 1 ? 's' : ''}`
                                : `${menu.children_count} submenu${menu.children_count > 1 ? 's' : ''}`}
                        </p>
                    )}
                    {emptyGroup && (
                        <p className="flex items-center gap-1 text-xs font-medium text-amber-700 dark:text-warning">
                            <TriangleAlert className="size-3.5 shrink-0" />
                            Not shown: add a URL or a submenu
                        </p>
                    )}
                </div>
            </div>

            {/* URL */}
            <div className="col-start-3 min-w-0 md:col-start-auto">
                {menu.url ? (
                    <span className="inline-flex max-w-full items-center gap-1 font-mono text-xs text-muted-foreground">
                        <span className="truncate">{menu.url}</span>
                        {menu.open_in_new_tab && (
                            <ArrowUpRight
                                className="size-3.5 shrink-0"
                                aria-label="Opens in a new tab"
                            />
                        )}
                    </span>
                ) : (
                    <span className="text-xs text-muted-foreground italic">
                        {menu.children_count > 0 ? 'Opens submenu' : 'No URL'}
                    </span>
                )}
            </div>

            {showAudience && (
                <div className="col-start-3 min-w-0 md:col-start-auto">
                    <AudienceCell menu={menu} />
                </div>
            )}

            <div className="col-start-3 flex flex-wrap items-center gap-1 md:col-start-auto">
                <StatusBadge tone={menu.is_active ? 'success' : 'neutral'}>
                    {menu.is_active ? 'Active' : 'Hidden'}
                </StatusBadge>
                {menu.is_system && (
                    <StatusBadge tone="neutral" dot={false}>
                        Built-in
                    </StatusBadge>
                )}
            </div>
        </li>
    );
}

function AudienceCell({ menu }: { menu: MenuRow }) {
    const { type, roles, permission } = menu.audience;

    if (type === 'everyone' || type === 'public') {
        return <span className="text-sm text-muted-foreground">Everyone</span>;
    }

    return (
        <div className="space-y-1">
            <div className="flex flex-wrap gap-1">
                {roles.map((r) => (
                    <StatusBadge key={r} tone={roleTone(r)} dot={false}>
                        {r}
                    </StatusBadge>
                ))}
            </div>
            {type === 'permission' && (
                <p className="text-xs text-muted-foreground">
                    via <code>{permission}</code>
                </p>
            )}
        </div>
    );
}

/**
 * Where the dragged item would land: its depth from the horizontal drag
 * distance, limited by its neighbours and by the menu rules.
 */
function project(
    items: MenuRow[],
    activeId: UniqueIdentifier,
    overId: UniqueIdentifier,
    dragOffset: number,
): Projection {
    const activeIndex = items.findIndex((i) => i.id === activeId);
    const overIndex = items.findIndex((i) => i.id === overId);
    const active = items[activeIndex];
    const moved = arrayMove(items, activeIndex, overIndex);
    const previous = moved[overIndex - 1];
    const next = moved[overIndex + 1];

    // Only a menu with a URL and no submenus may become a submenu.
    const canNest = active.url !== null && active.children_count === 0;
    const maxDepth = previous && canNest ? 1 : 0;
    // Dropping between a parent and its submenus keeps the item inside.
    const minDepth = next?.depth === 1 && canNest ? 1 : 0;

    const wanted = active.depth + Math.round(dragOffset / INDENT);
    const depth = Math.min(Math.max(wanted, minDepth), maxDepth) as 0 | 1;

    if (depth === 0) {
        return { depth, parentId: null };
    }

    const parent = moved
        .slice(0, overIndex)
        .reverse()
        .find((i) => i.depth === 0);

    return { depth, parentId: parent?.id ?? null };
}

/** Recompute parent_id, depth and children_count from the list order. */
function normalize(list: MenuRow[]): MenuRow[] {
    let parentId: number | null = null;

    const withParents = list.map((item) => {
        if (item.depth === 0) {
            parentId = item.id;

            return { ...item, parent_id: null };
        }

        return { ...item, parent_id: parentId };
    });

    return withParents.map((item) => ({
        ...item,
        children_count:
            item.depth === 0
                ? withParents.filter((c) => c.parent_id === item.id).length
                : 0,
    }));
}

function sameStructure(a: MenuRow[], b: MenuRow[]): boolean {
    return (
        a.length === b.length &&
        a.every(
            (item, i) =>
                item.id === b[i].id && item.parent_id === b[i].parent_id,
        )
    );
}

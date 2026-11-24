import { ChevronDown, Eye, EyeOff, ListTree } from 'lucide-react';
import { useState } from 'react';
import { roleTone, StatusBadge } from '@/components/status-badge';
import { ToggleSwitch } from '@/components/toggle-switch';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { menuIcon } from '@/lib/menu-icons';
import { cn } from '@/lib/utils';
import type { MenuPreview, MenuPreviewNode } from '@/types';

/**
 * "View as role": the dashboard sidebar exactly as one role sees it,
 * computed on the server with the same rules as the real sidebar.
 * Hidden items can be shown with the reason they are hidden.
 */
export function MenuPreviewPanel({ previews }: { previews: MenuPreview[] }) {
    // Start with a non-admin role: admin sees everything, which is the least useful view.
    const [role, setRole] = useState(
        previews.find((p) => p.role !== 'admin')?.role ??
            previews[0]?.role ??
            '',
    );
    const [showHidden, setShowHidden] = useState(true);

    const preview = previews.find((p) => p.role === role);

    if (!preview) {
        return null;
    }

    const visibleCount = countVisible(preview.tree);

    return (
        <Card className="gap-4">
            <CardHeader className="flex-row items-start gap-3">
                <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Eye className="size-4.5" />
                </div>
                <div className="space-y-1">
                    <CardTitle>Sidebar preview</CardTitle>
                    <CardDescription>
                        What a role sees, and why items are hidden.
                    </CardDescription>
                </div>
            </CardHeader>

            <CardContent className="space-y-4">
                <div className="grid gap-2">
                    <Label htmlFor="preview-role">View as</Label>
                    <Select value={role} onValueChange={setRole}>
                        <SelectTrigger id="preview-role" className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {previews.map((p) => (
                                <SelectItem key={p.role} value={p.role}>
                                    {p.role}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="flex items-center justify-between gap-2">
                    <span className="text-sm text-muted-foreground">
                        Show hidden items
                    </span>
                    <ToggleSwitch
                        checked={showHidden}
                        onChange={setShowHidden}
                        label="Show hidden items"
                    />
                </div>

                {/* Mini sidebar */}
                <div className="rounded-xl border bg-sidebar p-2">
                    <div className="mb-1 flex items-center justify-between px-2 py-1">
                        <StatusBadge tone={roleTone(role)} dot={false}>
                            {role}
                        </StatusBadge>
                        <span className="text-xs text-muted-foreground">
                            {visibleCount} visible
                        </span>
                    </div>

                    {visibleCount === 0 && !showHidden ? (
                        <p className="px-2 py-4 text-center text-sm text-muted-foreground">
                            This role sees no menus.
                        </p>
                    ) : (
                        <ul className="space-y-0.5">
                            {preview.tree.map((node) => (
                                <PreviewItem
                                    key={node.id}
                                    node={node}
                                    showHidden={showHidden}
                                />
                            ))}
                        </ul>
                    )}
                </div>

                {role === 'admin' && (
                    <p className="text-xs text-muted-foreground">
                        Admin always sees every active menu.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}

function PreviewItem({
    node,
    showHidden,
    child = false,
}: {
    node: MenuPreviewNode;
    showHidden: boolean;
    child?: boolean;
}) {
    if (!node.visible && !showHidden) {
        return null;
    }

    const Icon = menuIcon(node.icon) ?? ListTree;
    const children = node.children.filter((c) => c.visible || showHidden);

    return (
        <li>
            <div
                className={cn(
                    'flex items-start gap-2 rounded-md px-2 py-1.5 text-sm',
                    child && 'ml-4 border-l pl-3',
                    !node.visible && 'text-muted-foreground',
                )}
            >
                {node.visible ? (
                    <Icon className="mt-0.5 size-4 shrink-0" />
                ) : (
                    <EyeOff className="mt-0.5 size-4 shrink-0 opacity-60" />
                )}
                <div className="min-w-0 flex-1">
                    <p
                        className={cn(
                            'truncate',
                            !node.visible && 'line-through decoration-1',
                        )}
                    >
                        {node.title}
                    </p>
                    {!node.visible && node.reason && (
                        <p className="text-xs leading-snug text-amber-700 dark:text-warning">
                            {node.reason}
                        </p>
                    )}
                </div>
                {!child && children.length > 0 && (
                    <ChevronDown className="mt-0.5 size-3.5 shrink-0 opacity-50" />
                )}
            </div>

            {children.length > 0 && (
                <ul className="space-y-0.5">
                    {children.map((c) => (
                        <PreviewItem
                            key={c.id}
                            node={c}
                            showHidden={showHidden}
                            child
                        />
                    ))}
                </ul>
            )}
        </li>
    );
}

function countVisible(tree: MenuPreviewNode[]): number {
    return tree.reduce(
        (sum, n) => sum + (n.visible ? 1 + countVisible(n.children) : 0),
        0,
    );
}

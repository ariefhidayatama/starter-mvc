import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { MoreHorizontal } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

/** A menu item that navigates to another page (e.g. Edit). */
type LinkAction = {
    type: 'link';
    label: string;
    icon: LucideIcon;
    href: NonNullable<InertiaLinkProps['href']>;
};

/**
 * A menu item that asks for confirmation first (e.g. Delete).
 * `onConfirm` receives a `close` callback to close the dialog when done.
 */
type ConfirmAction = {
    type: 'confirm';
    label: string;
    icon: LucideIcon;
    destructive?: boolean;
    confirm: {
        title: string;
        description: string;
        confirmLabel: string;
        /** Extra content inside the dialog, e.g. checkboxes. */
        body?: ReactNode;
    };
    onConfirm: (close: () => void) => void;
};

export type RowAction = LinkAction | ConfirmAction;

/**
 * "⋯" button in a table row that opens a dropdown of actions.
 *
 * Actions the user is not allowed to perform should simply not be passed
 * in (filter them with useCan()). If no action remains, nothing renders.
 */
export function RowActions({
    label,
    actions,
    align = 'start',
}: {
    /** Accessible name, e.g. "Actions for Budi". */
    label: string;
    actions: RowAction[];
    /** Menu alignment: 'start' for the first table column, 'end' for a card's top-right corner. */
    align?: 'start' | 'end';
}) {
    // The dialog lives OUTSIDE the dropdown: the dropdown closes first,
    // then the dialog opens (otherwise both would fight over focus).
    // Store the label, not the action object, so the dialog always uses the
    // latest action (its body / onConfirm may change while it is open).
    const [pendingLabel, setPendingLabel] = useState<string | null>(null);
    const pending = actions.find(
        (a): a is ConfirmAction =>
            a.type === 'confirm' && a.label === pendingLabel,
    );
    const setPending = (action: ConfirmAction | null) =>
        setPendingLabel(action?.label ?? null);

    if (actions.length === 0) {
        return null;
    }

    const destructiveStart = actions.findIndex(
        (a) => a.type === 'confirm' && a.destructive,
    );

    return (
        <>
            <DropdownMenu modal={false}>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-8 text-muted-foreground data-[state=open]:bg-muted data-[state=open]:text-foreground"
                        aria-label={label}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align={align} className="w-44">
                    <DropdownMenuLabel className="text-xs text-muted-foreground">
                        Actions
                    </DropdownMenuLabel>
                    {actions.map((action, i) => {
                        const Icon = action.icon;

                        return (
                            <div key={action.label}>
                                {i === destructiveStart && i > 0 && (
                                    <DropdownMenuSeparator />
                                )}
                                {action.type === 'link' ? (
                                    <DropdownMenuItem asChild>
                                        <Link
                                            href={action.href}
                                            className="cursor-pointer"
                                        >
                                            <Icon /> {action.label}
                                        </Link>
                                    </DropdownMenuItem>
                                ) : (
                                    <DropdownMenuItem
                                        variant={
                                            action.destructive
                                                ? 'destructive'
                                                : 'default'
                                        }
                                        className="cursor-pointer"
                                        onSelect={() => setPending(action)}
                                    >
                                        <Icon /> {action.label}
                                    </DropdownMenuItem>
                                )}
                            </div>
                        );
                    })}
                </DropdownMenuContent>
            </DropdownMenu>

            {pending && (
                <ConfirmDialog
                    open
                    onOpenChange={(open) => !open && setPending(null)}
                    title={pending.confirm.title}
                    description={pending.confirm.description}
                    confirmLabel={pending.confirm.confirmLabel}
                    destructive={pending.destructive ?? false}
                    onConfirm={(close) =>
                        pending.onConfirm(() => {
                            close();
                            setPending(null);
                        })
                    }
                >
                    {pending.confirm.body}
                </ConfirmDialog>
            )}
        </>
    );
}

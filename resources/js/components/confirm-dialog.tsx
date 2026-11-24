import type { ReactNode } from 'react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

/**
 * "Are you sure?" dialog before a dangerous action (delete, force logout).
 *
 * Two ways to use it:
 * - With `trigger`: the dialog opens when the trigger is clicked.
 * - Controlled (`open` + `onOpenChange`): opened from elsewhere,
 *   e.g. from a dropdown menu item.
 */
export function ConfirmDialog({
    trigger,
    open: controlledOpen,
    onOpenChange,
    title,
    description,
    confirmLabel = 'Confirm',
    destructive = true,
    onConfirm,
    children,
}: {
    trigger?: ReactNode;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    title: string;
    description: string;
    confirmLabel?: string;
    destructive?: boolean;
    onConfirm: (close: () => void) => void;
    /** Extra content between the description and the buttons (e.g. options). */
    children?: ReactNode;
}) {
    const [internalOpen, setInternalOpen] = useState(false);
    const isControlled = controlledOpen !== undefined;
    const open = isControlled ? controlledOpen : internalOpen;

    const setOpen = (value: boolean) => {
        if (!isControlled) {
            setInternalOpen(value);
        }

        onOpenChange?.(value);
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            {trigger && <DialogTrigger asChild>{trigger}</DialogTrigger>}
            <DialogContent>
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription>{description}</DialogDescription>
                {children}
                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button
                        variant={destructive ? 'destructive' : 'default'}
                        onClick={() => onConfirm(() => setOpen(false))}
                    >
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

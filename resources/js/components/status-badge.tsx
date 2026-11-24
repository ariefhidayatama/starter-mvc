import { cn } from '@/lib/utils';

const TONES = {
    success: 'bg-success/10 text-success ring-success/20',
    danger: 'bg-destructive/10 text-destructive-foreground ring-destructive/20',
    warning: 'bg-warning/15 text-amber-700 ring-warning/30 dark:text-warning',
    neutral: 'bg-muted text-muted-foreground ring-border',
    primary: 'bg-primary/10 text-primary ring-primary/20',
} as const;

export type BadgeTone = keyof typeof TONES;

/**
 * Badge "pil" dengan titik warna, mis. ● Aktif.
 */
export function StatusBadge({
    tone,
    children,
    dot = true,
    className,
}: {
    tone: BadgeTone;
    children: React.ReactNode;
    dot?: boolean;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset',
                TONES[tone],
                className,
            )}
        >
            {dot && <span className="size-1.5 rounded-full bg-current" />}
            {children}
        </span>
    );
}

/** Warna badge per role, agar mudah dibedakan sekilas. */
export function roleTone(role: string): BadgeTone {
    switch (role) {
        case 'admin':
            return 'primary';
        case 'staff':
            return 'warning';
        default:
            return 'neutral';
    }
}

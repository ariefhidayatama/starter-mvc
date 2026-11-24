import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

export function EmptyState({
    icon: Icon,
    title,
    description,
    action,
}: {
    icon: LucideIcon;
    title: string;
    description?: string;
    action?: ReactNode;
}) {
    return (
        <div className="flex flex-col items-center justify-center gap-3 px-6 py-14 text-center">
            <div className="flex size-12 items-center justify-center rounded-2xl bg-muted text-muted-foreground">
                <Icon className="size-6" />
            </div>
            <div className="space-y-1">
                <p className="font-semibold">{title}</p>
                {description && (
                    <p className="max-w-sm text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {action}
        </div>
    );
}

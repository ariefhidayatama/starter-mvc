import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * Judul halaman yang konsisten: ikon, judul, deskripsi, dan tombol aksi.
 */
export function PageHeader({
    title,
    description,
    icon: Icon,
    actions,
}: {
    title: string;
    description?: string;
    icon?: LucideIcon;
    actions?: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-center gap-4">
                {Icon && (
                    <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/15">
                        <Icon className="size-5" />
                    </div>
                )}
                <div className="space-y-0.5">
                    <h1 className="text-2xl font-bold tracking-tight">
                        {title}
                    </h1>
                    {description && (
                        <p className="text-sm text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>
            </div>
            {actions && (
                <div className="flex items-center gap-2">{actions}</div>
            )}
        </div>
    );
}

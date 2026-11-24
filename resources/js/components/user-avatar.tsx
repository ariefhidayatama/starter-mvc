import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';

/** Palet warna avatar; dipilih dari nama agar orang yang sama selalu sewarna. */
const COLORS = [
    'bg-indigo-500/15 text-indigo-700 dark:text-indigo-300',
    'bg-violet-500/15 text-violet-700 dark:text-violet-300',
    'bg-sky-500/15 text-sky-700 dark:text-sky-300',
    'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
    'bg-amber-500/15 text-amber-700 dark:text-amber-300',
    'bg-rose-500/15 text-rose-700 dark:text-rose-300',
];

function colorFor(name: string): string {
    let hash = 0;

    for (const char of name) {
        hash = (hash * 31 + char.charCodeAt(0)) >>> 0;
    }

    return COLORS[hash % COLORS.length];
}

export function UserAvatar({
    name,
    className,
}: {
    name: string;
    className?: string;
}) {
    const getInitials = useInitials();

    return (
        <div
            className={cn(
                'flex size-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                colorFor(name),
                className,
            )}
            aria-hidden
        >
            {getInitials(name)}
        </div>
    );
}

/** Nama + email dengan avatar, untuk sel tabel & daftar. */
export function UserIdentity({ name, email }: { name: string; email: string }) {
    return (
        <div className="flex min-w-0 items-center gap-3">
            <UserAvatar name={name} />
            <div className="min-w-0">
                <p className="truncate font-medium">{name}</p>
                <p className="truncate text-xs text-muted-foreground">
                    {email}
                </p>
            </div>
        </div>
    );
}

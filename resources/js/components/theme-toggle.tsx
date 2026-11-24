import { Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';

/** Tombol cepat ganti tema terang/gelap (pengaturan lengkap di Settings → Appearance). */
export function ThemeToggle() {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const isDark = resolvedAppearance === 'dark';

    return (
        <Button
            variant="ghost"
            size="icon"
            className="rounded-full"
            onClick={() => updateAppearance(isDark ? 'light' : 'dark')}
            aria-label={
                isDark ? 'Switch to light theme' : 'Switch to dark theme'
            }
        >
            {isDark ? <Sun /> : <Moon />}
        </Button>
    );
}

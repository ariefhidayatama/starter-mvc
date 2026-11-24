import { cn } from '@/lib/utils';

/**
 * On/off switch. Pass `name` to submit the value inside a <Form> as
 * a hidden input ("1" / "0"), because unchecked controls are not submitted.
 */
export function ToggleSwitch({
    checked,
    onChange,
    name,
    disabled,
    label,
}: {
    checked: boolean;
    onChange: (checked: boolean) => void;
    name?: string;
    disabled?: boolean;
    label: string;
}) {
    return (
        <>
            {name && (
                <input type="hidden" name={name} value={checked ? '1' : '0'} />
            )}
            <button
                type="button"
                role="switch"
                aria-checked={checked}
                aria-label={label}
                disabled={disabled}
                onClick={() => onChange(!checked)}
                className={cn(
                    'relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors disabled:cursor-not-allowed disabled:opacity-50',
                    checked ? 'bg-primary' : 'bg-input',
                )}
            >
                <span
                    className={cn(
                        'inline-block size-5 rounded-full bg-white shadow transition-transform',
                        checked ? 'translate-x-5.5' : 'translate-x-0.5',
                    )}
                />
            </button>
        </>
    );
}

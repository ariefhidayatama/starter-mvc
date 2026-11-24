import { Checkbox } from '@/components/ui/checkbox';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { PermissionGroup } from '@/types';

/** Standard matrix columns. Other actions (e.g. "export") go under "Other". */
const ACTIONS = [
    { key: 'view', label: 'View' },
    { key: 'create', label: 'Create' },
    { key: 'update', label: 'Update' },
    { key: 'delete', label: 'Delete' },
] as const;

const STANDARD = ACTIONS.map((a) => a.key as string);

/**
 * Checkbox matrix: rows = modules, columns = actions.
 *
 * Checked values are submitted as hidden inputs name="permissions[]",
 * so the matrix works directly inside an Inertia <Form>.
 */
export function PermissionMatrix({
    groups,
    value,
    onChange,
    disabled = false,
}: {
    groups: PermissionGroup[];
    value: string[];
    onChange: (next: string[]) => void;
    disabled?: boolean;
}) {
    const toggle = (name: string, checked: boolean) =>
        onChange(checked ? [...value, name] : value.filter((p) => p !== name));

    const toggleModule = (group: PermissionGroup, checked: boolean) => {
        const names = group.permissions.map((p) => p.name);
        const others = value.filter((p) => !names.includes(p));
        onChange(checked ? [...others, ...names] : others);
    };

    return (
        <div className="rounded-lg border">
            {value.map((name) => (
                <input
                    key={name}
                    type="hidden"
                    name="permissions[]"
                    value={name}
                />
            ))}

            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead className="w-48">Module</TableHead>
                        {ACTIONS.map((a) => (
                            <TableHead key={a.key} className="text-center">
                                {a.label}
                            </TableHead>
                        ))}
                        <TableHead>Other</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {groups.map((group) => {
                        const byAction = Object.fromEntries(
                            group.permissions.map((p) => [p.action, p]),
                        );
                        const extras = group.permissions.filter(
                            (p) => !STANDARD.includes(p.action),
                        );
                        const allChecked = group.permissions.every((p) =>
                            value.includes(p.name),
                        );

                        return (
                            <TableRow key={group.module}>
                                <TableCell>
                                    <label className="flex items-center gap-2 font-medium">
                                        <Checkbox
                                            checked={allChecked}
                                            disabled={disabled}
                                            onCheckedChange={(c) =>
                                                toggleModule(group, c === true)
                                            }
                                            aria-label={`Select all ${group.label}`}
                                        />
                                        {group.label}
                                    </label>
                                </TableCell>

                                {ACTIONS.map((a) => {
                                    const p = byAction[a.key];

                                    return (
                                        <TableCell
                                            key={a.key}
                                            className="text-center"
                                        >
                                            {p ? (
                                                <Checkbox
                                                    checked={value.includes(
                                                        p.name,
                                                    )}
                                                    disabled={disabled}
                                                    onCheckedChange={(c) =>
                                                        toggle(
                                                            p.name,
                                                            c === true,
                                                        )
                                                    }
                                                    title={p.description}
                                                    aria-label={p.name}
                                                />
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </TableCell>
                                    );
                                })}

                                <TableCell>
                                    <div className="flex flex-wrap gap-3">
                                        {extras.map((p) => (
                                            <label
                                                key={p.name}
                                                className="flex items-center gap-1.5"
                                                title={p.description}
                                            >
                                                <Checkbox
                                                    checked={value.includes(
                                                        p.name,
                                                    )}
                                                    disabled={disabled}
                                                    onCheckedChange={(c) =>
                                                        toggle(
                                                            p.name,
                                                            c === true,
                                                        )
                                                    }
                                                />
                                                {p.action}
                                            </label>
                                        ))}
                                        {extras.length === 0 && (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </div>
                                </TableCell>
                            </TableRow>
                        );
                    })}
                </TableBody>
            </Table>
        </div>
    );
}

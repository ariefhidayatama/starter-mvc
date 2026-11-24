import { Plus, Trash2 } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { CrudGeneratorOptions } from '@/types';

export type FieldRow = {
    key: number;
    name: string;
    type: string;
    required: boolean;
    options: string;
    /** Short text only; '' = 255. */
    length: string;
};

const TYPE_LABELS: Record<string, string> = {
    string: 'Short text',
    text: 'Long text',
    integer: 'Whole number',
    decimal: 'Decimal (money)',
    boolean: 'Yes / No',
    date: 'Date',
    datetime: 'Date & time',
    select: 'Choice list',
};

let nextKey = 1;

export const newFieldRow = (
    name = '',
    type = 'string',
    required = false,
): FieldRow => ({
    key: nextKey++,
    name,
    type,
    required,
    options: '',
    length: '',
});

/**
 * Column list for a NEW table ("Create a new table" mode), used for the
 * main table and for each new detail table.
 *
 * Inputs are named `${name}[i][name]`, e.g. crud[fields][0][name] or
 * crud[details][1][fields][0][name]; errors are read from `${errorKey}.i.attr`.
 */
export function CrudFieldsEditor({
    name,
    errorKey,
    fields,
    onChange,
    options,
    errors,
    label = 'Field',
}: {
    name: string;
    errorKey: string;
    errors: Record<string, string>;
    fields: FieldRow[];
    onChange: (fields: FieldRow[]) => void;
    options: CrudGeneratorOptions;
    /** Used in aria-labels, e.g. "Item field 2 name". */
    label?: string;
}) {
    const update = (key: number, patch: Partial<FieldRow>) =>
        onChange(fields.map((r) => (r.key === key ? { ...r, ...patch } : r)));

    const error = (i: number, attr: string) =>
        errors[`${errorKey}.${i}.${attr}`];

    return (
        <div className="space-y-3">
            <div className="hidden grid-cols-[minmax(0,1fr)_8.5rem_4.75rem_4rem_2.25rem] gap-3 px-1 text-xs font-medium text-muted-foreground uppercase sm:grid">
                <span>Column name</span>
                <span>Type</span>
                <span>Length</span>
                <span>Required</span>
                <span aria-hidden />
            </div>

            {fields.map((field, i) => (
                <div
                    key={field.key}
                    className="space-y-2 rounded-xl border bg-muted/20 p-3"
                >
                    <div className="grid items-start gap-3 sm:grid-cols-[minmax(0,1fr)_8.5rem_4.75rem_4rem_2.25rem]">
                        <div className="grid gap-1">
                            <Input
                                name={`${name}[${i}][name]`}
                                value={field.name}
                                onChange={(e) =>
                                    update(field.key, { name: e.target.value })
                                }
                                placeholder="e.g. phone_number"
                                className="bg-background font-mono"
                                aria-label={`${label} ${i + 1} name`}
                            />
                            <InputError message={error(i, 'name')} />
                        </div>

                        <div className="grid gap-1">
                            <input
                                type="hidden"
                                name={`${name}[${i}][type]`}
                                value={field.type}
                            />
                            <Select
                                value={field.type}
                                onValueChange={(type) =>
                                    update(field.key, {
                                        type,
                                        required:
                                            type === 'boolean'
                                                ? false
                                                : field.required,
                                    })
                                }
                            >
                                <SelectTrigger
                                    className="w-full bg-background"
                                    aria-label={`${label} ${i + 1} type`}
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {options.types.map((t) => (
                                        <SelectItem key={t} value={t}>
                                            {TYPE_LABELS[t] ?? t}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={error(i, 'type')} />
                        </div>

                        {/* Length: only for Short text (varchar). Empty = 255. */}
                        <div className="grid gap-1">
                            {field.type === 'string' ? (
                                <Input
                                    name={`${name}[${i}][length]`}
                                    type="number"
                                    inputMode="numeric"
                                    min={1}
                                    max={options.maxStringLength}
                                    step={1}
                                    value={field.length}
                                    onChange={(e) =>
                                        update(field.key, {
                                            length: e.target.value,
                                        })
                                    }
                                    placeholder="255"
                                    className="bg-background tabular-nums"
                                    aria-label={`${label} ${i + 1} max length`}
                                />
                            ) : (
                                <span
                                    className="flex h-9 items-center px-3 text-sm text-muted-foreground"
                                    title="Only Short text has a length"
                                >
                                    —
                                </span>
                            )}
                            <InputError message={error(i, 'length')} />
                        </div>

                        <label className="flex h-9 items-center gap-2 text-sm">
                            <Checkbox
                                checked={field.required}
                                disabled={field.type === 'boolean'}
                                onCheckedChange={(checked) =>
                                    update(field.key, {
                                        required: checked === true,
                                    })
                                }
                                aria-label={`${label} ${i + 1} required`}
                            />
                            <span className="sm:sr-only">Required</span>
                            <input
                                type="hidden"
                                name={`${name}[${i}][required]`}
                                value={field.required ? '1' : '0'}
                            />
                        </label>

                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-9 text-muted-foreground hover:text-destructive"
                            disabled={fields.length === 1}
                            onClick={() =>
                                onChange(
                                    fields.filter((r) => r.key !== field.key),
                                )
                            }
                            aria-label={`Remove ${label.toLowerCase()} ${i + 1}`}
                        >
                            <Trash2 />
                        </Button>
                    </div>

                    {field.type === 'select' && (
                        <div className="grid gap-1">
                            <Input
                                name={`${name}[${i}][options]`}
                                value={field.options}
                                onChange={(e) =>
                                    update(field.key, {
                                        options: e.target.value,
                                    })
                                }
                                placeholder="Choices, separated by commas: draft, published, archived"
                                className="bg-background"
                                aria-label={`${label} ${i + 1} choices`}
                            />
                            <InputError message={error(i, 'options')} />
                        </div>
                    )}
                </div>
            ))}

            <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={fields.length >= options.maxFields}
                onClick={() => onChange([...fields, newFieldRow()])}
            >
                <Plus /> Add field
            </Button>
        </div>
    );
}

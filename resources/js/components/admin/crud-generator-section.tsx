import { Database, ListTree, Plus, Table2, Trash2, Wand2 } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import {
    CrudFieldsEditor,
    newFieldRow,
} from '@/components/admin/crud-fields-editor';
import type { FieldRow } from '@/components/admin/crud-fields-editor';
import { ExistingTablePicker } from '@/components/admin/existing-table-picker';
import InputError from '@/components/input-error';
import { ToggleSwitch } from '@/components/toggle-switch';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type { CrudGeneratorOptions } from '@/types';

/** A compact radio card. */
function ModeOption({
    selected,
    onSelect,
    icon: Icon,
    title,
    description,
}: {
    selected: boolean;
    onSelect: () => void;
    icon: LucideIcon;
    title: string;
    description: string;
}) {
    return (
        <button
            type="button"
            role="radio"
            aria-checked={selected}
            onClick={onSelect}
            className={cn(
                'flex items-start gap-3 rounded-xl border p-3 text-left transition-all hover:border-primary/50',
                selected && 'border-primary bg-primary/5 ring-1 ring-primary',
            )}
        >
            <Icon
                className={cn(
                    'mt-0.5 size-4 shrink-0 text-muted-foreground',
                    selected && 'text-primary',
                )}
            />
            <span className="space-y-0.5">
                <span className="block text-sm font-medium">{title}</span>
                <span className="block text-xs text-muted-foreground">
                    {description}
                </span>
            </span>
        </button>
    );
}

/** Radix Select cannot use "" as a value. */
const APP_DB = '__app';

/**
 * "Generate CRUD page" (development only).
 *
 * When switched on, saving the menu also generates a full CRUD module
 * (migration, model, controller, React pages, test) and links the menu
 * to it: URL /admin/<name> and the <name>.view permission.
 */
export function CrudGeneratorSection({
    options,
    enabled,
    onEnabledChange,
    errors,
    token,
}: {
    options: CrudGeneratorOptions;
    enabled: boolean;
    onEnabledChange: (enabled: boolean) => void;
    errors: Record<string, string>;
    /** Lets a page reloaded by Vite ask the server how the generation went. */
    token: string;
}) {
    const [fields, setFields] = useState<FieldRow[]>(() => [
        newFieldRow('name', 'string', true),
    ]);
    const [mode, setMode] = useState<'new' | 'existing'>('new');
    /** New table: add a "ulid" column for admin URLs (the project default). */
    const [ulid, setUlid] = useState(true);
    /** Also generate /api/v1/<name> (same permissions as the admin pages). */
    const [api, setApi] = useState(false);
    /** '' = the app database. */
    const [connection, setConnection] = useState('');

    return (
        <Card className={enabled ? 'border-primary/50' : undefined}>
            <CardHeader className="flex-row items-start gap-3">
                <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Wand2 className="size-4.5" />
                </div>
                <div className="flex-1 space-y-1">
                    <CardTitle className="flex flex-wrap items-center gap-2">
                        Generate CRUD page
                        <span className="rounded-full bg-warning/15 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-warning">
                            Development only
                        </span>
                    </CardTitle>
                    <CardDescription>
                        Also create the database table, model, controller and
                        list / add / edit pages, and link this menu to them.
                    </CardDescription>
                </div>
                <ToggleSwitch
                    name="crud[enabled]"
                    checked={enabled}
                    onChange={onEnabledChange}
                    label="Generate CRUD page"
                />
                <input type="hidden" name="crud[token]" value={token} />
            </CardHeader>

            {enabled && (
                <CardContent className="space-y-5">
                    <div className="grid gap-2">
                        <Label htmlFor="crud-name" required>
                            Resource name
                        </Label>
                        <Input
                            id="crud-name"
                            name="crud[name]"
                            placeholder="Same as the title, e.g. Suppliers"
                            className="sm:max-w-sm"
                        />
                        <p className="text-xs text-muted-foreground">
                            Used for the table, URL and permissions, e.g.{' '}
                            <strong>Suppliers</strong> →{' '}
                            <code>/admin/suppliers</code> &amp;{' '}
                            <code>suppliers.view</code>.
                        </p>
                        <InputError message={errors['crud.name']} />
                    </div>

                    {/* Target database: where the table is created / read. */}
                    <input
                        type="hidden"
                        name="crud[connection]"
                        value={connection}
                    />
                    {options.connections.length > 1 && (
                        <div className="grid gap-2">
                            <Label htmlFor="crud-connection" required>
                                Database
                            </Label>
                            <Select
                                value={connection || APP_DB}
                                onValueChange={(v) =>
                                    setConnection(v === APP_DB ? '' : v)
                                }
                            >
                                <SelectTrigger
                                    id="crud-connection"
                                    className="w-full sm:max-w-sm"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {options.connections.map((c) => (
                                        <SelectItem
                                            key={c.value || APP_DB}
                                            value={c.value || APP_DB}
                                        >
                                            {c.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <p className="text-xs text-muted-foreground">
                                {connection
                                    ? 'The model uses this connection; a new table is created there. Users, roles & menus stay in the app database.'
                                    : 'The database the app itself uses.'}
                            </p>
                            <InputError message={errors['crud.connection']} />
                        </div>
                    )}

                    <div className="grid gap-2">
                        <span className="text-sm font-medium">
                            Database table
                        </span>
                        <div
                            role="radiogroup"
                            aria-label="Database table"
                            className="grid gap-2 sm:grid-cols-2"
                        >
                            <ModeOption
                                selected={mode === 'new'}
                                onSelect={() => setMode('new')}
                                icon={Table2}
                                title="Create a new table"
                                description="Define the columns below."
                            />
                            <ModeOption
                                selected={mode === 'existing'}
                                onSelect={() => setMode('existing')}
                                icon={Database}
                                title="Use an existing table"
                                description="Columns are read from the table."
                            />
                        </div>
                    </div>

                    {mode === 'existing' && (
                        <ExistingTablePicker
                            key={connection}
                            connection={connection}
                            errors={errors}
                        />
                    )}

                    {mode === 'new' && (
                        <>
                            <fieldset className="space-y-3">
                                <legend className="mb-3 text-sm font-medium">
                                    Fields{' '}
                                    <span className="font-normal text-muted-foreground">
                                        (id and timestamps are added
                                        automatically)
                                    </span>
                                </legend>
                                <CrudFieldsEditor
                                    name="crud[fields]"
                                    errorKey="crud.fields"
                                    fields={fields}
                                    onChange={setFields}
                                    options={options}
                                    errors={errors}
                                />
                                <InputError message={errors['crud.fields']} />
                            </fieldset>

                            <NewDetailTables
                                options={options}
                                errors={errors}
                            />

                            <div className="flex items-start gap-3 rounded-xl border p-3">
                                <div className="flex-1 space-y-0.5">
                                    <span className="block text-sm font-medium">
                                        ULID in URLs
                                    </span>
                                    <span className="block text-xs text-muted-foreground">
                                        Adds a <code>ulid</code> column, so edit
                                        links look like{' '}
                                        <code>/admin/…/01jd8q3x…/edit</code>{' '}
                                        instead of <code>/admin/…/5/edit</code>.
                                        Forgot it? Add it later with{' '}
                                        <code>
                                            php artisan make:ulid-migration
                                            &lt;table&gt;
                                        </code>
                                        .
                                    </span>
                                </div>
                                <ToggleSwitch
                                    name="crud[ulid]"
                                    checked={ulid}
                                    onChange={setUlid}
                                    label="ULID in URLs"
                                />
                            </div>
                        </>
                    )}

                    <div className="flex items-start gap-3 rounded-xl border p-3">
                        <div className="flex-1 space-y-0.5">
                            <span className="block text-sm font-medium">
                                Also generate REST API
                            </span>
                            <span className="block text-xs text-muted-foreground">
                                Adds <code>/api/v1/&lt;name&gt;</code> (list,
                                show, create, update, delete) for mobile apps
                                and other systems. It uses the same{' '}
                                <code>
                                    &lt;name&gt;.view / create / update / delete
                                </code>{' '}
                                permissions as these pages, and the role also
                                needs <code>api.access</code>. Add it later with{' '}
                                <code>php artisan crud:api &lt;table&gt;</code>.
                            </span>
                        </div>
                        <ToggleSwitch
                            name="crud[api]"
                            checked={api}
                            onChange={setApi}
                            label="Also generate REST API"
                        />
                    </div>

                    <Alert>
                        <Wand2 />
                        <AlertDescription>
                            <p>
                                The URL and visibility are set for you: the menu
                                follows the new{' '}
                                <code className="font-semibold">
                                    &lt;name&gt;.view
                                </code>{' '}
                                permission (admin only until you grant it on the
                                Permissions page). Generated files are plain
                                code you can edit. Run{' '}
                                <code className="font-semibold">
                                    npm run build
                                </code>{' '}
                                before deploying.
                            </p>
                        </AlertDescription>
                    </Alert>
                </CardContent>
            )}
        </Card>
    );
}

type DetailTable = { key: number; name: string; fields: FieldRow[] };

let nextDetailKey = 1;

const newDetail = (): DetailTable => ({
    key: nextDetailKey++,
    name: '',
    fields: [newFieldRow('', 'string', true)],
});

/**
 * "Create a new table" mode: optional DETAIL tables (master–detail, one
 * level). Each gets its own table, created by the same migration with a
 * foreign key to the main table, e.g. "items" for Purchase orders →
 * purchase_order_items.purchase_order_id. The Add page then saves the main
 * row and all detail rows in one transaction.
 */
function NewDetailTables({
    options,
    errors,
}: {
    options: CrudGeneratorOptions;
    errors: Record<string, string>;
}) {
    const [details, setDetails] = useState<DetailTable[]>([]);

    const update = (key: number, patch: Partial<DetailTable>) =>
        setDetails((rows) =>
            rows.map((d) => (d.key === key ? { ...d, ...patch } : d)),
        );

    return (
        <fieldset className="space-y-3">
            <legend className="mb-1 text-sm font-medium">
                Detail tables{' '}
                <span className="font-normal text-muted-foreground">(opt)</span>
            </legend>
            <p className="text-xs text-muted-foreground">
                Rows that belong to one record, e.g. the <strong>items</strong>{' '}
                of a purchase order. Each detail gets its own table with a link
                column to this one, and the Add page saves everything in one
                transaction.
            </p>

            {details.map((detail, i) => (
                <div
                    key={detail.key}
                    className="space-y-4 rounded-xl border border-dashed p-4"
                >
                    <div className="flex items-start gap-3">
                        <div className="grid flex-1 gap-2">
                            <label
                                htmlFor={`crud-detail-${detail.key}`}
                                className="flex items-center gap-2 text-sm font-medium"
                            >
                                <ListTree className="size-4 text-muted-foreground" />
                                Detail name
                            </label>
                            <Input
                                id={`crud-detail-${detail.key}`}
                                name={`crud[details][${i}][name]`}
                                value={detail.name}
                                onChange={(e) =>
                                    update(detail.key, {
                                        name: e.target.value,
                                    })
                                }
                                placeholder="e.g. items"
                                className="sm:max-w-xs"
                            />
                            <p className="text-xs text-muted-foreground">
                                Used for the table, relation and URL, e.g.{' '}
                                <strong>items</strong> for Purchase orders →{' '}
                                <code>purchase_order_items</code> with{' '}
                                <code>purchase_order_id</code>.
                            </p>
                            <InputError
                                message={errors[`crud.details.${i}.name`]}
                            />
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-9 text-muted-foreground hover:text-destructive"
                            onClick={() =>
                                setDetails((rows) =>
                                    rows.filter((d) => d.key !== detail.key),
                                )
                            }
                            aria-label={`Remove detail table ${i + 1}`}
                        >
                            <Trash2 />
                        </Button>
                    </div>

                    <CrudFieldsEditor
                        name={`crud[details][${i}][fields]`}
                        errorKey={`crud.details.${i}.fields`}
                        fields={detail.fields}
                        onChange={(fields) => update(detail.key, { fields })}
                        options={options}
                        errors={errors}
                        label={`Detail ${i + 1} field`}
                    />
                    <InputError message={errors[`crud.details.${i}.fields`]} />
                </div>
            ))}

            <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={details.length >= options.maxDetails}
                onClick={() => setDetails((rows) => [...rows, newDetail()])}
            >
                <Plus /> Add detail table
            </Button>
            <InputError message={errors['crud.details']} />
        </fieldset>
    );
}

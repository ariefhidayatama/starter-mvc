import { CircleAlert, Link2, Loader2, Lock, Rows3, Wand2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import MenuController from '@/actions/App/Http/Controllers/Admin/MenuController';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Checkbox } from '@/components/ui/checkbox';
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
import type { CrudColumn, CrudTable } from '@/types';

/** Max detail tables per record; same as CrudGenerator::MAX_DETAILS. */
const MAX_DETAILS = 5;

/** A chosen detail table and its optional name (relation, URL & form key). */
type DetailChoice = { table: string; name: string };

const TYPE_LABELS: Record<string, string> = {
    string: 'Short text',
    text: 'Long text',
    integer: 'Whole number',
    decimal: 'Decimal',
    boolean: 'Yes / No',
    date: 'Date',
    datetime: 'Date & time',
    select: 'Choice list',
};

function describe(column: CrudColumn): string {
    const type = TYPE_LABELS[column.type] ?? column.type;

    if (column.type === 'select') {
        return `${type}: ${column.options.join(', ')}`;
    }

    if (column.type === 'string' && column.max) {
        return `${type} (max ${column.max})`;
    }

    return type;
}

async function getJson<T>(url: string): Promise<T> {
    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
    });
    const body = (await response.json()) as T & { message?: string };

    if (!response.ok) {
        throw new Error(body.message ?? `Request failed (${response.status})`);
    }

    return body;
}

/**
 * "Use an existing table": pick a table in the chosen database, the
 * columns that appear in the form, and optionally a DETAIL table
 * (master–detail). The database is chosen by the parent section; the
 * parent remounts this picker (key) when it changes.
 *
 * Tables & columns are loaded on demand, so opening the page does not
 * query every table of a large database.
 */
export function ExistingTablePicker({
    connection: connectionParam,
    errors,
    target = 'pages',
}: {
    /** '' = the app database, otherwise e.g. "wmsreports". */
    connection: string;
    errors: Record<string, string>;
    /** What is generated: admin pages (menu form) or only a REST API. */
    target?: 'pages' | 'api';
}) {
    const forApi = target === 'api';
    const [tables, setTables] = useState<string[] | null>(null);
    const [tableName, setTableName] = useState('');
    const [table, setTable] = useState<CrudTable | null>(null);
    const [selected, setSelected] = useState<string[]>([]);
    const [details, setDetails] = useState<DetailChoice[]>([]);
    const [loading, setLoading] = useState(false);
    const [loadError, setLoadError] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;

        getJson<{ tables: string[] }>(
            MenuController.generatorTables.url({
                query: { connection: connectionParam },
            }),
        )
            .then((body) => !cancelled && setTables(body.tables))
            .catch((e: Error) => !cancelled && setLoadError(e.message));

        return () => {
            cancelled = true;
        };
    }, [connectionParam]);

    const chooseTable = async (name: string) => {
        setTableName(name);
        setTable(null);
        setDetails([]);
        setLoading(true);
        setLoadError(null);

        try {
            const info = await getJson<CrudTable>(
                MenuController.generatorTable.url({
                    query: { connection: connectionParam, table: name },
                }),
            );
            setTable(info);
            // Start with every supported column ticked.
            setSelected(info.fields.map((f) => f.name));
        } catch (e) {
            setLoadError((e as Error).message);
        } finally {
            setLoading(false);
        }
    };

    const toggle = (name: string) =>
        setSelected((current) =>
            current.includes(name)
                ? current.filter((c) => c !== name)
                : [...current, name],
        );

    const toggleDetail = (name: string) =>
        setDetails((current) =>
            current.some((d) => d.table === name)
                ? current.filter((d) => d.table !== name)
                : [...current, { table: name, name: '' }],
        );

    const renameDetail = (name: string, value: string) =>
        setDetails((current) =>
            current.map((d) => (d.table === name ? { ...d, name: value } : d)),
        );

    const audit = table
        ? [
              table.audit.created_by,
              table.audit.updated_by,
              table.audit.created_at,
              table.audit.updated_at,
          ].filter(Boolean)
        : [];

    return (
        <div className="space-y-4">
            <input type="hidden" name="crud[table]" value={tableName} />
            {details.map((d, index) => (
                <span key={d.table}>
                    <input
                        type="hidden"
                        name={`crud[details][${index}][table]`}
                        value={d.table}
                    />
                    <input
                        type="hidden"
                        name={`crud[details][${index}][name]`}
                        value={d.name}
                    />
                </span>
            ))}

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="crud-table" required>
                        Table
                    </Label>
                    <Select
                        value={tableName}
                        onValueChange={chooseTable}
                        disabled={tables === null}
                    >
                        <SelectTrigger id="crud-table" className="w-full">
                            <SelectValue
                                placeholder={
                                    tables === null
                                        ? 'Loading tables…'
                                        : 'Choose a table'
                                }
                            />
                        </SelectTrigger>
                        <SelectContent className="max-h-72">
                            {(tables ?? []).map((t) => (
                                <SelectItem key={t} value={t}>
                                    <span className="font-mono">{t}</span>
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors['crud.table']} />
                </div>
            </div>

            {tables !== null && tables.length === 0 && (
                <Alert>
                    <CircleAlert />
                    <AlertDescription>
                        <p>
                            No tables available here. Tables used by the app
                            (users, products, menus, …) and tables that already
                            have generated pages are not listed.
                        </p>
                        {forApi && (
                            <p>
                                Products &amp; categories already have a
                                hand-written REST API (
                                <code>/api/v1/products</code>,{' '}
                                <code>/api/v1/categories</code>). Tables of
                                another database appear here after it is added
                                to <code>config/crud.php</code> →{' '}
                                <code>connections</code>.
                            </p>
                        )}
                    </AlertDescription>
                </Alert>
            )}

            {loading && (
                <p className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Loader2 className="size-4 animate-spin" /> Reading the
                    table structure…
                </p>
            )}

            {(loadError || table?.problem) && (
                <Alert variant="destructive">
                    <CircleAlert />
                    <AlertDescription>
                        <p>{loadError ?? table?.problem}</p>
                    </AlertDescription>
                </Alert>
            )}

            {table && !table.problem && (
                <fieldset className="space-y-2">
                    <legend className="mb-2 text-sm font-medium">
                        {forApi ? 'Columns in the API' : 'Columns in the form'}
                    </legend>

                    {table.fields.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            This table has no columns the generator supports.
                        </p>
                    )}

                    <div className="divide-y rounded-xl border">
                        {table.fields.map((column) => {
                            const checked =
                                column.required ||
                                selected.includes(column.name);

                            return (
                                <label
                                    key={column.name}
                                    className={cn(
                                        'flex items-center gap-3 px-3 py-2.5 text-sm',
                                        !column.required && 'cursor-pointer',
                                    )}
                                >
                                    <Checkbox
                                        checked={checked}
                                        disabled={column.required}
                                        onCheckedChange={() =>
                                            toggle(column.name)
                                        }
                                    />
                                    {checked && (
                                        <input
                                            type="hidden"
                                            name="crud[columns][]"
                                            value={column.name}
                                        />
                                    )}
                                    <span className="min-w-0 flex-1">
                                        <span className="font-mono font-medium">
                                            {column.name}
                                        </span>
                                        <span className="block truncate text-xs text-muted-foreground">
                                            {describe(column)}
                                            {column.references && (
                                                <span className="ml-1 inline-flex items-center gap-0.5">
                                                    <Link2 className="size-3" />
                                                    {column.references}
                                                </span>
                                            )}
                                        </span>
                                    </span>
                                    {column.required && (
                                        <span
                                            className="inline-flex items-center gap-1 text-xs text-muted-foreground"
                                            title="NOT NULL without a default value"
                                        >
                                            <Lock className="size-3" />
                                            Required
                                        </span>
                                    )}
                                </label>
                            );
                        })}
                    </div>
                    <InputError message={errors['crud.columns']} />

                    <div className="space-y-1 rounded-xl bg-muted/40 p-3 text-xs text-muted-foreground">
                        <p>
                            <span className="font-medium text-foreground">
                                Filled automatically:
                            </span>{' '}
                            id
                            {audit.length > 0 && (
                                <>
                                    , <code>{audit.join(', ')}</code> (current
                                    time & the signed-in user&apos;s name)
                                </>
                            )}
                            .
                        </p>
                        {table.skipped.length > 0 && (
                            <p>
                                <span className="font-medium text-foreground">
                                    Not included:
                                </span>{' '}
                                {table.skipped
                                    .map((s) => `${s.name} (${s.reason})`)
                                    .join('; ')}
                            </p>
                        )}
                    </div>
                </fieldset>
            )}

            {table && !table.problem && table.children.length > 0 && (
                <fieldset className="space-y-3 rounded-xl border border-dashed p-4">
                    <legend className="flex items-center gap-2 px-1 text-sm font-medium">
                        <Rows3 className="size-4 text-primary" />
                        Detail tables{' '}
                        <span className="font-normal text-muted-foreground">
                            (opt, master–detail, up to {MAX_DETAILS})
                        </span>
                    </legend>

                    <div className="divide-y rounded-xl border">
                        {table.children.map((c) => {
                            const index = details.findIndex(
                                (d) => d.table === c.table,
                            );
                            const choice = index >= 0 ? details[index] : null;
                            const full =
                                !choice && details.length >= MAX_DETAILS;

                            return (
                                <div
                                    key={c.table}
                                    className="space-y-2 px-3 py-2.5"
                                >
                                    <label
                                        className={cn(
                                            'flex items-center gap-3 text-sm',
                                            !full && 'cursor-pointer',
                                        )}
                                    >
                                        <Checkbox
                                            checked={choice !== null}
                                            disabled={full}
                                            onCheckedChange={() =>
                                                toggleDetail(c.table)
                                            }
                                        />
                                        <span className="min-w-0 flex-1">
                                            <span className="font-mono font-medium">
                                                {c.table}
                                            </span>{' '}
                                            <span className="text-xs text-muted-foreground">
                                                via {c.column}
                                            </span>
                                            <span className="block truncate text-xs text-muted-foreground">
                                                {c.fields
                                                    .map((f) => f.name)
                                                    .join(', ')}
                                            </span>
                                        </span>
                                    </label>

                                    {c.problem && (
                                        <p className="pl-7 text-sm text-destructive">
                                            {c.problem}
                                        </p>
                                    )}

                                    {choice && (
                                        <div className="grid gap-1 pl-7 sm:max-w-sm">
                                            <Label
                                                htmlFor={`crud-detail-${c.table}`}
                                                className="text-xs"
                                                optional
                                            >
                                                Detail name
                                            </Label>
                                            <Input
                                                id={`crud-detail-${c.table}`}
                                                value={choice.name}
                                                onChange={(e) =>
                                                    renameDetail(
                                                        c.table,
                                                        e.target.value,
                                                    )
                                                }
                                                maxLength={40}
                                                placeholder={
                                                    details.length === 1
                                                        ? 'details'
                                                        : c.table
                                                }
                                                className="h-8"
                                            />
                                            <InputError
                                                message={
                                                    errors[
                                                        `crud.details.${index}.table`
                                                    ] ??
                                                    errors[
                                                        `crud.details.${index}.name`
                                                    ]
                                                }
                                            />
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                    <InputError message={errors['crud.details']} />

                    {details.length > 0 && forApi && (
                        <p className="flex items-start gap-2 text-xs text-muted-foreground">
                            <Wand2 className="mt-0.5 size-3.5 shrink-0" />
                            <span>
                                <code>POST</code> accepts the record and its
                                detail rows in one request, saved in{' '}
                                <strong>one transaction</strong>.{' '}
                                <code>GET /{'{id}'}</code> returns the record
                                with its rows, and <code>DELETE</code> removes
                                both. The detail name becomes the JSON key, e.g.
                                “Items” → <code>"items": [...]</code>.
                            </span>
                        </p>
                    )}

                    {details.length > 0 && !forApi && (
                        <p className="flex items-start gap-2 text-xs text-muted-foreground">
                            <Wand2 className="mt-0.5 size-3.5 shrink-0" />
                            <span>
                                The <strong>Add</strong> form gets a rows table
                                per detail: the record and all rows are saved in{' '}
                                <strong>one transaction</strong>. The edit page
                                gets a section per detail to list, add, edit &
                                delete rows. The link column is set
                                automatically, and deleting a record also
                                deletes its details in one transaction. The
                                detail name becomes the relation & URL, e.g.
                                “Items” → <code>items()</code>,{' '}
                                <code>/items</code>.
                            </span>
                        </p>
                    )}
                </fieldset>
            )}

            <p className="text-xs text-muted-foreground">
                No migration is created and the table is never dropped by the
                generator.
            </p>
        </div>
    );
}

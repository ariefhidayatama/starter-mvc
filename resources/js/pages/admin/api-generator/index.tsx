import { Form, Head, Link, router } from "@inertiajs/react";
import {
    BookOpen,
    CircleAlert,
    Database,
    ExternalLink,
    KeyRound,
    Loader2,
    Plug,
    Plus,
    Trash2,
    Wand2,
} from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";
import ApiGeneratorController from "@/actions/App/Http/Controllers/Admin/ApiGeneratorController";
import {
    CrudGeneratingOverlay,
    forgetPendingCrud,
    newCrudToken,
    rememberPendingCrud,
} from "@/components/admin/crud-generating-overlay";
import { ExistingTablePicker } from "@/components/admin/existing-table-picker";
import { ConfirmDialog } from "@/components/confirm-dialog";
import InputError from "@/components/input-error";
import { PageHeader } from "@/components/page-header";
import { StatusBadge } from "@/components/status-badge";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import admin from "@/routes/admin";

/** A module made by the CRUD generator (storage/app/crud/<table>.json). */
type GeneratedModule = {
    table: string;
    name: string;
    /** false = API-only module (no admin pages). */
    web: boolean;
    api: boolean;
    api_url: string | null;
    /** Admin page URL, e.g. /admin/messages. */
    url: string | null;
    db_table: string;
    db_connection: string | null;
    /** Detail tables (master–detail). */
    details: string[];
};

type Connection = { value: string; label: string };

/** Radix Select cannot use "" as a value. */
const APP_DB = "__app__";

/**
 * Admin → API Generator (development only).
 *
 * 1. Generated modules: add / remove their REST API.
 * 2. Existing tables (e.g. ERP tables from an old project): create an
 *    API-only module, without admin pages or a menu.
 *
 * Who may call each API is set on the Permissions page.
 */
export default function ApiGeneratorIndex({
    enabled,
    modules,
    connections,
}: {
    enabled: boolean;
    modules: GeneratedModule[];
    connections: Connection[];
}) {
    const [busy, setBusy] = useState(false);
    const [token] = useState(newCrudToken);
    /** '' = the app database. */
    const [connection, setConnection] = useState("");

    /** Add / remove an API: writes route files, so Vite may reload the page. */
    const act = (method: "post" | "delete", url: string) => {
        const actionToken = newCrudToken();
        rememberPendingCrud(actionToken);
        setBusy(true);

        router.visit(url, {
            method,
            data: { token: actionToken },
            preserveScroll: true,
            onError: (errors) =>
                toast.error(
                    Object.values(errors)[0] ?? "Something went wrong.",
                ),
            onFinish: () => {
                forgetPendingCrud();
                setBusy(false);
            },
        });
    };

    const withoutApi = modules.filter((m) => !m.api).length;

    return (
        <>
            <Head title="API Generator" />

            <CrudGeneratingOverlay
                submitting={busy}
                label="the REST API"
                description="Writing files and refreshing routes."
            />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    icon={Plug}
                    title="API Generator"
                    description="Create REST APIs (/api/v1/…) for generated modules and for tables you already have. Who may call them is set on the Permissions page."
                    actions={
                        <Button variant="outline" asChild>
                            {/* Scramble page (not Inertia): open in a new tab. */}
                            <a
                                href="/docs/api"
                                target="_blank"
                                rel="noreferrer"
                            >
                                <BookOpen /> API Docs
                                <ExternalLink className="size-3.5 opacity-60" />
                            </a>
                        </Button>
                    }
                />

                {!enabled && (
                    <Alert variant="destructive">
                        <CircleAlert />
                        <AlertDescription>
                            <p>
                                The generator only runs in local development
                                (APP_ENV=local). Generate APIs on your machine,
                                then deploy the code.
                            </p>
                        </AlertDescription>
                    </Alert>
                )}

                <Alert>
                    <KeyRound />
                    <AlertDescription>
                        <p>
                            Each API uses the module&apos;s own permissions (
                            <code>
                                &lt;table&gt;.view / create / update / delete
                            </code>
                            ), the same as its admin pages, and the role also
                            needs <code>api.access</code>. Grant them on the{" "}
                            <Link
                                href={admin.permissions.index()}
                                className="font-medium text-primary underline-offset-4 hover:underline"
                            >
                                Permissions
                            </Link>{" "}
                            page. Hand-written APIs (products, categories) are
                            not listed here.
                        </p>
                    </AlertDescription>
                </Alert>

                {enabled && (
                    <>
                        <Card className="gap-0 overflow-hidden py-0">
                            <CardHeader className="border-b py-4">
                                <CardTitle>Generated modules</CardTitle>
                                <CardDescription>
                                    {modules.length === 0
                                        ? "No generated modules yet."
                                        : `${modules.length} module(s), ${withoutApi} without a REST API.`}
                                </CardDescription>
                            </CardHeader>

                            {modules.length > 0 && (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b bg-muted/40 text-left text-xs tracking-wide text-muted-foreground uppercase">
                                                <th className="px-4 py-3 font-medium">
                                                    Module
                                                </th>
                                                <th className="px-4 py-3 font-medium">
                                                    Table
                                                </th>
                                                <th className="px-4 py-3 font-medium">
                                                    Admin pages
                                                </th>
                                                <th className="px-4 py-3 font-medium">
                                                    REST API
                                                </th>
                                                <th className="px-4 py-3" />
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {modules.map((m) => (
                                                <ModuleRow
                                                    key={m.table}
                                                    module={m}
                                                    busy={busy}
                                                    onAdd={() =>
                                                        act(
                                                            "post",
                                                            ApiGeneratorController.addApi.url(
                                                                m.table,
                                                            ),
                                                        )
                                                    }
                                                    onRemoveApi={() =>
                                                        act(
                                                            "delete",
                                                            ApiGeneratorController.removeApi.url(
                                                                m.table,
                                                            ),
                                                        )
                                                    }
                                                    onRemoveModule={() =>
                                                        act(
                                                            "delete",
                                                            ApiGeneratorController.destroy.url(
                                                                m.table,
                                                            ),
                                                        )
                                                    }
                                                />
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </Card>

                        <Card>
                            <CardHeader className="flex-row items-start gap-3">
                                <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                    <Database className="size-4.5" />
                                </div>
                                <div className="space-y-1">
                                    <CardTitle>
                                        New API from an existing table
                                    </CardTitle>
                                    <CardDescription>
                                        For tables from an old project or the
                                        ERP database. Creates the model, policy,
                                        validation, permissions and the REST API
                                        only: no admin pages, no menu, no
                                        migration.
                                    </CardDescription>
                                </div>
                            </CardHeader>

                            <CardContent>
                                <Form
                                    {...ApiGeneratorController.store.form()}
                                    className="space-y-5"
                                    onStart={() => {
                                        rememberPendingCrud(token);
                                        setBusy(true);
                                    }}
                                    onFinish={() => {
                                        forgetPendingCrud();
                                        setBusy(false);
                                    }}
                                >
                                    {({ errors, processing }) => (
                                        <>
                                            <input
                                                type="hidden"
                                                name="crud[token]"
                                                value={token}
                                            />
                                            <input
                                                type="hidden"
                                                name="crud[connection]"
                                                value={connection}
                                            />

                                            <div className="grid gap-4 sm:grid-cols-2">
                                                <div className="grid gap-2">
                                                    <Label
                                                        htmlFor="api-name"
                                                        required
                                                    >
                                                        Resource name
                                                    </Label>
                                                    <Input
                                                        id="api-name"
                                                        name="crud[name]"
                                                        placeholder="e.g. Vendors"
                                                        maxLength={40}
                                                    />
                                                    <p className="text-xs text-muted-foreground">
                                                        Used for the URL and
                                                        permissions, e.g.{" "}
                                                        <strong>Vendors</strong>{" "}
                                                        →{" "}
                                                        <code>
                                                            /api/v1/vendors
                                                        </code>{" "}
                                                        &amp;{" "}
                                                        <code>
                                                            vendors.view
                                                        </code>
                                                        .
                                                    </p>
                                                    <InputError
                                                        message={
                                                            errors["crud.name"]
                                                        }
                                                    />
                                                </div>

                                                <div className="grid content-start gap-2">
                                                    <Label
                                                        htmlFor="api-connection"
                                                        required
                                                    >
                                                        Database
                                                    </Label>
                                                    <Select
                                                        value={
                                                            connection || APP_DB
                                                        }
                                                        onValueChange={(v) =>
                                                            setConnection(
                                                                v === APP_DB
                                                                    ? ""
                                                                    : v,
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger
                                                            id="api-connection"
                                                            className="w-full"
                                                        >
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {connections.map(
                                                                (c) => (
                                                                    <SelectItem
                                                                        key={
                                                                            c.value ||
                                                                            APP_DB
                                                                        }
                                                                        value={
                                                                            c.value ||
                                                                            APP_DB
                                                                        }
                                                                    >
                                                                        {
                                                                            c.label
                                                                        }
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                    <InputError
                                                        message={
                                                            errors[
                                                                "crud.connection"
                                                            ]
                                                        }
                                                    />
                                                </div>
                                            </div>

                                            <ExistingTablePicker
                                                key={connection}
                                                connection={connection}
                                                errors={errors}
                                                target="api"
                                            />

                                            <div className="flex flex-wrap items-center gap-3 border-t pt-5">
                                                <Button
                                                    type="submit"
                                                    disabled={
                                                        processing || busy
                                                    }
                                                    className="shadow-sm shadow-primary/30"
                                                >
                                                    {processing ? (
                                                        <Loader2 className="animate-spin" />
                                                    ) : (
                                                        <Wand2 />
                                                    )}
                                                    Generate REST API
                                                </Button>
                                                <p className="text-xs text-muted-foreground">
                                                    Creates <code>GET</code>,{" "}
                                                    <code>POST</code>,{" "}
                                                    <code>PUT</code> and{" "}
                                                    <code>DELETE</code>{" "}
                                                    endpoints. Only admin can
                                                    call them until you grant
                                                    the permissions.
                                                </p>
                                            </div>
                                        </>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>
                    </>
                )}
            </div>
        </>
    );
}

function ModuleRow({
    module: m,
    busy,
    onAdd,
    onRemoveApi,
    onRemoveModule,
}: {
    module: GeneratedModule;
    busy: boolean;
    onAdd: () => void;
    onRemoveApi: () => void;
    onRemoveModule: () => void;
}) {
    return (
        <tr className="border-b transition-colors last:border-0 hover:bg-muted/30">
            <td className="px-4 py-3">
                <p className="font-medium">{m.name}</p>
                <p className="font-mono text-xs text-muted-foreground">
                    {m.table}
                </p>
            </td>
            <td className="px-4 py-3">
                <p className="font-mono text-xs">
                    {m.db_table}
                    {m.details.length > 0 && (
                        <span className="text-muted-foreground">
                            {" "}
                            + {m.details.join(", ")}
                        </span>
                    )}
                </p>
                <p className="text-xs text-muted-foreground">
                    {m.db_connection ?? "App database"}
                </p>
            </td>
            <td className="px-4 py-3">
                {m.web && m.url ? (
                    <Link
                        href={m.url}
                        className="font-mono text-xs text-primary underline-offset-4 hover:underline"
                    >
                        {m.url}
                    </Link>
                ) : (
                    <StatusBadge tone="neutral" dot={false}>
                        API only
                    </StatusBadge>
                )}
            </td>
            <td className="px-4 py-3">
                {m.api_url ? (
                    <StatusBadge tone="success">
                        <code>{m.api_url}</code>
                    </StatusBadge>
                ) : (
                    <span className="text-xs text-muted-foreground">
                        None yet
                    </span>
                )}
            </td>
            <td className="px-4 py-3 text-right whitespace-nowrap">
                {!m.api && (
                    <Button size="sm" onClick={onAdd} disabled={busy}>
                        <Plus /> Generate API
                    </Button>
                )}

                {m.api && m.web && (
                    <ConfirmDialog
                        trigger={
                            <Button size="sm" variant="outline" disabled={busy}>
                                <Trash2 /> Remove API
                            </Button>
                        }
                        title={`Remove the REST API of ${m.name}?`}
                        description={`${m.api_url} stops working for every client that calls it. The admin pages and data are kept. Files you changed are kept too.`}
                        confirmLabel="Remove API"
                        onConfirm={(close) => {
                            close();
                            onRemoveApi();
                        }}
                    />
                )}

                {!m.web && (
                    <ConfirmDialog
                        trigger={
                            <Button size="sm" variant="outline" disabled={busy}>
                                <Trash2 /> Remove module
                            </Button>
                        }
                        title={`Remove the API module ${m.name}?`}
                        description={`Deletes its generated code (API, model, policy, validation) and its ${m.table}.* permissions. ${m.api_url ?? "The API"} stops working. The database table "${m.db_table}" and its data are kept.`}
                        confirmLabel="Remove module"
                        onConfirm={(close) => {
                            close();
                            onRemoveModule();
                        }}
                    />
                )}
            </td>
        </tr>
    );
}

ApiGeneratorIndex.layout = {
    breadcrumbs: [{ title: "API Generator", href: "/admin/api-generator" }],
};

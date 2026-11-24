import { router } from '@inertiajs/react';
import { AlertCircle, Download, FileSpreadsheet, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import type { FormEvent } from 'react';
import ProductController from '@/actions/App/Http/Controllers/Admin/ProductController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

const ACCEPT =
    '.xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv';

/**
 * "Import" button + dialog: upload an Excel/CSV file of products.
 * An existing SKU updates that product, a new SKU creates one. When any row
 * is invalid nothing is saved and the row errors are listed here.
 */
export function ProductImportDialog() {
    const [open, setOpen] = useState(false);
    const [file, setFile] = useState<File | null>(null);
    const [uploading, setUploading] = useState(false);
    const [fileError, setFileError] = useState<string | null>(null);
    const [rowErrors, setRowErrors] = useState<string[]>([]);
    const inputRef = useRef<HTMLInputElement>(null);

    const reset = () => {
        setFile(null);
        setFileError(null);
        setRowErrors([]);

        if (inputRef.current) {
            inputRef.current.value = '';
        }
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (!file) {
            setFileError('Choose an Excel or CSV file to upload.');

            return;
        }

        setUploading(true);
        setFileError(null);
        setRowErrors([]);

        router.post(
            ProductController.importExcel.url(),
            { file },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    setOpen(false);
                    reset();
                },
                onError: (errors) => {
                    setFileError(errors.file ?? null);
                    setRowErrors(
                        Object.entries(errors)
                            .filter(([key]) => key.startsWith('rows.'))
                            .sort(
                                ([a], [b]) =>
                                    Number(a.slice(5)) - Number(b.slice(5)),
                            )
                            .map(([, message]) => message),
                    );
                },
                onFinish: () => setUploading(false),
            },
        );
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                if (uploading) {
                    return;
                }

                setOpen(value);

                if (!value) {
                    reset();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button variant="outline" className="shrink-0">
                    <Upload /> Import
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogTitle>Import products</DialogTitle>
                <DialogDescription>
                    Upload an Excel (.xlsx, .xls) or CSV file. A new SKU
                    creates a product; an existing SKU updates it. If any row
                    has an error, nothing is saved.
                </DialogDescription>

                <form onSubmit={submit} className="space-y-4">
                    <a
                        href={ProductController.importTemplate.url()}
                        className="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline"
                    >
                        <Download className="size-4" /> Download template
                    </a>

                    <label
                        className={cn(
                            'flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed px-4 py-6 text-center transition-colors hover:bg-muted/50',
                            fileError
                                ? 'border-destructive/60'
                                : 'border-muted-foreground/25',
                        )}
                    >
                        <FileSpreadsheet className="size-8 text-muted-foreground" />
                        {file ? (
                            <span className="text-sm font-medium break-all">
                                {file.name}{' '}
                                <span className="font-normal text-muted-foreground">
                                    ({Math.ceil(file.size / 1024)} KB)
                                </span>
                            </span>
                        ) : (
                            <span className="text-sm text-muted-foreground">
                                Click to choose a file (max 5 MB, 1000 rows)
                            </span>
                        )}
                        <input
                            ref={inputRef}
                            type="file"
                            accept={ACCEPT}
                            className="sr-only"
                            onChange={(e) => {
                                setFile(e.target.files?.[0] ?? null);
                                setFileError(null);
                                setRowErrors([]);
                            }}
                        />
                    </label>

                    {(fileError || rowErrors.length > 0) && (
                        <div
                            role="alert"
                            className="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive"
                        >
                            {fileError && (
                                <p className="flex items-start gap-2 font-medium">
                                    <AlertCircle className="mt-0.5 size-4 shrink-0" />
                                    {fileError}
                                </p>
                            )}
                            {rowErrors.length > 0 && (
                                <ul className="mt-2 max-h-48 list-disc space-y-0.5 overflow-y-auto pl-6">
                                    {rowErrors.map((message) => (
                                        <li key={message}>{message}</li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    )}

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button
                                type="button"
                                variant="secondary"
                                disabled={uploading}
                            >
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={uploading || !file}>
                            <Upload /> {uploading ? 'Importing…' : 'Import'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

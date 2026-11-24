import { ListPlus, Rows3, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';

import type { MessageDetail } from '@/components/admin/message-detail-form';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

/*
 * Baris detail (msg_det) di form "Add message". Header + semua baris dikirim
 * sekali dan disimpan dalam SATU transaksi (MessageController@store).
 *
 * Input sengaja uncontrolled (seperti MessageForm): nilainya dibaca <Form>
 * Inertia dari DOM lewat nama bertingkat details[i][kolom]. `key` React per
 * baris tetap stabil, jadi menghapus baris di tengah tidak menggeser isian
 * baris lain; hanya nomor indeks di atribut `name` yang ikut berubah.
 */

type DetailColumn = {
    /** Kolom yang diisi user; id, msg_id & tanggal diisi server. */
    name: Exclude<
        keyof MessageDetail,
        'id' | 'msg_id' | 'created_date' | 'updated_date'
    >;
    label: string;
    required?: boolean;
    maxLength?: number;
    numeric?: boolean;
    /** Lebar minimum kolom (Tailwind), agar tabel enak diisi. */
    width: string;
};

/** Sama dengan SaveMessageDetailRequest::fieldRules(). */
const COLUMNS: DetailColumn[] = [
    {
        name: 'trans_code',
        label: 'Trans code',
        required: true,
        maxLength: 10,
        width: 'min-w-28',
    },
    {
        name: 'trans_no',
        label: 'Trans no',
        required: true,
        maxLength: 30,
        width: 'min-w-36',
    },
    {
        name: 'ref_no',
        label: 'Ref no',
        required: true,
        maxLength: 30,
        width: 'min-w-36',
    },
    {
        name: 'po_no',
        label: 'Po no',
        required: true,
        maxLength: 30,
        width: 'min-w-36',
    },
    { name: 'prod_code', label: 'Prod code', maxLength: 30, width: 'min-w-36' },
    { name: 'loc_code', label: 'Loc code', maxLength: 30, width: 'min-w-32' },
    { name: 'pallet_no', label: 'Pallet no', maxLength: 30, width: 'min-w-32' },
    { name: 'lot_no', label: 'Lot no', maxLength: 30, width: 'min-w-32' },
    {
        name: 'inv_status',
        label: 'Inv status',
        maxLength: 30,
        width: 'min-w-28',
    },
    { name: 'qty', label: 'Qty', numeric: true, width: 'min-w-24' },
];

export function MessageDetailRows({
    errors,
    max,
}: {
    /** `errors` dari <Form>; kunci baris detail: "details.0.trans_code". */
    errors: Partial<Record<string, string>>;
    max: number;
}) {
    // Satu baris kosong di awal, karena tujuan form ini memang mengisi detail.
    const nextKey = useRef(1);
    const [rows, setRows] = useState<number[]>([0]);
    const tableRef = useRef<HTMLDivElement>(null);

    const addRow = () => {
        const key = nextKey.current++;
        setRows((current) => [...current, key]);
        // Fokus ke sel pertama baris baru setelah dirender.
        requestAnimationFrame(() =>
            tableRef.current
                ?.querySelector<HTMLInputElement>(
                    `#detail-${key}-${COLUMNS[0].name}`,
                )
                ?.focus(),
        );
    };

    const removeRow = (key: number) =>
        setRows((current) => current.filter((k) => k !== key));

    const listError = errors.details;

    return (
        <Card className="max-w-full gap-0 overflow-hidden py-0">
            <CardHeader className="flex-row items-center gap-3 border-b py-4">
                <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Rows3 className="size-4.5" />
                </div>
                <div className="flex-1 space-y-1">
                    <CardTitle>Details</CardTitle>
                    <CardDescription>
                        {rows.length} of max {max} row
                        {max === 1 ? '' : 's'} for <code>msg_det</code>. Saved
                        together with the message: all rows or nothing.
                    </CardDescription>
                </div>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={addRow}
                    disabled={rows.length >= max}
                >
                    <ListPlus /> Add row
                </Button>
            </CardHeader>

            {listError && (
                <div className="border-b px-4 py-3">
                    <InputError message={listError} />
                </div>
            )}

            {rows.length === 0 ? (
                <p className="px-4 py-8 text-center text-sm text-muted-foreground">
                    No detail rows. The message will be saved without details.
                </p>
            ) : (
                <div ref={tableRef}>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-12 pr-0 text-right">
                                    #
                                </TableHead>
                                {COLUMNS.map((column) => (
                                    <TableHead
                                        key={column.name}
                                        className={column.width}
                                    >
                                        {column.label}
                                        {column.required && (
                                            <span
                                                className="text-destructive"
                                                aria-hidden
                                            >
                                                {' '}
                                                *
                                            </span>
                                        )}
                                    </TableHead>
                                ))}
                                <TableHead className="w-12">
                                    <span className="sr-only">Remove</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.map((key, index) => (
                                <TableRow
                                    key={key}
                                    className="hover:bg-transparent"
                                >
                                    <TableCell className="pr-0 text-right align-top text-muted-foreground tabular-nums">
                                        <span className="inline-flex h-9 items-center">
                                            {index + 1}
                                        </span>
                                    </TableCell>
                                    {COLUMNS.map((column) => {
                                        const field = `details.${index}.${column.name}`;

                                        return (
                                            <TableCell
                                                key={column.name}
                                                className="px-2 align-top whitespace-normal"
                                            >
                                                <div className="grid gap-1">
                                                    <Input
                                                        id={`detail-${key}-${column.name}`}
                                                        name={`details[${index}][${column.name}]`}
                                                        aria-label={`Row ${index + 1} ${column.label}`}
                                                        required={
                                                            column.required
                                                        }
                                                        maxLength={
                                                            column.maxLength
                                                        }
                                                        {...(column.numeric
                                                            ? {
                                                                  type: 'number',
                                                                  inputMode:
                                                                      'numeric' as const,
                                                                  step: '1',
                                                              }
                                                            : {})}
                                                        className="h-9"
                                                    />
                                                    <InputError
                                                        message={errors[field]}
                                                        className="text-xs"
                                                    />
                                                </div>
                                            </TableCell>
                                        );
                                    })}
                                    <TableCell className="px-2 align-top">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="text-muted-foreground hover:text-destructive"
                                            onClick={() => removeRow(key)}
                                            aria-label={`Remove row ${index + 1}`}
                                        >
                                            <Trash2 />
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}
        </Card>
    );
}

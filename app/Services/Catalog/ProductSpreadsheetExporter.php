<?php

namespace App\Services\Catalog;

use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ekspor produk yang dicentang ke Excel (.xlsx) dan CSV.
 * Kolom sama dengan PDF (ProductPdfBuilder). Harga & stok disimpan sebagai
 * ANGKA (bukan teks "IDR ..."), sehingga bisa langsung dihitung di Excel.
 */
class ProductSpreadsheetExporter
{
    /** @var list<string> */
    private const HEADINGS = ['No', 'SKU', 'Product', 'Category', 'Price', 'Stock', 'Stock value', 'Status'];

    /**
     * @param  Collection<int, Product>  $products
     */
    public function xlsx(Collection $products, ?string $generatedBy, CarbonInterface $generatedAt): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator($generatedBy ?? (string) config('app.name'))
            ->setTitle('Product List')
            ->setCompany((string) config('app.name'));

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Products');
        $lastCol = Coordinate::stringFromColumnIndex(count(self::HEADINGS)); // "H"

        // Judul & info dokumen (baris 1–2).
        $sheet->setCellValue('A1', config('app.name').' — Product List');
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', sprintf(
            'Generated %s%s · %d %s',
            $generatedAt->format('d M Y H:i'),
            $generatedBy ? " by {$generatedBy}" : '',
            $products->count(),
            str('product')->plural($products->count()),
        ));
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('6B7280');

        // Judul kolom (baris 4).
        $headerRow = 4;
        $sheet->fromArray(self::HEADINGS, null, "A{$headerRow}");
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Data.
        $row = $headerRow;
        foreach ($products->values() as $i => $product) {
            $row++;
            $sheet->fromArray([
                $i + 1,
                $product->sku,
                $product->name,
                $product->category?->name ?? '-',
                (float) $product->price,
                (int) $product->stock,
                null, // diisi rumus di bawah
                $product->is_active ? 'Active' : 'Inactive',
            ], null, "A{$row}");
            // SKU sebagai teks (mis. "001" tidak berubah jadi 1).
            $sheet->setCellValueExplicit("B{$row}", $product->sku, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue("G{$row}", "=E{$row}*F{$row}");
        }

        $firstData = $headerRow + 1;
        $lastData = max($row, $headerRow);

        // Baris total dengan rumus SUM (ikut berubah bila data diedit).
        $totalRow = $lastData + 1;
        $sheet->setCellValue("A{$totalRow}", 'Total');
        $sheet->mergeCells("A{$totalRow}:E{$totalRow}");
        $sheet->setCellValue("F{$totalRow}", "=SUM(F{$firstData}:F{$lastData})");
        $sheet->setCellValue("G{$totalRow}", "=SUM(G{$firstData}:G{$lastData})");
        $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EEF2FF']],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'C7D2FE']]],
        ]);

        // Format angka & garis tipis antar baris.
        $sheet->getStyle("E{$firstData}:E{$totalRow}")->getNumberFormat()->setFormatCode('"IDR "#,##0');
        $sheet->getStyle("G{$firstData}:G{$totalRow}")->getNumberFormat()->setFormatCode('"IDR "#,##0');
        $sheet->getStyle("F{$firstData}:F{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$lastData}")->getBorders()->getBottom()
            ->setBorderStyle(Border::BORDER_HAIR);
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$lastData}")->getBorders()->getInside()
            ->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('E5E7EB');
        $sheet->getStyle("A{$firstData}:A{$lastData}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Judul kolom tetap terlihat saat scroll + filter per kolom.
        $sheet->freezePane('A'.$firstData);
        $sheet->setAutoFilter("A{$headerRow}:{$lastCol}{$lastData}");
        $sheet->setSelectedCell('A1');

        $filename = 'products-'.$generatedAt->format('Ymd-His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * CSV polos (tanpa format), cocok untuk impor ke sistem lain.
     * Diberi BOM UTF-8 agar Excel membaca karakter non-ASCII dengan benar.
     *
     * @param  Collection<int, Product>  $products
     */
    public function csv(Collection $products, CarbonInterface $generatedAt): StreamedResponse
    {
        $filename = 'products-'.$generatedAt->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($products): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::HEADINGS, escape: '');

            foreach ($products->values() as $i => $product) {
                fputcsv($out, [
                    $i + 1,
                    $product->sku,
                    $product->name,
                    $product->category?->name ?? '',
                    number_format((float) $product->price, 2, '.', ''),
                    $product->stock,
                    number_format((float) $product->price * $product->stock, 2, '.', ''),
                    $product->is_active ? 'Active' : 'Inactive',
                ], escape: '');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}

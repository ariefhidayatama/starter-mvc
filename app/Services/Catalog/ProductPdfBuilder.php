<?php

namespace App\Services\Catalog;

use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Menyusun PDF daftar produk (header + logo, tabel, footer + nomor halaman).
 * Dipakai oleh Admin\ProductController::pdf().
 */
class ProductPdfBuilder
{
    /** Logo di header. Ganti file ini dengan logo asli perusahaan. */
    public const LOGO_PATH = 'images/pdf-logo.svg';

    /**
     * @param  Collection<int, Product>  $products
     */
    public function build(Collection $products, ?string $generatedBy, ?CarbonInterface $generatedAt = null): DomPdf
    {
        $generatedAt ??= now();

        $pdf = Pdf::loadView('pdf.products', [
            'products' => $products,
            'generatedAt' => $generatedAt,
            'generatedBy' => $generatedBy,
            'documentNo' => 'PRD-'.$generatedAt->format('Ymd-His'),
            'logo' => $this->logoDataUri(),
        ])->setPaper('a4', 'portrait');

        // "Page X of Y" butuh total halaman, yang baru diketahui setelah
        // render. Jadi ditulis langsung ke canvas, di area footer.
        $pdf->render();
        $canvas = $pdf->getDomPDF()->getCanvas();
        $font = $pdf->getDomPDF()->getFontMetrics()->getFont('DejaVu Sans');
        // Koordinat dalam pt (A4 portrait 595 × 842); ukuran 6pt ≈ 8px CSS
        // agar sejajar dengan teks footer di kiri.
        $canvas->page_text(
            $canvas->get_width() - 24 - 58,
            $canvas->get_height() - 35,
            'Page {PAGE_NUM} of {PAGE_COUNT}',
            $font,
            6,
            [0.42, 0.45, 0.5],
        );

        return $pdf;
    }

    /** Logo disematkan sebagai data URI agar tidak bergantung chroot/remote. */
    private function logoDataUri(): ?string
    {
        $path = resource_path(self::LOGO_PATH);

        if (! is_file($path)) {
            return null;
        }

        return 'data:image/svg+xml;base64,'.base64_encode((string) file_get_contents($path));
    }
}

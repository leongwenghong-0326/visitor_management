<?php
declare(strict_types=1);

/**
 * CSV / Excel-compatible export helpers.
 */

function export_csv(string $filename, array $headers, array $rows): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // UTF-8 BOM for Excel
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

function simple_pdf_escape(string $s): string
{
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
}

/**
 * Minimal single-page text PDF (no external libs) for shared hosting.
 */
function export_simple_pdf(string $filename, string $title, array $lines): never
{
    $content = "BT /F1 16 Tf 50 780 Td (" . simple_pdf_escape($title) . ") Tj ET\n";
    $y = 750;
    foreach ($lines as $line) {
        if ($y < 50) {
            break;
        }
        $content .= "BT /F1 10 Tf 50 {$y} Td (" . simple_pdf_escape(mb_substr($line, 0, 100)) . ") Tj ET\n";
        $y -= 14;
    }

    $objects = [];
    $objects[] = "1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj\n";
    $objects[] = "2 0 obj<< /Type /Pages /Kids [3 0 R] /Count 1 >>endobj\n";
    $objects[] = "3 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources<< /Font<< /F1 5 0 R >> >> >>endobj\n";
    $objects[] = "4 0 obj<< /Length " . strlen($content) . " >>stream\n{$content}endstream\nendobj\n";
    $objects[] = "5 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>endobj\n";

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $obj) {
        $offsets[] = strlen($pdf);
        $pdf .= $obj;
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n{$xref}\n%%EOF";

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $pdf;
    exit;
}

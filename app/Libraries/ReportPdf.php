<?php

namespace App\Libraries;

use Dompdf\Dompdf;

/** Shared document identity and recorded-value presentation for A4 exports. */
final class ReportPdf
{
    public static function context(string $title, string $branch, string $scope, string $by): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Manila'));
        return ['reportTitle' => $title, 'branchName' => $branch, 'reportScope' => $scope,
            'reportReference' => 'RPT-' . $now->format('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(3))),
            'generatedAt' => $now->format('Y-m-d H:i:s') . ' PHT', 'generatedBy' => $by];
    }

    public static function stamp(Dompdf $pdf, array $context): void
    {
        $canvas = $pdf->getCanvas();
        $font = $pdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $fit = static function (string $text, float $width, float $size) use ($pdf, $font): string {
            $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
            while (mb_strlen($text) > 1 && $pdf->getFontMetrics()->getTextWidth($text, $font, $size) > $width) {
                $text = mb_substr($text, 0, -2) . '~';
            }
            return $text;
        };
        $width = $canvas->get_width() - 68;
        $identity = 'PHARXMACO | {PAGE_NUM}/{PAGE_COUNT} | ' . ($context['reportTitle'] ?? 'Management report') . ' | ' . ($context['branchName'] ?? 'All Branches');
        $canvas->page_text(34, 15, $fit($identity, $width, 8), $font, 8, [0.22, 0.25, 0.32]);
        $canvas->page_text(34, 28, $fit('Page {PAGE_NUM} | ' . (string)($context['reportScope'] ?? ''), $width, 7.5), $font, 7.5, [0.3, 0.33, 0.39]);
        $footer = ($context['reportReference'] ?? 'Report') . ' | ' . ($context['generatedAt'] ?? '') . ' | PHT = Asia/Manila';
        $canvas->page_text(34, $canvas->get_height() - 27, $fit($footer, $width - 115, 7), $font, 7, [0.3, 0.33, 0.39]);
        $canvas->page_text($canvas->get_width() - 110, $canvas->get_height() - 27, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 8, [0.3, 0.33, 0.39]);
        $pdf->getCanvas()->get_cpdf()->addInfo(['Title' => (string)($context['reportTitle'] ?? 'Pharxmaco report'),
            'Author' => (string)($context['generatedBy'] ?? 'Pharxmaco'), 'Subject' => (string)($context['reportReference'] ?? '')]);
    }

    public static function header(array $data, string $title, array $metadata): array
    {
        return array_intersect_key($data, array_flip(['branchName', 'reportReference', 'generatedBy', 'generatedAt', 'filterNotices']))
            + ['reportTitle' => $title, 'metadata' => $metadata];
    }

    public static function text(string $text, int $limit = 110): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit - 3) . '...' : $text;
    }

    public static function reference(array $log): string
    {
        // Only parse known application-generated prefixes. Never infer receiving lots.
        $remarks = (string)($log['remarks'] ?? '');
        foreach (['/^Sold via POS Invoice (\S+)/u', '/^Sale correction ([^:]+):/u', '/^Partial refund of (\S+)/u'] as $pattern) {
            if (preg_match($pattern, $remarks, $match)) return trim($match[1]);
        }
        return 'Stock log #' . (int)($log['id'] ?? 0);
    }

    public static function reconciliation(array $row): array
    {
        $row['original_sales'] = (float)($row['original_sales'] ?? $row['gross_sales'] ?? 0);
        $row['total_vat_relief'] = (float)($row['total_vat_relief'] ?? 0);
        $row['total_refunds'] = (float)($row['total_refunds'] ?? 0);
        $row['other_adjustments'] = round($row['original_sales'] - (float)($row['total_discount'] ?? 0)
            - $row['total_vat_relief'] - $row['total_refunds'] - (float)($row['net_sales'] ?? 0), 2);
        return $row;
    }
}

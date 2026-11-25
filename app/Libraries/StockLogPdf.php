<?php

namespace App\Libraries;

use Dompdf\Canvas;
use Dompdf\Dompdf;
use RuntimeException;

/** Draw stock history without creating an HTML/CSS frame tree for every cell. */
final class StockLogPdf
{
    private const LEFT = 34.0;
    private const TOP = 54.0;
    private const FONT_SIZE = 8.5;
    private const LINE_HEIGHT = 11.5;
    private const COLUMNS = [
        ['Log / time (PHT)', 13], ['Product / SKU / ID', 23], ['Branch / user', 16],
        ['Movement / source', 17], ['Change / unit', 9], ['Before / after', 8], ['Note', 14],
    ];

    private Dompdf $pdf;
    private Canvas $canvas;
    private string $normal;
    private string $bold;
    private float $width;
    private float $bottom;
    private float $y;
    private array $widths;

    public function render(iterable $logs, int $total, array $context): Dompdf
    {
        $this->pdf = PdfFactory::create();
        $this->pdf->setPaper('A4', 'landscape');
        $this->canvas = $this->pdf->getCanvas();
        $this->pdf->getFontMetrics()->setCanvas($this->canvas);
        $this->normal = $this->pdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $this->bold = $this->pdf->getFontMetrics()->getFont('DejaVu Sans', 'bold');
        $this->width = $this->canvas->get_width() - 2 * self::LEFT;
        $this->bottom = $this->canvas->get_height() - 45;
        $this->widths = array_map(fn ($c) => $this->width * $c[1] / 100, self::COLUMNS);
        $this->y = self::TOP;
        $notes = tmpfile();
        if ($notes === false) throw new RuntimeException('Unable to prepare stock report notes.');

        try {
            $this->header($total, $context);
            if ($total > 0) {
                $this->heading('Stock Movements');
                $this->paragraph('Change = after - before; positive adds stock, negative removes stock. Product IDs are stable; names, SKUs and units are current catalog values. Source is the recorded invoice or stock log reference.', 8.5);
                $this->tableHeader();
            }
            $count = 0;
            foreach ($logs as $log) {
                $remarks = (string) ($log['remarks'] ?? '');
                $short = ReportPdf::text($remarks, 65);
                $hasNote = $short !== trim($remarks) || str_contains($remarks, "\n");
                $ref = 'LOG-' . (int) $log['id'];
                if ($hasNote) {
                    $line = json_encode(['ref' => $ref, 'text' => $remarks], JSON_THROW_ON_ERROR) . "\n";
                    if (fwrite($notes, $line) !== strlen($line)) throw new RuntimeException('Unable to save stock report notes.');
                }
                $delta = (int) $log['new_stock'] - (int) $log['previous_stock'];
                $time = 'Time not recorded';
                if (!empty($log['created_at'])) {
                    try { $time = (new \DateTimeImmutable($log['created_at']))->format("Y-m-d\nH:i"); }
                    catch (\Exception) { /* Keep unknown legacy dates explicit. */ }
                }
                $this->row([
                    '#' . $log['id'] . "\n" . $time,
                    ($log['product_name'] ?? 'Catalog name unavailable') . "\n" . (($log['sku'] ?? '') ?: 'SKU not recorded') . ' / #' . (int) $log['product_id'],
                    ($log['branch_name'] ?? '-') . "\n" . ($log['full_name'] ?? '-'),
                    ucwords(str_replace('_', ' ', $log['action_type'] ?? '-')) . "\n" . ReportPdf::reference($log),
                    ($delta > 0 ? '+' : '') . $delta . "\n" . (($log['unit'] ?? '') ?: 'Not recorded'),
                    $log['previous_stock'] . ' -> ' . $log['new_stock'],
                    $hasNote ? 'See ' . $ref : $short,
                ], ++$count);
            }
            // Do not return a document whose record count conceals a failed/incomplete iterator.
            if ($count !== $total) throw new RuntimeException('The stock report could not include every selected record. Please retry.');
            if ($count === 0) $this->paragraph('No stock logs matched the selected filters.');

            rewind($notes);
            if (($line = fgets($notes)) !== false) {
                $this->space(48, 'Complete Movement Notes');
                $this->heading('Complete Movement Notes');
                $this->paragraph('Complete text for notes shortened in the movement table. References match the original stock-log ID.', 8.5);
                do {
                    $note = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                    $this->space(30, 'Complete Movement Notes');
                    $this->text(self::LEFT, $this->y, $note['ref'], 9, true);
                    $this->y += 14;
                    $this->paragraph($note['text'], 9, 'Complete Movement Notes');
                    $this->y += 6;
                } while (($line = fgets($notes)) !== false);
            }
            ReportPdf::stamp($this->pdf, $context);
            return $this->pdf;
        } finally {
            fclose($notes); // tmpfile automatically removes its file, including on an exception.
        }
    }

    private function header(int $total, array $context): void
    {
        $this->canvas->line(self::LEFT, $this->y, self::LEFT + $this->width, $this->y, [0.40, 0.27, 0.71], 1.5);
        $this->y += 8;
        $this->text(self::LEFT, $this->y, 'PHARXMACO DRUGSTORE', 9, true);
        $this->y += 17;
        $this->text(self::LEFT, $this->y, 'Stock Logs Report', 17, true);
        $this->y += 28;
        $from = !empty($context['dateFrom']) ? DisplayDate::date($context['dateFrom']) : 'All dates';
        $to = !empty($context['dateTo']) ? DisplayDate::date($context['dateTo']) : 'All dates';
        foreach ([
            ['Branch', $context['branchName'] ?? 'All Branches', 'Report reference', $context['reportReference'] ?? '-'],
            ['Type', $context['actionTypeLabel'] ?? 'All Types', 'Date range', $from . ' to ' . $to],
            ['Search', ($context['search'] ?? '') ?: 'None', 'Generated by', $context['generatedBy'] ?? 'System User'],
            ['Total records', (string) $total, 'Generated at', $context['generatedAt'] ?? ''],
        ] as $row) {
            $height = 14.0;
            foreach ([0, 1] as $i) {
                $x = self::LEFT + $i * ($this->width / 2);
                $this->text($x, $this->y, $row[$i * 2], 8.5, true);
                $lines = $this->wrap((string) $row[$i * 2 + 1], $this->width / 2 - 96, 9);
                foreach ($lines as $j => $line) $this->text($x + 88, $this->y + $j * 12, $line, 9);
                $height = max($height, count($lines) * 12 + 3);
            }
            $this->y += $height;
        }
        $this->y += 7;
        if (!empty($context['filterNotices'])) $this->paragraph('Filter note: ' . implode(' ', $context['filterNotices']));
    }

    private function tableHeader(): void
    {
        $this->space(38, 'Stock Movements');
        $height = 32.0;
        $this->canvas->filled_rectangle(self::LEFT, $this->y, $this->width, $height, [0.93, 0.94, 0.96]);
        $x = self::LEFT;
        foreach (self::COLUMNS as $i => $column) {
            foreach ($this->wrap($column[0], $this->widths[$i] - 8, self::FONT_SIZE, true) as $j => $line) {
                $this->text($x + 4, $this->y + 4 + $j * self::LINE_HEIGHT, $line, self::FONT_SIZE, true);
            }
            $x += $this->widths[$i];
        }
        $this->y += $height;
    }

    private function row(array $cells, int $number): void
    {
        $lines = [];
        foreach ($cells as $i => $cell) $lines[] = $this->wrap((string) $cell, $this->widths[$i] - 8, self::FONT_SIZE);
        $length = max(array_map('count', $lines));
        $height = $length * self::LINE_HEIGHT + 8;
        if ($this->y + $height > $this->bottom) {
            $this->newPage('Stock Movements');
            $this->tableHeader();
        }
        // Ordinary database fields fit on one page. Split an oversized legacy field safely too.
        for ($offset = 0; $offset < $length;) {
            $take = min($length - $offset, max(1, (int) floor(($this->bottom - $this->y - 8) / self::LINE_HEIGHT)));
            $height = $take * self::LINE_HEIGHT + 8;
            if ($number % 2 === 0) $this->canvas->filled_rectangle(self::LEFT, $this->y, $this->width, $height, [0.97, 0.98, 0.99]);
            $x = self::LEFT;
            foreach ($lines as $i => $cellLines) {
                foreach (array_slice($cellLines, $offset, $take) as $j => $line) {
                    $right = $i === 4 || $i === 5;
                    $tx = $right ? $x + $this->widths[$i] - 4 - $this->measure($line, self::FONT_SIZE) : $x + 4;
                    $this->text($tx, $this->y + 4 + $j * self::LINE_HEIGHT, $line, self::FONT_SIZE);
                }
                $x += $this->widths[$i];
            }
            $this->y += $height;
            $this->canvas->line(self::LEFT, $this->y, self::LEFT + $this->width, $this->y, [0.80, 0.82, 0.86], 0.4);
            $offset += $take;
            if ($offset < $length) { $this->newPage('Stock Movements'); $this->tableHeader(); }
        }
    }

    private function paragraph(string $text, float $size = 9, string $section = 'Stock Movements'): void
    {
        foreach ($this->wrap($text, $this->width, $size) as $line) {
            $this->space(13, $section);
            $this->text(self::LEFT, $this->y, $line, $size);
            $this->y += 13;
        }
        $this->y += 6;
    }

    private function heading(string $title): void
    {
        $this->text(self::LEFT, $this->y, $title, 11, true);
        $this->y += 18;
    }

    private function space(float $height, string $section): void
    {
        if ($this->y + $height > $this->bottom) $this->newPage($section);
    }

    private function newPage(string $section): void
    {
        $this->canvas->new_page();
        $this->y = self::TOP;
        $this->heading($section . ' (continued)');
    }

    private function text(float $x, float $y, string $text, float $size, bool $bold = false): void
    {
        $this->canvas->text($x, $y, $text, $bold ? $this->bold : $this->normal, $size, [0.09, 0.14, 0.20]);
    }

    private function measure(string $text, float $size, bool $bold = false): float
    {
        return $this->pdf->getFontMetrics()->getTextWidth($text, $bold ? $this->bold : $this->normal, $size);
    }

    private function wrap(string $text, float $width, float $size, bool $bold = false): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;
        $result = [];
        foreach (explode("\n", $text) as $paragraph) {
            $line = '';
            foreach (preg_split('/\s+/u', trim($paragraph), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                $candidate = $line === '' ? $word : $line . ' ' . $word;
                if ($line !== '' && $this->measure($candidate, $size, $bold) > $width) { $result[] = $line; $line = ''; }
                while ($this->measure($word, $size, $bold) > $width) {
                    $low = 1; $high = mb_strlen($word);
                    while ($low < $high) {
                        $mid = (int) ceil(($low + $high) / 2);
                        if ($this->measure(mb_substr($word, 0, $mid), $size, $bold) <= $width) $low = $mid;
                        else $high = $mid - 1;
                    }
                    $result[] = mb_substr($word, 0, $low);
                    $word = mb_substr($word, $low);
                }
                $line = $line === '' ? $word : $line . ' ' . $word;
            }
            $result[] = $line;
        }
        return $result;
    }
}

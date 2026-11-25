<?php
// Document-only build. No application bootstrap, database connection or live account data.
require dirname(__DIR__, 2).'/vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

$root = dirname(__DIR__, 2);
$sourcePath = $root.'/docs/manual/Pharxmaco_User_Manual.md';
$source = file_get_contents($sourcePath);
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
function inlineManual(string $text): string
{
    $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $text = preg_replace_callback('/( ?)`([^`]+)`( ?)/', static fn ($m) => ($m[1] !== '' ? '&#160;' : '').'<code>'.$m[2].'</code>'.($m[3] !== '' ? '&#160;' : ''), $text);
    return preg_replace_callback('/( ?)\*\*([^*]+)\*\*( ?)/', static fn ($m) => ($m[1] !== '' ? '&#160;' : '').'<strong>'.$m[2].'</strong>'.($m[3] !== '' ? '&#160;' : ''), $text);
}
function manualMarkdown(string $source, string $id): string
{
    $lines = preg_split('/\r?\n/', trim($source)); $out = ''; $i = 0;
    while ($i < count($lines)) {
        $line = trim($lines[$i]);
        if ($line === '' || str_starts_with($line, '<!--')) { $i++; continue; }
        if (preg_match('/^(#{1,3}) (.+)$/', $line, $heading)) {
            $level = strlen($heading[1]); $anchor = $level === 1 ? ' data-manual-anchor="'.$id.'"' : '';
            $out .= '<h'.$level.$anchor.'>'.inlineManual($heading[2]).'</h'.$level.'>'; $i++; continue;
        }
        if (str_starts_with($line, '|')) {
            $rows = [];
            while ($i < count($lines) && str_starts_with(trim($lines[$i]), '|')) {
                $row = array_map('trim', explode('|', trim(trim($lines[$i++]), '|')));
                if (count(array_filter($row, static fn ($cell) => preg_match('/^:?-+:?$/', $cell))) === count($row)) continue;
                $rows[] = $row;
            }
            $columns = count($rows[0]);
            $out .= '<table class="manual-table cols-'.$columns.'"><thead><tr>';
            foreach (array_shift($rows) as $cell) $out .= '<th>'.inlineManual($cell).'</th>';
            $out .= '</tr></thead><tbody>';
            foreach ($rows as $row) { $out .= '<tr>'; foreach ($row as $cell) $out .= '<td>'.inlineManual($cell).'</td>'; $out .= '</tr>'; }
            $out .= '</tbody></table>'; continue;
        }
        if (preg_match('/^(?:- |\d+\. )/', $line)) {
            $ordered = preg_match('/^\d+\. /', $line); $tag = $ordered ? 'ol' : 'ul'; $pattern = $ordered ? '/^\d+\. (.+)$/' : '/^- (.+)$/';
            $out .= '<'.$tag.'>';
            while ($i < count($lines) && preg_match($pattern, trim($lines[$i]), $item)) { $out .= '<li>'.inlineManual($item[1]).'</li>'; $i++; }
            $out .= '</'.$tag.'>'; continue;
        }
        $parts = [$line]; $i++;
        while ($i < count($lines) && trim($lines[$i]) !== '' && !preg_match('/^(?:#|\||- |\d+\. |<!--)/', trim($lines[$i]))) $parts[] = trim($lines[$i++]);
        $paragraph = implode(' ', $parts);
        $class = str_starts_with($paragraph, '**Important:**') ? 'important' : (str_starts_with($paragraph, '**Expected result:**') ? 'result' : '');
        $out .= '<p'.($class !== '' ? ' class="'.$class.'"' : '').'>'.inlineManual($paragraph).'</p>';
    }
    return $out;
}

$parts = preg_split('/<!-- page: ([a-z0-9-]+) \| ([^|]+) \| ([^>]+) -->\s*/', $source, -1, PREG_SPLIT_DELIM_CAPTURE);
array_shift($parts); $pages = [];
for ($i = 0; $i < count($parts); $i += 4) $pages[] = ['id'=>$parts[$i], 'title'=>trim($parts[$i + 1]), 'audience'=>trim($parts[$i + 2]), 'body'=>$parts[$i + 3]];
if (count($pages) !== 37) throw new RuntimeException('Review the expected manual page structure.');

$css = <<<'CSS'
@page{size:A4 portrait;margin:19mm 18mm 18mm}
*{box-sizing:border-box}
body{font-family:'DejaVu Sans',sans-serif;font-size:9.65pt;line-height:1.29;color:#19212b;margin:0}
.manual-page{page-break-before:always}
.manual-page:first-child{page-break-before:auto}
.page-kicker{font-size:8.1pt;letter-spacing:.45pt;text-transform:uppercase;color:#5d6570;margin:0 0 8pt}
h1,h2,h3{color:#000;line-height:1.19;page-break-after:avoid;font-weight:bold}
h1{font-size:20pt;margin:0 0 11pt;letter-spacing:-.4pt}
h2{font-size:12.1pt;margin:10pt 0 5pt}
h3{font-size:10.6pt;margin:10pt 0 5pt}
p{margin:0 0 6pt;orphans:3;widows:3}
strong{font-weight:bold;color:#10161f}
ul,ol{margin:4pt 0 8pt;padding-left:19pt}
li{padding-left:3pt;margin:0 0 3pt;page-break-inside:avoid}
.manual-table{width:100%;border-collapse:collapse;table-layout:fixed;font-size:8.9pt;line-height:1.26;margin:7pt 0 9pt}
.manual-table th,.manual-table td{padding:4pt 7pt;border:.6pt solid #d9d9d9;vertical-align:middle;overflow-wrap:break-word}
.manual-table th{text-align:left;background:#e9edf1;color:#000;font-weight:bold}
.manual-table tbody tr:nth-child(even){background:#f6f7f8}
.manual-table.cols-2 th:first-child,.manual-table.cols-2 td:first-child{width:32%}
.manual-table.cols-3 th:first-child,.manual-table.cols-3 td:first-child{width:43%}
.manual-table.cols-3 th:not(:first-child),.manual-table.cols-3 td:not(:first-child){width:28.5%}
.benefit-chapter .manual-table.cols-2 th:first-child,.benefit-chapter .manual-table.cols-2 td:first-child{width:70%}
thead{display:table-header-group}
tr{page-break-inside:avoid}
thead tr:last-child{page-break-after:avoid}
tbody tr:first-child{page-break-before:avoid}
code{font-family:'DejaVu Sans Mono',monospace;font-size:8.3pt;overflow-wrap:anywhere}
.important{border-left:2pt solid #be572b;padding:7pt 9pt;background:#fbf5ef;font-size:9.2pt;page-break-inside:avoid;margin-top:8pt}
.result{padding-left:0;color:#19212b}
.cover{padding-top:69pt}
.cover .publication{font-size:10pt;letter-spacing:2pt;text-transform:uppercase;color:#655448;margin-bottom:38pt}
.cover h1{font-size:34pt;line-height:1.12;letter-spacing:-.6pt;margin-bottom:18pt;max-width:390pt}
.cover h2{font-size:18pt;font-weight:normal;line-height:1.4;margin:0 0 32pt;max-width:370pt}
.cover .edition{font-size:11.6pt;margin:0 0 6pt}
.cover .control{font-size:9pt;color:#515b67;margin-bottom:53pt}
.cover .intro{font-size:11pt;line-height:1.65;max-width:370pt}
.cover .scope{border-top:1pt solid #c8ccd1;padding-top:15pt;margin-top:70pt;font-size:9pt;line-height:1.8;color:#515b67}
.toc h1{margin-bottom:11pt}
.toc>p{font-size:9pt;margin-bottom:11pt}
.toc-table{width:100%;border-collapse:collapse;font-size:8.8pt;line-height:1.19}
.toc-table th{padding:5pt 0;text-align:left;color:#5d6570;font-size:8pt;border-bottom:1pt solid #c7cdd4}
.toc-table td{padding:1.2pt 0;border-bottom:.4pt solid #ebedf0}
.toc-table td:last-child,.toc-table th:last-child{width:33pt;text-align:right}
.toc-table a{color:#10161f;text-decoration:none}
.toc-note{font-size:8pt;color:#5d6570;margin-top:10pt}
@media screen{body{background:#eef0f3}.manual-page{background:white;width:210mm;min-height:297mm;margin:24px auto;padding:19mm 18mm 18mm;box-shadow:0 4px 22px #00000014}.manual-page.cover{padding-top:42mm}.web-guide{max-width:210mm;margin:20px auto;font-size:14px;line-height:1.6;padding:0 20px}.web-guide a{color:#995020}.page-kicker:after{content:'  |  Page ' attr(data-page)}}
@media print{.web-guide{display:none}}
CSS;
$body = '';
foreach ($pages as $index => $page) {
    $id = $escape($page['id']);
    if ($index === 0) {
        $body .= '<section class="manual-page cover" id="cover"><div class="publication">Operations reference</div><h1>Pharxmaco<br>User Manual</h1><h2>Point of Sale and<br>Inventory Operations</h2><p class="edition">Administrator and Cashier Edition</p><p class="control">PHX UM 001 &nbsp; | &nbsp; Revision 1.0 &nbsp; | &nbsp; 17 October 2026</p><p class="intro">A practical operating reference for preparing the pharmacy workspace, recording sales, managing branch inventory, reconciling shared cash drawers and reviewing business records.</p><div class="scope">Daily counter procedures &nbsp; / &nbsp; Branch inventory<br>Cash reconciliation &nbsp; / &nbsp; Business reporting and recovery</div></section>';
        continue;
    }
    $body .= '<section class="manual-page'.($page['id'] === 'contents' ? ' toc' : '').($page['id'] === 'senior-pwd' ? ' benefit-chapter' : '').'" id="'.$id.'"><div class="page-kicker" data-page="'.($index + 1).'">'.$escape($page['audience']).'</div>';
    if ($page['id'] === 'contents') {
        $body .= '<h1 data-manual-anchor="contents">Contents</h1><p>Select a chapter to jump to it. Page numbers refer to this edition.</p><table class="toc-table"><thead><tr><th>Chapter</th><th>Page</th></tr></thead><tbody>';
        foreach ($pages as $pageIndex => $entry) if ($pageIndex >= 3) $body .= '<tr><td><a href="#'.$escape($entry['id']).'">'.$escape($entry['title']).'</a></td><td><a href="#'.$escape($entry['id']).'">'.($pageIndex + 1).'</a></td></tr>';
        $body .= '</tbody></table><p class="toc-note">About this manual: page 2. Cashier start-to-close procedures: Chapters 3 to 18. Inventory: Chapters 19 to 24. Administration and recovery: Chapters 25 to 30.</p>';
    } else $body .= manualMarkdown($page['body'], $id);
    $body .= '</section>';
}
$head = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Pharxmaco User Manual</title><style>'.$css.'</style></head><body>';
$htmlPath = $root.'/docs/manual/Pharxmaco_User_Manual.html';
file_put_contents($htmlPath, $head.'<aside class="web-guide"><strong>Pharxmaco User Manual</strong> &middot; Revision 1.0 &middot; 17 October 2026<br>Use the <a href="#contents">contents</a> to navigate. The printable PDF is the reference for page numbers. Edit the accompanying Markdown source to maintain a new edition.</aside>'.$body.'</body></html>');

$cache = __DIR__.'/cache'; foreach ([$cache.'/fonts', $cache.'/tmp', $root.'/output/pdf'] as $directory) if (!is_dir($directory)) mkdir($directory, 0775, true);
$options = new Options(); $options->set('isRemoteEnabled', false); $options->set('defaultFont', 'DejaVu Sans'); $options->set('defaultMediaType', 'print');
$options->setFontDir($cache.'/fonts'); $options->setFontCache($cache.'/fonts'); $options->setTempDir($cache.'/tmp');
$pdf = new Dompdf($options); $pdf->setPaper('A4', 'portrait'); $pdf->loadHtml($head.$body.'</body></html>', 'UTF-8');
$anchors = [];
$pdf->setCallbacks([['event'=>'begin_frame', 'f'=>static function ($frame, $canvas, $fontMetrics) use (&$anchors): void {
    $node = $frame->get_node();
    if ($node instanceof DOMElement && $node->hasAttribute('data-manual-anchor')) $anchors[$node->getAttribute('data-manual-anchor')] = $canvas->get_page_number();
}]]);
$pdf->render();
$canvas = $pdf->getCanvas(); $fonts = $pdf->getFontMetrics();
$canvas->add_info('Title', 'Pharxmaco User Manual'); $canvas->add_info('Author', 'Pharxmaco');
$canvas->add_info('Subject', 'Point of Sale and Inventory Operations - Administrator and Cashier Edition');
$canvas->page_script(static function (int $pageNumber, int $pageCount, $canvas, $fontMetrics): void {
    $font = $fontMetrics->getFont('DejaVu Sans'); $gray = [0.32, 0.36, 0.41]; $black = [0, 0, 0]; $width = $canvas->get_width(); $height = $canvas->get_height();
    if ($pageNumber > 1) {
        $canvas->text(51, 24, 'Pharxmaco User Manual', $font, 7.5, $black);
        $right = 'Point of Sale and Inventory Operations';
        $canvas->text($width - 51 - $fontMetrics->getTextWidth($right, $font, 7.1), 24, $right, $font, 7.1, $gray);
    }
    $canvas->text(51, $height - 32, 'PHX UM 001  |  Rev 1.0  |  17 October 2026', $font, 7.1, $gray);
    $right = 'Page '.$pageNumber.' of '.$pageCount;
    $canvas->text($width - 51 - $fontMetrics->getTextWidth($right, $font, 7.1), $height - 32, $right, $font, 7.1, $gray);
});
$pdfPath = $root.'/output/pdf/Pharxmaco_User_Manual_2026-10-17.pdf'; file_put_contents($pdfPath, $pdf->output());
$counts = [];
foreach ($pages as $index => $page) $counts[] = ['id'=>$page['id'], 'title'=>$page['title'], 'expected_page'=>$index + 1, 'actual_page'=>$page['id'] === 'cover' ? 1 : ($anchors[$page['id']] ?? null), 'words'=>str_word_count($page['body'])];
$report = ['pdf'=>$pdfPath, 'html'=>$htmlPath, 'source'=>$sourcePath, 'expected_pages'=>count($pages), 'actual_pages'=>$canvas->get_page_count(), 'page_map'=>$counts, 'pdf_sha256'=>hash_file('sha256', $pdfPath), 'source_sha256'=>hash_file('sha256', $sourcePath), 'application_records_modified'=>false];
file_put_contents(__DIR__.'/manifest.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode(['pages'=>$report['actual_pages'], 'expected'=>$report['expected_pages'], 'pdf'=>$pdfPath, 'source_words'=>str_word_count($source), 'misplaced_chapters'=>array_values(array_filter($counts, static fn ($row) => $row['expected_page'] !== $row['actual_page']))], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;

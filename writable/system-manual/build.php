<?php
// Document-only build. No application bootstrap, database connection or live account data.
require dirname(__DIR__, 2).'/vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

$root = dirname(__DIR__, 2);
$sourcePath = $root.'/docs/manual/Pharxmaco_User_Manual.md';
$source = file_get_contents($sourcePath);
if (!preg_match('/^Document (.+)$/m', str_replace("\r", '', $source), $edition)) {
    throw new RuntimeException('Manual document-control line is missing.');
}
[, $documentId] = $edition;
// Retain the current download filename; no issue date is printed in the manual.
$pdfName = 'Pharxmaco_User_Manual_2026-10-18';
$partsOfBook = [
    ['number'=>1, 'title'=>'Getting started', 'from'=>1, 'to'=>4],
    ['number'=>2, 'title'=>'Sales and returns', 'from'=>5, 'to'=>14],
    ['number'=>3, 'title'=>'Cashier operations', 'from'=>15, 'to'=>18],
    ['number'=>4, 'title'=>'Inventory', 'from'=>19, 'to'=>24],
    ['number'=>5, 'title'=>'Administration', 'from'=>25, 'to'=>30],
    ['number'=>6, 'title'=>'Quick reference', 'from'=>31, 'to'=>34],
];
$partForChapter = static function (int $chapter) use ($partsOfBook): array {
    foreach ($partsOfBook as $part) if ($chapter >= $part['from'] && $chapter <= $part['to']) return $part;
    throw new RuntimeException('Chapter has no manual part.');
};
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
            $numeric = array_map(static fn ($header) => (bool) preg_match('/^(Amount|Effect|Running cash)$/i', $header), $rows[0]);
            $out .= '<table class="manual-table cols-'.$columns.'"><thead><tr>';
            foreach (array_shift($rows) as $column => $cell) $out .= '<th'.($numeric[$column] ? ' class="numeric"' : '').'>'.inlineManual($cell).'</th>';
            $out .= '</tr></thead><tbody>';
            foreach ($rows as $row) { $out .= '<tr>'; foreach ($row as $column => $cell) $out .= '<td'.($numeric[$column] ? ' class="numeric"' : '').'>'.inlineManual($cell).'</td>'; $out .= '</tr>'; }
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
$fontRoot = 'C:/Windows/Fonts';
$useCalibri = count(array_filter(['calibri.ttf','calibrib.ttf','calibrii.ttf','calibriz.ttf','calibril.ttf'], static fn ($file) => is_file($fontRoot.'/'.$file))) === 5;
$bodyFont = $useCalibri ? 'Calibri' : 'DejaVu Sans';
$displayFont = $useCalibri ? 'Calibri Light' : 'DejaVu Sans';

$css = <<<'CSS'
@page{size:A4 portrait;margin:18mm 18mm 18mm}
*{box-sizing:border-box}
body{font-family:'__BODY_FONT__','Calibri','Arial',sans-serif;font-size:11pt;line-height:1.08;color:#000;margin:0}
.manual-page{page-break-before:always}
.manual-page:first-child{page-break-before:auto}
.chapter-meta{border-top:.6pt solid #bdbdbd;padding-top:6pt;margin:0 0 8pt;font-size:8pt;line-height:1.2;color:#000}
.part-label{color:#000;font-weight:bold;letter-spacing:.55pt;text-transform:uppercase}
.chapter-meta .audience{float:right;text-align:right;color:#000;font-size:8pt}
.part-start .chapter-meta{border-top:2pt solid #000;padding-top:8pt}
h1,h2,h3{color:#000;line-height:1.12;page-break-after:avoid}
h1{font-family:'__DISPLAY_FONT__','Calibri Light','Calibri',sans-serif;font-weight:normal;font-size:23pt;margin:0 0 10pt;letter-spacing:-.2pt}
h2{font-size:12.5pt;font-weight:bold;margin:9pt 0 4pt}
h3{font-size:11.3pt;font-weight:bold;margin:10pt 0 5pt}
p{margin:0 0 6pt;orphans:3;widows:3}
strong{font-weight:bold;color:#000}
ul,ol{margin:4pt 0 8pt;padding-left:17pt}
li{padding-left:3pt;margin:0 0 3pt;page-break-inside:avoid}
.manual-table{width:100%;border-collapse:collapse;table-layout:fixed;font-size:10pt;line-height:1.06;margin:7pt 0 9pt}
.manual-table th,.manual-table td{padding:4pt 7pt;border:.5pt solid #aaa;vertical-align:middle;overflow-wrap:break-word;background:#fff}
.manual-table th{text-align:left;border-top:.8pt solid #000;border-bottom:.8pt solid #000;color:#000;font-weight:bold}
.manual-table.cols-2 th:first-child,.manual-table.cols-2 td:first-child{width:32%}
.manual-table.cols-3 th:first-child,.manual-table.cols-3 td:first-child{width:46%}
.manual-table.cols-3 th:not(:first-child),.manual-table.cols-3 td:not(:first-child){width:27%}
.manual-table .numeric{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}
.benefit-chapter .manual-table.cols-2 th:first-child,.benefit-chapter .manual-table.cols-2 td:first-child{width:70%}
thead{display:table-header-group}
tr{page-break-inside:avoid}
thead tr:last-child{page-break-after:avoid}
tbody tr:first-child{page-break-before:avoid}
code{font-family:'DejaVu Sans Mono',monospace;font-size:8.5pt;overflow-wrap:anywhere}
.important{border-left:1.7pt solid #000;padding:7pt 10pt;background:#fff;font-size:10.2pt;page-break-inside:avoid;margin-top:9pt}
.result{border-top:.5pt solid #bdbdbd;padding-top:6pt;color:#000;font-size:10.6pt}
.cover{padding-top:20pt}
.cover-brand{font-size:14pt;letter-spacing:3pt;color:#000;font-weight:bold;margin-bottom:8pt}
.cover .publication{font-size:9pt;letter-spacing:1.2pt;text-transform:uppercase;color:#000;padding-bottom:14pt;border-bottom:.7pt solid #bdbdbd;margin-bottom:45pt}
.cover h1{font-size:49pt;line-height:1.06;letter-spacing:-.6pt;margin-bottom:15pt}
.cover h2{font-size:21pt;font-weight:normal;line-height:1.18;margin:0 0 18pt;max-width:370pt}
.cover .edition{font-size:11.5pt;color:#000;margin-bottom:0}
.cover-rule{height:3pt;width:48pt;background:#000;margin:29pt 0 24pt}
.cover .intro{font-size:12pt;line-height:1.4;max-width:390pt}
.cover-parts{width:100%;border-collapse:collapse;table-layout:fixed;margin:35pt 0 32pt;font-size:10pt;color:#000}
.cover-parts td{width:50%;padding:8pt 0;border-top:.5pt solid #bdbdbd}
.cover-parts span{color:#000;font-size:8.4pt;padding-right:8pt}
.cover-control{width:100%;border-collapse:collapse;border-top:.7pt solid #bdbdbd;font-size:9.2pt;color:#000}
.cover-control td{width:100%;padding:10pt 0 0;vertical-align:top}
.cover-control .control-label{display:block;font-size:8pt;text-transform:uppercase;letter-spacing:.5pt;color:#000;margin-bottom:4pt}
.toc h1{margin-bottom:8pt}
.toc>p{font-size:10.5pt;margin-bottom:17pt;color:#000}
.toc-column{float:left;width:47%}
.toc-column+.toc-column{float:right}
.toc-clear{clear:both}
.toc-part{font-size:10pt;color:#000;letter-spacing:.3pt;font-weight:bold;margin:0 0 5pt;padding-top:13pt}
.toc-part:first-child{padding-top:0}
.toc-table{width:100%;border-collapse:collapse;table-layout:fixed;font-size:9.7pt;line-height:1.04;margin-bottom:5pt}
.toc-table td{padding:3pt 0;vertical-align:top;border-bottom:.4pt solid #ddd}
.toc-table td:first-child{width:89%;padding-right:6pt}
.toc-table td:last-child{width:11%;text-align:right;font-weight:bold}
.toc-number{color:#000}
.toc-table a{color:#000;text-decoration:none}
.toc-table td:last-child a{color:#000}
.toc-note{font-size:9.2pt;color:#000;margin-top:19pt;border-top:.5pt solid #bdbdbd;padding-top:9pt}
@media screen{body{background:#eee;line-height:1.45}.manual-page{background:white;width:210mm;min-height:297mm;margin:24px auto;padding:18mm;box-shadow:0 4px 22px #00000014}.web-guide{max-width:210mm;margin:20px auto;font-size:15px;line-height:1.5;padding:0 20px}.web-guide a{color:#000}.manual-page.cover{padding-top:25mm}.manual-table{font-size:10.5pt;line-height:1.3}.important,.result{line-height:1.4}.toc-table{font-size:11pt;line-height:1.25}.toc-table td{padding:5px 0}}
@media screen and (max-width:820px){.manual-page{width:auto;min-height:0;margin:16px 10px;padding:28px 22px}.manual-page.cover{padding-top:32px}.chapter-meta .audience{float:none;display:block;text-align:left;margin-top:5px}.toc-column,.toc-column+.toc-column{float:none;width:100%}.toc-column+.toc-column{padding-top:16px}.cover h1{font-size:42pt}}
@media print{.web-guide{display:none}}
CSS;
$css = str_replace(['__BODY_FONT__','__DISPLAY_FONT__'], [$bodyFont,$displayFont], $css);
$body = '';
foreach ($pages as $index => $page) {
    $id = $escape($page['id']);
    if ($index === 0) {
        $body .= '<section class="manual-page cover" id="cover"><div class="cover-brand">PHARXMACO</div><div class="publication">Operations reference</div><h1>User manual</h1><h2>Point of Sale and<br>Inventory Operations</h2><p class="edition">Administrator and Cashier Edition</p><div class="cover-rule"></div><p class="intro">A practical operating reference for preparing the pharmacy workspace, recording sales, managing branch inventory, reconciling shared cash drawers and reviewing business records.</p><table class="cover-parts"><tbody>';
        for ($row = 0; $row < 3; $row++) {
            $body .= '<tr>';
            foreach ([$partsOfBook[$row * 2], $partsOfBook[$row * 2 + 1]] as $part) $body .= '<td><span>'.str_pad((string)$part['number'],2,'0',STR_PAD_LEFT).'</span> '.$escape($part['title']).'</td>';
            $body .= '</tr>';
        }
        $body .= '</tbody></table><table class="cover-control"><tbody><tr><td><span class="control-label">Document</span>'.$escape($documentId).'</td></tr></tbody></table></section>';
        continue;
    }
    $part = $index >= 3 ? $partForChapter($index - 2) : null;
    $partStart = $part && $index - 2 === $part['from'];
    $body .= '<section class="manual-page'.($page['id'] === 'contents' ? ' toc' : '').($page['id'] === 'senior-pwd' ? ' benefit-chapter' : '').($partStart ? ' part-start' : '').'" id="'.$id.'"><div class="chapter-meta"><span class="audience">'.$escape($page['audience']).'</span><span class="part-label">'.($part ? 'Part '.str_pad((string)$part['number'],2,'0',STR_PAD_LEFT).' / '.$escape($part['title']) : 'Pharxmaco / '.($page['id'] === 'contents' ? 'Find a procedure' : 'Using this manual')).'</span></div>';
    if ($page['id'] === 'contents') {
        $body .= '<h1 data-manual-anchor="contents">Contents</h1><p>Choose a chapter to open its procedure. Numbers at the right are printed page numbers.</p><div class="toc-columns">';
        foreach ([array_slice($partsOfBook,0,3), array_slice($partsOfBook,3)] as $column) {
            $body .= '<div class="toc-column">';
            foreach ($column as $group) {
                $body .= '<div class="toc-part">'.str_pad((string)$group['number'],2,'0',STR_PAD_LEFT).' / '.$escape($group['title']).'</div><table class="toc-table"><tbody>';
                for ($chapter = $group['from']; $chapter <= $group['to']; $chapter++) {
                    $entry = $pages[$chapter + 2];
                    $body .= '<tr><td><a href="#'.$escape($entry['id']).'"><span class="toc-number">'.$chapter.'</span> &nbsp;'.$escape(preg_replace('/^\d+\s+/', '', $entry['title'])).'</a></td><td><a href="#'.$escape($entry['id']).'">'.($chapter + 3).'</a></td></tr>';
                }
                $body .= '</tbody></table>';
            }
            $body .= '</div>';
        }
        $body .= '<div class="toc-clear"></div></div><p class="toc-note">About this manual: page 2. Cashier start-to-close procedures: Chapters 3 to 18. Inventory: Chapters 19 to 24. Administration and recovery: Chapters 25 to 30.</p>';
    } else $body .= manualMarkdown($page['body'], $id);
    $body .= '</section>';
}
$head = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Pharxmaco User Manual</title><style>'.$css.'</style></head><body>';
$htmlPath = $root.'/docs/manual/Pharxmaco_User_Manual.html';
file_put_contents($htmlPath, $head.'<aside class="web-guide"><strong>Pharxmaco User Manual</strong><br>Use the <a href="#contents">contents</a> to navigate, or open the <a href="'.$escape($pdfName).'.pdf">print edition</a>.</aside>'.$body.'</body></html>');

$cache = __DIR__.'/cache'; foreach ([$cache.'/fonts', $cache.'/tmp', $root.'/output/pdf'] as $directory) if (!is_dir($directory)) mkdir($directory, 0775, true);
$options = new Options(); $options->set('isRemoteEnabled', false); $options->set('defaultFont', $bodyFont); $options->set('defaultMediaType', 'print'); $options->set('isFontSubsettingEnabled', true);
$options->setChroot($useCalibri ? [$root, $fontRoot] : [$root]);
$options->setFontDir($cache.'/fonts'); $options->setFontCache($cache.'/fonts'); $options->setTempDir($cache.'/tmp');
$pdf = new Dompdf($options);
if ($useCalibri) {
    foreach ([['Calibri','normal','normal','calibri.ttf'],['Calibri','bold','normal','calibrib.ttf'],['Calibri','normal','italic','calibrii.ttf'],['Calibri','bold','italic','calibriz.ttf'],['Calibri Light','normal','normal','calibril.ttf']] as [$family,$weight,$style,$file]) {
        if (!$pdf->getFontMetrics()->registerFont(['family'=>$family,'weight'=>$weight,'style'=>$style], 'file://'.$fontRoot.'/'.$file)) throw new RuntimeException('Could not embed '.$file);
    }
}
$pdf->setPaper('A4', 'portrait'); $pdf->loadHtml($head.$body.'</body></html>', 'UTF-8');
$anchors = [];
$pdf->setCallbacks([['event'=>'begin_frame', 'f'=>static function ($frame, $canvas, $fontMetrics) use (&$anchors): void {
    $node = $frame->get_node();
    if ($node instanceof DOMElement && $node->hasAttribute('data-manual-anchor')) $anchors[$node->getAttribute('data-manual-anchor')] = $canvas->get_page_number();
}]]);
$pdf->render();
$canvas = $pdf->getCanvas(); $fonts = $pdf->getFontMetrics();
$canvas->add_info('Title', 'Pharxmaco User Manual'); $canvas->add_info('Author', 'Pharxmaco');
$canvas->add_info('Subject', 'Point of Sale and Inventory Operations - Administrator and Cashier Edition');
$canvas->page_script(static function (int $pageNumber, int $pageCount, $canvas, $fontMetrics) use ($bodyFont,$documentId,$partForChapter): void {
    $font = $fontMetrics->getFont($bodyFont); $black = [0, 0, 0]; $width = $canvas->get_width(); $height = $canvas->get_height();
    if ($pageNumber > 1) {
        $canvas->text(51, 23, 'PHARXMACO / USER MANUAL', $font, 8.2, $black);
        $right = $pageNumber >= 4 ? $partForChapter(min(34,$pageNumber - 3))['title'] : 'Point of sale and inventory operations';
        $canvas->text($width - 51 - $fontMetrics->getTextWidth($right, $font, 8.2), 23, $right, $font, 8.2, $black);
    }
    $canvas->line(51, $height - 43, $width - 51, $height - 43, [0.74, 0.74, 0.74], .5);
    $canvas->text(51, $height - 31, $documentId, $font, 8.2, $black);
    $right = 'Page '.$pageNumber.' of '.$pageCount;
    $canvas->text($width - 51 - $fontMetrics->getTextWidth($right, $font, 8.2), $height - 31, $right, $font, 8.2, $black);
});
$pdfPath = $root.'/output/pdf/'.$pdfName.'.pdf'; file_put_contents($pdfPath, $pdf->output());
$counts = [];
foreach ($pages as $index => $page) $counts[] = ['id'=>$page['id'], 'title'=>$page['title'], 'expected_page'=>$index + 1, 'actual_page'=>$page['id'] === 'cover' ? 1 : ($anchors[$page['id']] ?? null), 'words'=>str_word_count($page['body'])];
$report = ['pdf'=>$pdfPath, 'html'=>$htmlPath, 'source'=>$sourcePath, 'issue_date_shown'=>false, 'revision_label_shown'=>false, 'body_font'=>$bodyFont, 'display_font'=>$displayFont, 'body_font_pt'=>11, 'table_font_pt'=>10, 'font_color'=>'black', 'table_style'=>'unshaded grid', 'parts'=>$partsOfBook, 'expected_pages'=>count($pages), 'actual_pages'=>$canvas->get_page_count(), 'page_map'=>$counts, 'pdf_sha256'=>hash_file('sha256', $pdfPath), 'source_sha256'=>hash_file('sha256', $sourcePath), 'application_records_modified'=>false];
file_put_contents(__DIR__.'/manifest.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode(['pages'=>$report['actual_pages'], 'expected'=>$report['expected_pages'], 'pdf'=>$pdfPath, 'source_words'=>str_word_count($source), 'misplaced_chapters'=>array_values(array_filter($counts, static fn ($row) => $row['expected_page'] !== $row['actual_page']))], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;

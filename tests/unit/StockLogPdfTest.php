<?php

use App\Libraries\ReportPdf;
use App\Libraries\StockLogPdf;
use CodeIgniter\Test\CIUnitTestCase;

final class StockLogPdfTest extends CIUnitTestCase
{
    private function context(): array
    {
        return ReportPdf::context('Stock Logs Report', 'PDF Test Branch', 'All dates | Test history', 'PDF Test Staff');
    }

    private function row(int $id): array
    {
        return ['id' => $id, 'product_id' => 1, 'product_name' => 'Test tablet', 'sku' => 'PDF-TEST', 'unit' => 'tablet',
            'branch_name' => 'PDF Test Branch', 'full_name' => 'PDF Test Staff', 'action_type' => 'stock_out',
            'previous_stock' => 10, 'new_stock' => 8, 'created_at' => '2026-10-23 08:00:00', 'remarks' => 'Physical movement checked'];
    }

    public function testIteratorIsConsumedOnceAndProducesCompleteMultipagePdf(): void
    {
        $read = [];
        $rows = (function () use (&$read) {
            for ($id = 1; $id <= 120; $id++) { $read[] = $id; yield $this->row($id); }
        })();
        $pdf = (new StockLogPdf())->render($rows, 120, $this->context());
        $this->assertSame(range(1, 120), $read);
        $this->assertGreaterThan(5, $pdf->getCanvas()->get_page_count());
        $this->assertEqualsWithDelta(841.89, $pdf->getCanvas()->get_width(), .1);
        $this->assertEqualsWithDelta(595.28, $pdf->getCanvas()->get_height(), .1);
        $bytes = $pdf->output();
        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertStringContainsString('/ToUnicode', $bytes);
        $this->assertStringContainsString('/FontFile2', $bytes);
    }

    public function testEmptySelectionProducesOnePage(): void
    {
        $pdf = (new StockLogPdf())->render([], 0, $this->context());
        $this->assertSame(1, $pdf->getCanvas()->get_page_count());
        $this->assertStringStartsWith('%PDF-', $pdf->output());
    }

    public function testAnIncompleteSelectionCannotBeReturnedAsACompleteReport(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('every selected record');
        (new StockLogPdf())->render([$this->row(1)], 2, $this->context());
    }

    public function testLongUnbrokenFieldsAndNotesCanContinueAcrossPages(): void
    {
        $row = $this->row(1);
        $row['product_name'] = str_repeat('LongUnbrokenProduct', 100);
        $row['remarks'] = "First paragraph\n" . str_repeat('Full note with quotes " and <script>literal</script>. ', 160) . 'COMPLETE-NOTE-END';
        $pdf = (new StockLogPdf())->render([$row], 1, $this->context());
        $this->assertGreaterThan(2, $pdf->getCanvas()->get_page_count());
        $this->assertStringStartsWith('%PDF-', $pdf->output());
    }
}

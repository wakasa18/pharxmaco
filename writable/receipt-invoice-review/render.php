<?php
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
helper(['url', 'form']);
session()->set(['role' => 'cashier', 'branch_id' => null, 'full_name' => 'Preview Staff']);
$sale = ['id' => 42, 'invoice_no' => 'INV-20261014-091500-A1B2C3', 'sale_date' => '2026-10-14 09:15:00',
    'branch_name' => 'Main Branch', 'branch_address' => '123 Sample Street, Barangay San Jose, Santa Maria, Bulacan', 'branch_contact' => '09123456789',
    'full_name' => 'Preview Staff', 'payment_method' => 'cash', 'reference_no' => '',
    'total_amount' => 262, 'discount_amount' => 11.2, 'final_total' => 250.8, 'amount_paid' => 300,
    'change_amount' => 49.2, 'status' => 'completed', 'notes' => ''];
$items = [
    ['product_name_snapshot' => 'Medicine A', 'generic_name_snapshot' => 'Paracetamol', 'strength_snapshot' => '500 mg',
        'quantity' => 1, 'price' => 112, 'subtotal' => 112, 'discount_applied' => 11.2, 'tax_treatment_snapshot' => 'taxable'],
    ['product_name_snapshot' => 'Medicine B', 'generic_name_snapshot' => 'Metformin hydrochloride', 'strength_snapshot' => '500 mg',
        'quantity' => 1, 'price' => 100, 'subtotal' => 100, 'discount_applied' => 0, 'tax_treatment_snapshot' => 'vat_exempt'],
    ['product_name_snapshot' => 'Ascorbic Acid + Zinc Film-coated Tablet', 'generic_name_snapshot' => 'Ascorbic acid + Zinc', 'strength_snapshot' => '500 mg / 10 mg',
        'quantity' => 1, 'price' => 50, 'subtotal' => 50, 'discount_applied' => 0, 'tax_treatment_snapshot' => 'zero_rated'],
];
$empty = new \Config\ReceiptInvoice();
$empty->tin = '';
$empty->taxStatus = '';
$empty->serialEnd = 0;
$nonVat = new \Config\ReceiptInvoice();
$nonVat->taxStatus = 'non_vat';
$nonVat->tin = '123-456-789-000'; // Rendering fixture only; never saved in the app configuration.
$vat = clone $nonVat;
$vat->taxStatus = 'vat';
foreach (['unconfigured' => $empty, 'non-vat' => $nonVat, 'vat' => $vat, 'senior' => $nonVat, 'pwd' => $nonVat, 'reprint' => $vat, 'legacy' => null] as $name => $config) {
    $data = $sale;
    if ($config) $data['receipt_metadata'] = \App\Libraries\ReceiptInvoice::snapshot(
        ['discount_name' => ['senior' => 'Senior Citizen', 'pwd' => 'PWD'][$name] ?? 'Promotion'], $config,
        $name === 'unconfigured' ? null : 'SI-00000042');
    $html = view('cashier/sales/receipt', ['sale' => $data, 'items' => $items,
        'isReprint' => $name === 'reprint', 'autoPrint' => false], ['saveData' => false]);
    $html = preg_replace('~https?://[^"\s]+/assets/~', '/assets/', $html);
    file_put_contents(__DIR__ . '/' . $name . '.html', $html);
    if (!str_contains($html, $data['branch_address'])) throw new RuntimeException('Missing business address.');
}
echo 'Rendered seven invoice variants.' . PHP_EOL;

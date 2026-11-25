<?php
require dirname(__DIR__, 3) . '/system/Test/bootstrap.php';
helper(['url', 'form']);
session()->set(['role' => 'cashier', 'branch_id' => 1, 'user_id' => 1, 'full_name' => 'Maria Santos']);
config(\Config\ReceiptInvoice::class)->taxStatus = 'vat';
$products = [];
foreach ([1 => ['Paracetamol 500mg', 112, 1], 2 => ['Vitamin C 500mg', 50, 0]] as $id => [$name, $price, $eligible]) {
    $products[$id] = ['id' => $id, 'product_id' => $id, 'product_name' => $name, 'generic_name' => $name,
        'sku' => 'DEMO-' . $id, 'category_id' => 1, 'category_name' => 'Medicine', 'price' => $price,
        'cost_price' => 10, 'stock' => 20, 'quantity' => 1, 'subtotal' => $price,
        'expiration_date' => '2099-01-01', 'status' => 'active', 'branch_id' => 1, 'unit' => 'tablet',
        'tax_treatment' => 'taxable', 'senior_pwd_eligible' => $eligible, 'medicine_type' => 'generic'];
}
$discounts = [['id' => 9, 'discount_name' => 'Vitamin promo', 'discount_type' => 'percentage', 'discount_value' => 10,
    'applies_to' => 'product', 'product_id' => 2, 'minimum_purchase' => 0, 'max_discount_amount' => null,
    'category_id' => null, 'status' => 'active']];
$held = [1 => ['label' => 'Waiting customer', 'cart' => [$products[1] + ['quantity' => 2]], 'held_at' => date('Y-m-d H:i:s')]];
$data = ['products' => array_values($products), 'cart' => [], 'discounts' => $discounts, 'heldSales' => []];
$html = view('cashier/sales/index', $data, ['saveData' => false]);
file_put_contents(__DIR__ . '/pos.html', preg_replace('~https?://[^"\s]+/assets/~', '/assets/', $html));
$full = view('cashier/sales/index', array_replace($data, ['cart' => [1 => $products[1]]]), ['saveData' => false]);
file_put_contents(__DIR__ . '/pos-full.html', preg_replace('~https?://[^"\s]+/assets/~', '/assets/', $full));
$snapshots = [];
for ($q1 = 0; $q1 <= 4; $q1++) for ($q2 = 0; $q2 <= 4; $q2++) foreach ([0, 1] as $h) {
    $cart = [];
    foreach ([1 => $q1, 2 => $q2] as $id => $quantity) if ($quantity) $cart[$id] = array_replace($products[$id], ['quantity' => $quantity, 'subtotal' => $quantity * $products[$id]['price']]);
    $current = ['cart' => $cart, 'heldSales' => $h ? $held : [], 'discounts' => $discounts, 'discountChoice' => 'auto', 'oldBenefitProducts' => []];
    $snapshots["$q1-$q2-$h"] = [
        'success' => true, 'message' => 'Cart updated.', 'new_customer' => false,
        'cart_html' => view('cashier/sales/_cart_lines', $current, ['saveData' => false]),
        'benefit_html' => view('cashier/sales/_benefit_products', $current, ['saveData' => false]),
        'held_html' => view('cashier/sales/_held_sales', $current, ['saveData' => false]),
        'discount_options_html' => view('cashier/sales/_discount_options', $current, ['saveData' => false]),
        'cart_count' => count($cart), 'held_count' => $h,
        'promotions' => [['id' => 9, 'productId' => 2, 'scope' => 'product', 'categoryId' => 0, 'name' => 'Vitamin promo', 'type' => 'percentage', 'value' => 10, 'minimum' => 0, 'maximum' => null]],
        'checkout_token' => session('checkout_token'), 'selection_scope' => '1:' . session('checkout_token'),
        'csrf_name' => csrf_token(), 'csrf_hash' => csrf_hash(),
    ];
}
file_put_contents(__DIR__ . '/snapshots.json', json_encode($snapshots, JSON_THROW_ON_ERROR));
echo "Rendered initial POS and 50 cart-only browser fixtures; no application database used.\n";

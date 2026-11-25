<?php
// Uses the existing in-memory regression fixture, never an application database.
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
require_once dirname(__DIR__, 2) . '/tests/unit/BusinessLogicTest.php';
$test = new BusinessLogicTest('testProductListFiltersUseBranchQuantityAndInclusiveReorderBoundary');
(new ReflectionMethod($test, 'setUp'))->invoke($test);
try {
    (new ReflectionMethod($test, 'productListFilterFixture'))->invoke($test);
    foreach (['admin', 'cashier'] as $role) {
        session()->set(['role' => $role, 'branch_id' => 1, 'branch_name' => 'Branch A']);
        foreach (['' => [], '-filtered' => ['manufacturer' => 'Acme', 'stock_filter' => 'low', 'medicine_type' => 'branded'],
            '-special' => ['manufacturer' => "O'Connor & Sons / Pharma", 'stock_filter' => 'in'],
            '-manufacturer' => ['manufacturer' => 'Acme'], '-low' => ['manufacturer' => 'Acme', 'stock_filter' => 'low'],
            '-empty' => ['manufacturer' => 'Unilab', 'stock_filter' => 'out'],
            '-unilab' => ['manufacturer' => 'Unilab'], '-unilab-in' => ['manufacturer' => 'Unilab', 'stock_filter' => 'in'],
            '-out' => ['stock_filter' => 'out']] as $suffix => $filters) {
            \Config\Services::resetSingle('renderer');
            $html = (new ReflectionMethod($test, 'productListFilterPage'))->invoke($test, ['branch_id' => '1'] + $filters);
            $html = preg_replace('~https?://[^"\s]+/assets/~', '/assets/', $html);
            $html = preg_replace('~<link[^>]*href="https://fonts\.[^>]*>~', '', $html);
            $html = preg_replace('~(<form id="products-filter-form" method="get" action=")[^"]+~', '$1/' . $role . '/products', $html);
            $html = preg_replace('~href="[^"]+"(?=>Clear filters</a>)~', 'href="/' . $role . '/products?branch_id=1"', $html);
            file_put_contents(__DIR__ . '/' . $role . $suffix . '.html', $html);
        }
    }
    echo "Rendered eighteen actual Products controller views with isolated SQLite records.\n";
} finally { (new ReflectionMethod($test, 'tearDown'))->invoke($test); }

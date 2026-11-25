<?php
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
});
$manifest = json_decode(file_get_contents(__DIR__ . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$source = file_get_contents($manifest['source_path']);
if (hash('sha256', $source) !== $manifest['source_sha256']) throw new RuntimeException('The source backup changed after preparation.');
$normalize = static function (string $sql): string {
    $sql = preg_replace('/^CREATE DATABASE[^\r\n]*\r?\n/m', '', $sql);
    $sql = preg_replace('/^USE\s+`[^`]+`;\s*\r?\n/m', '', $sql);
    $sql = preg_replace('/^DROP TABLE IF EXISTS[^\r\n]*\r?\n/m', '', $sql);
    return str_replace(['utf8mb4_0900_ai_ci', 'SET NAMES utf8 */'], ['utf8mb4_unicode_ci', 'SET NAMES utf8mb4 */'], $sql);
};
$normalized = $normalize($source);
$medicineUpgradePath = ROOTPATH . 'database/upgrades/2026-10-14-product-medicine-types.sql';
if (is_file($medicineUpgradePath)) $normalized .= PHP_EOL . file_get_contents($medicineUpgradePath);
$statutoryUpgradePath = ROOTPATH . 'database/upgrades/2026-10-14-senior-pwd-discounts.sql';
if (is_file($statutoryUpgradePath)) $normalized .= PHP_EOL . file_get_contents($statutoryUpgradePath);
if (preg_match('/^(?:USE|CREATE DATABASE|DROP TABLE)/mi', $normalized)) throw new RuntimeException('Unsafe database-switching statement remains.');
$full = file_get_contents($manifest['full_export']);
if (preg_match('/^(?:USE|CREATE DATABASE|DROP TABLE)/mi', $full)) throw new RuntimeException('Full export must target an empty selected database.');
$patch = file_get_contents($manifest['patch']);
$allVatable = $manifest['all_products_vatable'] ?? false;
$configuration = config(\Config\Database::class)->default;
if (!in_array($configuration['hostname'], ['localhost','127.0.0.1','::1'], true)) throw new RuntimeException('Verification only uses the local database server.');
$db = \Config\Database::connect('default');
$db->initialize();
$connection = $db->connID;
$connection->query('SET timestamp = ' . time()); // The added migration timestamp must match in both isolated imports.
$originalDatabase = $db->database;
$schemas = [];
$run = static function (string $sql) use ($connection): void {
    if (!$connection->multi_query($sql)) throw new RuntimeException($connection->error);
    do {
        if ($result = $connection->store_result()) $result->free();
        if (!$connection->more_results()) break;
        if (!$connection->next_result()) throw new RuntimeException($connection->error);
    } while (true);
};
$rows = static function (string $sql) use ($connection): array {
    $result = $connection->query($sql);
    if (!$result instanceof mysqli_result) throw new RuntimeException('Expected SQL rows.');
    return $result->fetch_all(MYSQLI_ASSOC);
};
$snapshot = static function () use ($rows): array {
    $tables = [];
    foreach ($rows('SHOW TABLES') as $entry) {
        $table = reset($entry);
        if (!preg_match('/^[a-z_]+$/D', $table)) throw new RuntimeException('Unexpected table identifier.');
        $key = $table === 'receipt_invoice_sequences' ? 'series_key' : 'id';
        $tables[$table] = $rows("SELECT * FROM `{$table}` ORDER BY `{$key}`");
    }
    return $tables;
};
$assert = static function (bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); };
$assertProductChanges = static function (array $before, array $after) use ($assert, $allVatable): int {
    $assert(array_keys($before) === array_keys($after), 'Table inventory changed.');
    $changed = 0;
    foreach ($before as $table => $originalRows) {
        $assert(count($originalRows) === count($after[$table]), 'Row count changed: ' . $table);
        if ($table !== 'products') { $assert($originalRows === $after[$table], 'Existing data changed: ' . $table); continue; }
        foreach ($originalRows as $index => $product) {
            $new = $after[$table][$index];
            if ($product !== $new) $changed++;
            foreach ($product as $field => $value) {
                if ($allVatable && $field === 'tax_treatment') {
                    $assert($new[$field] === 'taxable', 'A product is not VATable.');
                    continue;
                }
                if (!in_array($field, ['generic_name','strength'], true)) $assert($value === $new[$field], 'Unintended product field changed: ' . $field);
                elseif ($value !== null && trim($value) !== '') $assert($value === $new[$field], 'Existing medicine detail was overwritten.');
            }
        }
    }
    return $changed;
};
$report = ['source_sha256' => $manifest['source_sha256'], 'validation' => [], 'local_applied' => false];
try {
    foreach (['original','updated'] as $kind) {
        $schema = 'phx_invoice_data_review_' . bin2hex(random_bytes(6));
        $connection->query("CREATE DATABASE `{$schema}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $schemas[] = $schema;
        $connection->select_db($schema);
        $run($kind === 'original' ? $normalized : $full);
        $state = $snapshot();
        if ($kind === 'original') {
            $baseline = $state;
            $run($patch);
            $expected = $snapshot();
            $changed = $assertProductChanges($baseline, $expected);
            $assert($changed === $manifest['changed_product_count'], 'Unexpected product update count.');
            $run($patch);
            $assert($snapshot() === $expected, 'Product patch is not repeatable.');
            $report['validation'][] = 'Original backup and product patch imported successfully in an isolated MySQL database.';
            $report['validation'][] = $allVatable
                ? 'Only empty generic names/strengths and the requested VATable product classifications changed; all other values preserved.'
                : 'Only empty product generic names/strengths changed; all other tables and values preserved.';
            $report['validation'][] = 'Applying the product patch twice produces the same data.';
        } else {
            $assert($state === $expected, 'Full updated export does not match the verified product patch.');
            $report['validation'][] = 'Full updated export matches the verified patch exactly.';
            $report['row_counts'] = array_map('count', $state);
        }
    }
    $connection->select_db($originalDatabase);
    $connection->query('SET timestamp = 0');
    if (in_array('--apply', $argv, true)) {
        $connection->begin_transaction();
        try {
            $connection->query('SELECT id FROM products ORDER BY id FOR UPDATE');
            $before = $snapshot();
            file_put_contents(__DIR__ . ($allVatable ? '/local-products-before-vatable.json' : '/local-products-before.json'), json_encode($before['products'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            // Use the transaction already opened, keeping the proof checks before commit.
            $transactionPatch = str_replace(['START TRANSACTION;', 'COMMIT;'], '', $patch);
            $run($transactionPatch);
            $after = $snapshot();
            $report['local_changed_products'] = $assertProductChanges($before, $after);
            if ($allVatable) $report['local_vatable_products'] = count($after['products']);
            $updates = array_column($manifest['updates'], null, 'id');
            foreach ($after['products'] as $product) {
                $planned = $updates[$product['id']] ?? null;
                if (!$planned || $product['product_name'] !== $planned['name'] || $product['sku'] !== $planned['sku']) continue;
                foreach (['generic_name','strength'] as $field) {
                    if (!isset($planned[$field])) continue;
                    $prior = array_values(array_filter($before['products'], static fn ($p) => $p['id'] === $product['id']))[0];
                    if ($prior[$field] === null || trim($prior[$field]) === '') $assert($product[$field] === $planned[$field], 'Planned detail was not applied.');
                }
            }
            $connection->commit();
            $report['local_applied'] = true;
            $report['validation'][] = $allVatable
                ? 'All local products marked VATable; historical tables, stock, prices, timestamps and counters preserved.'
                : 'Local product updates committed; historical tables, stock, prices, tax classifications and counters preserved.';
        } catch (Throwable $error) {
            $connection->rollback();
            throw $error;
        }
    }
    $assert(hash_file('sha256', $manifest['source_path']) === $manifest['source_sha256'], 'Source backup was modified.');
    $reportFile = in_array('--apply', $argv, true) ? '/verification.json' : '/export-verification.json';
    file_put_contents(__DIR__ . $reportFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    $connection->select_db($originalDatabase);
    $connection->query('SET timestamp = 0');
    foreach ($schemas as $schema) {
        if (!preg_match('/^phx_invoice_data_review_[a-f0-9]{12}$/D', $schema) || $schema === $originalDatabase) throw new RuntimeException('Temporary database cleanup guard failed.');
        $connection->query("DROP DATABASE `{$schema}`");
    }
}

-- Select the EXISTING application database. Requires products.tax_treatment from the invoice-field upgrade.
-- Marks every product VATable as requested. Repeatable; existing sale snapshots, prices, stock and timestamps are preserved.
START TRANSACTION;
UPDATE `products` SET `tax_treatment` = 'taxable', `updated_at` = `updated_at` WHERE NOT (`tax_treatment` <=> 'taxable');
COMMIT;

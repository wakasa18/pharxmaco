-- Pharxmaco: twelve additional branded medicine catalog entries, October 15, 2026.
-- Select the EXISTING database in phpMyAdmin. Requires stock/invoice/medicine/benefit updates 000010-000013.
-- Adds only missing branded product records and DEMO inventory in one active branch.
-- Requested demo data: 50 units per product; sample unit prices/costs; expiry one year after import.
-- Demo prices/expiry are examples, not market quotations or real physical batch details.
-- All new medicines are Branded and VATable, with Senior Citizen/PWD eligibility enabled.
-- Verify required prescriptions, beneficiary documents and exclusive use at checkout.
-- No existing products, stock, prices, classifications, users, sales or invoice snapshots are updated.
-- Reimport skips existing SKUs or matching product names, including inactive or deleted products.
-- Each branch row is created only for a product inserted by this import; it never restocks existing rows.
-- Manufacturer/supplier are left unset for verification against actual stock.
SELECT id, generic_name, strength, medicine_type, senior_pwd_eligible, tax_treatment FROM products LIMIT 0;
SELECT id, stock_delivery_id FROM branch_products LIMIT 0;
SELECT id, action_type, quantity, previous_stock, new_stock, remarks FROM stock_logs LIMIT 0;
SELECT id, is_deleted, is_permanently_deleted FROM categories LIMIT 0;
START TRANSACTION;
-- Prefer Main Branch; otherwise use the first active branch. Set a specific active branch ID here if needed.
SET @phx_brand_branch = (SELECT id FROM branches WHERE status = 'active'
    ORDER BY CASE WHEN branch_name = 'Main Branch' THEN 0 ELSE 1 END, id LIMIT 1);
SET @phx_brand_created = NOW();
SET @phx_brand_expiry = DATE_ADD(CURDATE(), INTERVAL 1 YEAR);
SET @phx_brand_added = 0;

-- Bioflu 10 mg / 2 mg / 500 mg tablet
SET @phx_brand_category = (SELECT id FROM categories WHERE category_name = 'Cough and Cold'
    AND deleted_at IS NULL AND is_deleted = 0 AND is_permanently_deleted = 0 LIMIT 1);
INSERT INTO products (product_name, category_id, sku, unit, cost_price, price, stock, reorder_level, status,
    generic_name, strength, tax_treatment, medicine_type, senior_pwd_eligible, manufacturer, supplier_id, created_at, updated_at)
SELECT 'Bioflu 10 mg / 2 mg / 500 mg tablet', @phx_brand_category, 'DEMO-BRD-BIOFLU', 'tablet', 7, 10, 50, 5, 'active',
    'Phenylephrine hydrochloride + Chlorphenamine maleate + Paracetamol', '10 mg + 2 mg + 500 mg', 'taxable', 'branded', 1, NULL, NULL, @phx_brand_created, @phx_brand_created
WHERE @phx_brand_branch IS NOT NULL AND @phx_brand_category IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM products WHERE sku = 'DEMO-BRD-BIOFLU' OR LOWER(TRIM(product_name)) IN ('bioflu 10 mg / 2 mg / 500 mg tablet', 'bioflu', 'bioflu tablet'));
SET @phx_brand_new_id = IF(ROW_COUNT() = 1, LAST_INSERT_ID(), NULL);
SET @phx_brand_added = @phx_brand_added + IF(@phx_brand_new_id IS NULL, 0, 1);
INSERT INTO branch_products (branch_id, product_id, stock, reorder_level, expiration_date, price, cost_price,
    status, stock_delivery_id, created_at, updated_at)
SELECT @phx_brand_branch, @phx_brand_new_id, 50, 5, @phx_brand_expiry, 10, 7, 'active', REPLACE(UUID(), '-', ''), @phx_brand_created, @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;
INSERT INTO stock_logs (product_id, branch_id, user_id, action_type, quantity, previous_stock, new_stock, remarks, created_at)
SELECT @phx_brand_new_id, @phx_brand_branch, NULL, 'stock_in', 50, 0, 50,
    'Demo branded product seed: sample stock, prices and expiry; verify before live use', @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;

-- Solmux 500 mg capsule
SET @phx_brand_category = (SELECT id FROM categories WHERE category_name = 'Cough and Cold'
    AND deleted_at IS NULL AND is_deleted = 0 AND is_permanently_deleted = 0 LIMIT 1);
INSERT INTO products (product_name, category_id, sku, unit, cost_price, price, stock, reorder_level, status,
    generic_name, strength, tax_treatment, medicine_type, senior_pwd_eligible, manufacturer, supplier_id, created_at, updated_at)
SELECT 'Solmux 500 mg capsule', @phx_brand_category, 'DEMO-BRD-SOLMUX500', 'capsule', 8.4, 12, 50, 5, 'active',
    'Carbocisteine', '500 mg', 'taxable', 'branded', 1, NULL, NULL, @phx_brand_created, @phx_brand_created
WHERE @phx_brand_branch IS NOT NULL AND @phx_brand_category IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM products WHERE sku = 'DEMO-BRD-SOLMUX500' OR LOWER(TRIM(product_name)) IN ('solmux 500 mg capsule', 'solmux', 'solmux capsule'));
SET @phx_brand_new_id = IF(ROW_COUNT() = 1, LAST_INSERT_ID(), NULL);
SET @phx_brand_added = @phx_brand_added + IF(@phx_brand_new_id IS NULL, 0, 1);
INSERT INTO branch_products (branch_id, product_id, stock, reorder_level, expiration_date, price, cost_price,
    status, stock_delivery_id, created_at, updated_at)
SELECT @phx_brand_branch, @phx_brand_new_id, 50, 5, @phx_brand_expiry, 12, 8.4, 'active', REPLACE(UUID(), '-', ''), @phx_brand_created, @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;
INSERT INTO stock_logs (product_id, branch_id, user_id, action_type, quantity, previous_stock, new_stock, remarks, created_at)
SELECT @phx_brand_new_id, @phx_brand_branch, NULL, 'stock_in', 50, 0, 50,
    'Demo branded product seed: sample stock, prices and expiry; verify before live use', @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;

-- Diatabs 2 mg capsule
SET @phx_brand_category = (SELECT id FROM categories WHERE category_name = 'Digestive Health'
    AND deleted_at IS NULL AND is_deleted = 0 AND is_permanently_deleted = 0 LIMIT 1);
INSERT INTO products (product_name, category_id, sku, unit, cost_price, price, stock, reorder_level, status,
    generic_name, strength, tax_treatment, medicine_type, senior_pwd_eligible, manufacturer, supplier_id, created_at, updated_at)
SELECT 'Diatabs 2 mg capsule', @phx_brand_category, 'DEMO-BRD-DIATABS2', 'capsule', 5.6, 8, 50, 5, 'active',
    'Loperamide hydrochloride', '2 mg', 'taxable', 'branded', 1, NULL, NULL, @phx_brand_created, @phx_brand_created
WHERE @phx_brand_branch IS NOT NULL AND @phx_brand_category IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM products WHERE sku = 'DEMO-BRD-DIATABS2' OR LOWER(TRIM(product_name)) IN ('diatabs 2 mg capsule', 'diatabs', 'diatabs capsule'));
SET @phx_brand_new_id = IF(ROW_COUNT() = 1, LAST_INSERT_ID(), NULL);
SET @phx_brand_added = @phx_brand_added + IF(@phx_brand_new_id IS NULL, 0, 1);
INSERT INTO branch_products (branch_id, product_id, stock, reorder_level, expiration_date, price, cost_price,
    status, stock_delivery_id, created_at, updated_at)
SELECT @phx_brand_branch, @phx_brand_new_id, 50, 5, @phx_brand_expiry, 8, 5.6, 'active', REPLACE(UUID(), '-', ''), @phx_brand_created, @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;
INSERT INTO stock_logs (product_id, branch_id, user_id, action_type, quantity, previous_stock, new_stock, remarks, created_at)
SELECT @phx_brand_new_id, @phx_brand_branch, NULL, 'stock_in', 50, 0, 50,
    'Demo branded product seed: sample stock, prices and expiry; verify before live use', @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;

-- Allerta 10 mg tablet
SET @phx_brand_category = (SELECT id FROM categories WHERE category_name = 'Allergy and Respiratory'
    AND deleted_at IS NULL AND is_deleted = 0 AND is_permanently_deleted = 0 LIMIT 1);
INSERT INTO products (product_name, category_id, sku, unit, cost_price, price, stock, reorder_level, status,
    generic_name, strength, tax_treatment, medicine_type, senior_pwd_eligible, manufacturer, supplier_id, created_at, updated_at)
SELECT 'Allerta 10 mg tablet', @phx_brand_category, 'DEMO-BRD-ALLERTA10', 'tablet', 8.4, 12, 50, 5, 'active',
    'Loratadine', '10 mg', 'taxable', 'branded', 1, NULL, NULL, @phx_brand_created, @phx_brand_created
WHERE @phx_brand_branch IS NOT NULL AND @phx_brand_category IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM products WHERE sku = 'DEMO-BRD-ALLERTA10' OR LOWER(TRIM(product_name)) IN ('allerta 10 mg tablet', 'allerta', 'allerta tablet'));
SET @phx_brand_new_id = IF(ROW_COUNT() = 1, LAST_INSERT_ID(), NULL);
SET @phx_brand_added = @phx_brand_added + IF(@phx_brand_new_id IS NULL, 0, 1);
INSERT INTO branch_products (branch_id, product_id, stock, reorder_level, expiration_date, price, cost_price,
    status, stock_delivery_id, created_at, updated_at)
SELECT @phx_brand_branch, @phx_brand_new_id, 50, 5, @phx_brand_expiry, 12, 8.4, 'active', REPLACE(UUID(), '-', ''), @phx_brand_created, @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;
INSERT INTO stock_logs (product_id, branch_id, user_id, action_type, quantity, previous_stock, new_stock, remarks, created_at)
SELECT @phx_brand_new_id, @phx_brand_branch, NULL, 'stock_in', 50, 0, 50,
    'Demo branded product seed: sample stock, prices and expiry; verify before live use', @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;

-- Alnix 10 mg tablet
SET @phx_brand_category = (SELECT id FROM categories WHERE category_name = 'Allergy and Respiratory'
    AND deleted_at IS NULL AND is_deleted = 0 AND is_permanently_deleted = 0 LIMIT 1);
INSERT INTO products (product_name, category_id, sku, unit, cost_price, price, stock, reorder_level, status,
    generic_name, strength, tax_treatment, medicine_type, senior_pwd_eligible, manufacturer, supplier_id, created_at, updated_at)
SELECT 'Alnix 10 mg tablet', @phx_brand_category, 'DEMO-BRD-ALNIX10', 'tablet', 7, 10, 50, 5, 'active',
    'Cetirizine dihydrochloride', '10 mg', 'taxable', 'branded', 1, NULL, NULL, @phx_brand_created, @phx_brand_created
WHERE @phx_brand_branch IS NOT NULL AND @phx_brand_category IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM products WHERE sku = 'DEMO-BRD-ALNIX10' OR LOWER(TRIM(product_name)) IN ('alnix 10 mg tablet', 'alnix', 'alnix tablet'));
SET @phx_brand_new_id = IF(ROW_COUNT() = 1, LAST_INSERT_ID(), NULL);
SET @phx_brand_added = @phx_brand_added + IF(@phx_brand_new_id IS NULL, 0, 1);
INSERT INTO branch_products (branch_id, product_id, stock, reorder_level, expiration_date, price, cost_price,
    status, stock_delivery_id, created_at, updated_at)
SELECT @phx_brand_branch, @phx_brand_new_id, 50, 5, @phx_brand_expiry, 10, 7, 'active', REPLACE(UUID(), '-', ''), @phx_brand_created, @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;
INSERT INTO stock_logs (product_id, branch_id, user_id, action_type, quantity, previous_stock, new_stock, remarks, created_at)
SELECT @phx_brand_new_id, @phx_brand_branch, NULL, 'stock_in', 50, 0, 50,
    'Demo branded product seed: sample stock, prices and expiry; verify before live use', @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;

-- Medicol Advance 200 mg softgel capsule
SET @phx_brand_category = (SELECT id FROM categories WHERE category_name = 'Pain Relief'
    AND deleted_at IS NULL AND is_deleted = 0 AND is_permanently_deleted = 0 LIMIT 1);
INSERT INTO products (product_name, category_id, sku, unit, cost_price, price, stock, reorder_level, status,
    generic_name, strength, tax_treatment, medicine_type, senior_pwd_eligible, manufacturer, supplier_id, created_at, updated_at)
SELECT 'Medicol Advance 200 mg softgel capsule', @phx_brand_category, 'DEMO-BRD-MEDICOL200', 'capsule', 5.6, 8, 50, 5, 'active',
    'Ibuprofen', '200 mg', 'taxable', 'branded', 1, NULL, NULL, @phx_brand_created, @phx_brand_created
WHERE @phx_brand_branch IS NOT NULL AND @phx_brand_category IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM products WHERE sku = 'DEMO-BRD-MEDICOL200' OR LOWER(TRIM(product_name)) IN ('medicol advance 200 mg softgel capsule', 'medicol advance 200', 'medicol advance 200 capsule'));
SET @phx_brand_new_id = IF(ROW_COUNT() = 1, LAST_INSERT_ID(), NULL);
SET @phx_brand_added = @phx_brand_added + IF(@phx_brand_new_id IS NULL, 0, 1);
INSERT INTO branch_products (branch_id, product_id, stock, reorder_level, expiration_date, price, cost_price,
    status, stock_delivery_id, created_at, updated_at)
SELECT @phx_brand_branch, @phx_brand_new_id, 50, 5, @phx_brand_expiry, 8, 5.6, 'active', REPLACE(UUID(), '-', ''), @phx_brand_created, @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;
INSERT INTO stock_logs (product_id, branch_id, user_id, action_type, quantity, previous_stock, new_stock, remarks, created_at)
SELECT @phx_brand_new_id, @phx_brand_branch, NULL, 'stock_in', 50, 0, 50,
    'Demo branded product seed: sample stock, prices and expiry; verify before live use', @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;

-- Medicol Advance 400 mg softgel capsule
SET @phx_brand_category = (SELECT id FROM categories WHERE category_name = 'Pain Relief'
    AND deleted_at IS NULL AND is_deleted = 0 AND is_permanently_deleted = 0 LIMIT 1);
INSERT INTO products (product_name, category_id, sku, unit, cost_price, price, stock, reorder_level, status,
    generic_name, strength, tax_treatment, medicine_type, senior_pwd_eligible, manufacturer, supplier_id, created_at, updated_at)
SELECT 'Medicol Advance 400 mg softgel capsule', @phx_brand_category, 'DEMO-BRD-MEDICOL400', 'capsule', 8.4, 12, 50, 5, 'active',
    'Ibuprofen', '400 mg', 'taxable', 'branded', 1, NULL, NULL, @phx_brand_created, @phx_brand_created
WHERE @phx_brand_branch IS NOT NULL AND @phx_brand_category IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM products WHERE sku = 'DEMO-BRD-MEDICOL400' OR LOWER(TRIM(product_name)) IN ('medicol advance 400 mg softgel capsule', 'medicol advance 400', 'medicol advance 400 capsule'));
SET @phx_brand_new_id = IF(ROW_COUNT() = 1, LAST_INSERT_ID(), NULL);
SET @phx_brand_added = @phx_brand_added + IF(@phx_brand_new_id IS NULL, 0, 1);
INSERT INTO branch_products (branch_id, product_id, stock, reorder_level, expiration_date, price, cost_price,
    status, stock_delivery_id, created_at, updated_at)
SELECT @phx_brand_branch, @phx_brand_new_id, 50, 5, @phx_brand_expiry, 12, 8.4, 'active', REPLACE(UUID(), '-', ''), @phx_brand_created, @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;
INSERT INTO stock_logs (product_id, branch_id, user_id, action_type, quantity, previous_stock, new_stock, remarks, created_at)
SELECT @phx_brand_new_id, @phx_brand_branch, NULL, 'stock_in', 50, 0, 50,
    'Demo branded product seed: sample stock, prices and expiry; verify before live use', @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;

-- Dolfenal 500 mg tablet
SET @phx_brand_category = (SELECT id FROM categories WHERE category_name = 'Pain Relief'
    AND deleted_at IS NULL AND is_deleted = 0 AND is_permanently_deleted = 0 LIMIT 1);
INSERT INTO products (product_name, category_id, sku, unit, cost_price, price, stock, reorder_level, status,
    generic_name, strength, tax_treatment, medicine_type, senior_pwd_eligible, manufacturer, supplier_id, created_at, updated_at)
SELECT 'Dolfenal 500 mg tablet', @phx_brand_category, 'DEMO-BRD-DOLFENAL500', 'tablet', 17.5, 25, 50, 5, 'active',
    'Mefenamic acid', '500 mg', 'taxable', 'branded', 1, NULL, NULL, @phx_brand_created, @phx_brand_created
WHERE @phx_brand_branch IS NOT NULL AND @phx_brand_category IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM products WHERE sku = 'DEMO-BRD-DOLFENAL500' OR LOWER(TRIM(product_name)) IN ('dolfenal 500 mg tablet', 'dolfenal 500', 'dolfenal 500 tablet'));
SET @phx_brand_new_id = IF(ROW_COUNT() = 1, LAST_INSERT_ID(), NULL);
SET @phx_brand_added = @phx_brand_added + IF(@phx_brand_new_id IS NULL, 0, 1);
INSERT INTO branch_products (branch_id, product_id, stock, reorder_level, expiration_date, price, cost_price,
    status, stock_delivery_id, created_at, updated_at)
SELECT @phx_brand_branch, @phx_brand_new_id, 50, 5, @phx_brand_expiry, 25, 17.5, 'active', REPLACE(UUID(), '-', ''), @phx_brand_created, @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;
INSERT INTO stock_logs (product_id, branch_id, user_id, action_type, quantity, previous_stock, new_stock, remarks, created_at)
SELECT @phx_brand_new_id, @phx_brand_branch, NULL, 'stock_in', 50, 0, 50,
    'Demo branded product seed: sample stock, prices and expiry; verify before live use', @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;

-- Skelan 550 mg tablet
SET @phx_brand_category = (SELECT id FROM categories WHERE category_name = 'Pain Relief'
    AND deleted_at IS NULL AND is_deleted = 0 AND is_permanently_deleted = 0 LIMIT 1);
INSERT INTO products (product_name, category_id, sku, unit, cost_price, price, stock, reorder_level, status,
    generic_name, strength, tax_treatment, medicine_type, senior_pwd_eligible, manufacturer, supplier_id, created_at, updated_at)
SELECT 'Skelan 550 mg tablet', @phx_brand_category, 'DEMO-BRD-SKELAN550', 'tablet', 14, 20, 50, 5, 'active',
    'Naproxen sodium', '550 mg', 'taxable', 'branded', 1, NULL, NULL, @phx_brand_created, @phx_brand_created
WHERE @phx_brand_branch IS NOT NULL AND @phx_brand_category IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM products WHERE sku = 'DEMO-BRD-SKELAN550' OR LOWER(TRIM(product_name)) IN ('skelan 550 mg tablet', 'skelan 550', 'skelan 550 tablet'));
SET @phx_brand_new_id = IF(ROW_COUNT() = 1, LAST_INSERT_ID(), NULL);
SET @phx_brand_added = @phx_brand_added + IF(@phx_brand_new_id IS NULL, 0, 1);
INSERT INTO branch_products (branch_id, product_id, stock, reorder_level, expiration_date, price, cost_price,
    status, stock_delivery_id, created_at, updated_at)
SELECT @phx_brand_branch, @phx_brand_new_id, 50, 5, @phx_brand_expiry, 20, 14, 'active', REPLACE(UUID(), '-', ''), @phx_brand_created, @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;
INSERT INTO stock_logs (product_id, branch_id, user_id, action_type, quantity, previous_stock, new_stock, remarks, created_at)
SELECT @phx_brand_new_id, @phx_brand_branch, NULL, 'stock_in', 50, 0, 50,
    'Demo branded product seed: sample stock, prices and expiry; verify before live use', @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;

-- Kremil-S Advance 10 mg / 800 mg / 165 mg chewable tablet
SET @phx_brand_category = (SELECT id FROM categories WHERE category_name = 'Digestive Health'
    AND deleted_at IS NULL AND is_deleted = 0 AND is_permanently_deleted = 0 LIMIT 1);
INSERT INTO products (product_name, category_id, sku, unit, cost_price, price, stock, reorder_level, status,
    generic_name, strength, tax_treatment, medicine_type, senior_pwd_eligible, manufacturer, supplier_id, created_at, updated_at)
SELECT 'Kremil-S Advance 10 mg / 800 mg / 165 mg chewable tablet', @phx_brand_category, 'DEMO-BRD-KREMILS-ADV', 'tablet', 10.5, 15, 50, 5, 'active',
    'Famotidine + Calcium carbonate + Magnesium hydroxide', '10 mg + 800 mg + 165 mg', 'taxable', 'branded', 1, NULL, NULL, @phx_brand_created, @phx_brand_created
WHERE @phx_brand_branch IS NOT NULL AND @phx_brand_category IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM products WHERE sku = 'DEMO-BRD-KREMILS-ADV' OR LOWER(TRIM(product_name)) IN ('kremil-s advance 10 mg / 800 mg / 165 mg chewable tablet', 'kremil-s advance', 'kremil-s advance tablet'));
SET @phx_brand_new_id = IF(ROW_COUNT() = 1, LAST_INSERT_ID(), NULL);
SET @phx_brand_added = @phx_brand_added + IF(@phx_brand_new_id IS NULL, 0, 1);
INSERT INTO branch_products (branch_id, product_id, stock, reorder_level, expiration_date, price, cost_price,
    status, stock_delivery_id, created_at, updated_at)
SELECT @phx_brand_branch, @phx_brand_new_id, 50, 5, @phx_brand_expiry, 15, 10.5, 'active', REPLACE(UUID(), '-', ''), @phx_brand_created, @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;
INSERT INTO stock_logs (product_id, branch_id, user_id, action_type, quantity, previous_stock, new_stock, remarks, created_at)
SELECT @phx_brand_new_id, @phx_brand_branch, NULL, 'stock_in', 50, 0, 50,
    'Demo branded product seed: sample stock, prices and expiry; verify before live use', @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;

-- Tuseran Forte 15 mg / 25 mg / 325 mg capsule
SET @phx_brand_category = (SELECT id FROM categories WHERE category_name = 'Cough and Cold'
    AND deleted_at IS NULL AND is_deleted = 0 AND is_permanently_deleted = 0 LIMIT 1);
INSERT INTO products (product_name, category_id, sku, unit, cost_price, price, stock, reorder_level, status,
    generic_name, strength, tax_treatment, medicine_type, senior_pwd_eligible, manufacturer, supplier_id, created_at, updated_at)
SELECT 'Tuseran Forte 15 mg / 25 mg / 325 mg capsule', @phx_brand_category, 'DEMO-BRD-TUSERAN', 'capsule', 8.4, 12, 50, 5, 'active',
    'Dextromethorphan hydrobromide + Phenylpropanolamine hydrochloride + Paracetamol', '15 mg + 25 mg + 325 mg', 'taxable', 'branded', 1, NULL, NULL, @phx_brand_created, @phx_brand_created
WHERE @phx_brand_branch IS NOT NULL AND @phx_brand_category IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM products WHERE sku = 'DEMO-BRD-TUSERAN' OR LOWER(TRIM(product_name)) IN ('tuseran forte 15 mg / 25 mg / 325 mg capsule', 'tuseran forte', 'tuseran forte capsule'));
SET @phx_brand_new_id = IF(ROW_COUNT() = 1, LAST_INSERT_ID(), NULL);
SET @phx_brand_added = @phx_brand_added + IF(@phx_brand_new_id IS NULL, 0, 1);
INSERT INTO branch_products (branch_id, product_id, stock, reorder_level, expiration_date, price, cost_price,
    status, stock_delivery_id, created_at, updated_at)
SELECT @phx_brand_branch, @phx_brand_new_id, 50, 5, @phx_brand_expiry, 12, 8.4, 'active', REPLACE(UUID(), '-', ''), @phx_brand_created, @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;
INSERT INTO stock_logs (product_id, branch_id, user_id, action_type, quantity, previous_stock, new_stock, remarks, created_at)
SELECT @phx_brand_new_id, @phx_brand_branch, NULL, 'stock_in', 50, 0, 50,
    'Demo branded product seed: sample stock, prices and expiry; verify before live use', @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;

-- Decolgen Forte 25 mg / 2 mg / 500 mg tablet
SET @phx_brand_category = (SELECT id FROM categories WHERE category_name = 'Cough and Cold'
    AND deleted_at IS NULL AND is_deleted = 0 AND is_permanently_deleted = 0 LIMIT 1);
INSERT INTO products (product_name, category_id, sku, unit, cost_price, price, stock, reorder_level, status,
    generic_name, strength, tax_treatment, medicine_type, senior_pwd_eligible, manufacturer, supplier_id, created_at, updated_at)
SELECT 'Decolgen Forte 25 mg / 2 mg / 500 mg tablet', @phx_brand_category, 'DEMO-BRD-DECOLGEN', 'tablet', 6.3, 9, 50, 5, 'active',
    'Phenylpropanolamine hydrochloride + Chlorphenamine maleate + Paracetamol', '25 mg + 2 mg + 500 mg', 'taxable', 'branded', 1, NULL, NULL, @phx_brand_created, @phx_brand_created
WHERE @phx_brand_branch IS NOT NULL AND @phx_brand_category IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM products WHERE sku = 'DEMO-BRD-DECOLGEN' OR LOWER(TRIM(product_name)) IN ('decolgen forte 25 mg / 2 mg / 500 mg tablet', 'decolgen forte', 'decolgen forte tablet'));
SET @phx_brand_new_id = IF(ROW_COUNT() = 1, LAST_INSERT_ID(), NULL);
SET @phx_brand_added = @phx_brand_added + IF(@phx_brand_new_id IS NULL, 0, 1);
INSERT INTO branch_products (branch_id, product_id, stock, reorder_level, expiration_date, price, cost_price,
    status, stock_delivery_id, created_at, updated_at)
SELECT @phx_brand_branch, @phx_brand_new_id, 50, 5, @phx_brand_expiry, 9, 6.3, 'active', REPLACE(UUID(), '-', ''), @phx_brand_created, @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;
INSERT INTO stock_logs (product_id, branch_id, user_id, action_type, quantity, previous_stock, new_stock, remarks, created_at)
SELECT @phx_brand_new_id, @phx_brand_branch, NULL, 'stock_in', 50, 0, 50,
    'Demo branded product seed: sample stock, prices and expiry; verify before live use', @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;

COMMIT;
SELECT CASE WHEN @phx_brand_branch IS NULL THEN 'No active branch; no products added'
    ELSE 'Branded DEMO products ready: 50 units each with sample prices and expiry' END AS upgrade_status,
    @phx_brand_added AS products_added, @phx_brand_branch AS branch_id;
SELECT id, product_name, generic_name, strength, medicine_type, senior_pwd_eligible FROM products
WHERE sku IN ('DEMO-BRD-BIOFLU', 'DEMO-BRD-SOLMUX500', 'DEMO-BRD-DIATABS2', 'DEMO-BRD-ALLERTA10', 'DEMO-BRD-ALNIX10', 'DEMO-BRD-MEDICOL200', 'DEMO-BRD-MEDICOL400', 'DEMO-BRD-DOLFENAL500', 'DEMO-BRD-SKELAN550', 'DEMO-BRD-KREMILS-ADV', 'DEMO-BRD-TUSERAN', 'DEMO-BRD-DECOLGEN') ORDER BY product_name;
SET @phx_brand_branch = NULL, @phx_brand_category = NULL, @phx_brand_created = NULL, @phx_brand_expiry = NULL,
    @phx_brand_new_id = NULL, @phx_brand_added = NULL;

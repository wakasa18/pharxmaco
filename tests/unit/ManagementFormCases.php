<?php

trait ManagementFormCases
{
    private function managementFixtures(): array
    {
        $product = $this->expiryProductEditPost(['product_name' => "Children’s Vitamin C + Zinc (10%)", 'sku' => 'RX-500_01/A.2',
            'expiration_date' => '2030-01-01', 'generic_name' => 'Vitamin C + Zinc', 'strength' => '500 mg / 5 mL', 'manufacturer' => "O'Connor & Sons, Inc."]);
        foreach ([
            'branch_products' => ['created_at' => 'TEXT'],
            'branches' => ['branch_code' => 'TEXT', 'created_at' => 'TEXT', 'updated_at' => 'TEXT'],
            'users' => ['created_at' => 'TEXT'],
            'suppliers' => ['contact_person' => 'TEXT', 'contact_number' => 'TEXT', 'email' => 'TEXT', 'address' => 'TEXT', 'status' => 'TEXT'],
            'discounts' => ['description' => 'TEXT'],
        ] as $table => $columns) foreach ($columns as $name => $type) $this->db->query('ALTER TABLE ' . $table . ' ADD COLUMN ' . $name . ' ' . $type);
        $this->db->table('branches')->where('id', 1)->update(['branch_code' => 'MAIN']);
        $this->db->table('suppliers')->where('id', 1)->update(['status' => 'active']);
        return [
            'product' => [\App\Controllers\Products::class, 'products', 'product_name', $product],
            'category' => [\App\Controllers\Categories::class, 'categories', 'category_name', ['category_name' => 'Cough, Cold & Allergy']],
            'supplier' => [\App\Controllers\Admin\Suppliers::class, 'suppliers', 'supplier_name', ['supplier_name' => "O'Connor & Sons, Inc.",
                'contact_person' => 'Ma. José O’Connor-Santos', 'contact_number' => '+63 (917) 123-4567', 'email' => 'orders+demo@example.com',
                'address' => "Unit #3, 12/F, St. Niño St.\nCagayan de Oro", 'status' => 'active']],
            'branch' => [\App\Controllers\Admin\Branches::class, 'branches', 'branch_name', ['branch_name' => 'St. Niño - Main (2)',
                'branch_code' => 'BR-02', 'contact_number' => '(02) 8123-4567', 'address' => 'Unit #2, Main St.', 'status' => 'active']],
            'user' => [\App\Controllers\Admin\Users::class, 'users', 'full_name', ['full_name' => 'Ma. José O’Connor-Santos',
                'username' => 'jose.oconnor-2', 'password' => 'Demo!Pass+2026#', 'confirm_password' => 'Demo!Pass+2026#', 'role' => 'admin', 'status' => 'active']],
            'discount' => [\App\Controllers\Admin\Discounts::class, 'discounts', 'discount_name', ['discount_name' => 'Vitamin C + Zinc (20% off)',
                'discount_type' => 'percentage', 'discount_value' => '20', 'description' => 'Ask sales@example.com for details!', 'applies_to' => 'all',
                'minimum_purchase' => '0', 'max_discount_amount' => '', 'start_date' => '', 'end_date' => '', 'status' => 'active']],
        ];
    }

    public function testManagementRulesAllowFieldPunctuationAndRejectUnsupportedSymbols(): void
    {
        $rules = new \App\Validation\ManagementRules();
        $cases = [
            'catalog' => [['Vitamin C + Zinc (500 mg / 5 mL)', "Children’s medicine – 10%", "O'Connor & Sons, Inc.", 'Saline 0.9%', 'Vitamin D₃'],
                ['@@@', 'Brand<test>', 'Name!!!', "Name\0Here", "Name\nHere", '💊💊']],
            'person' => [['Ma. José O’Connor-Santos', 'Santos, Juan Jr.'], ['1234', '@@@', '<script>', 'Ana123', 'Ana+Juan']],
            'code' => [['RX-500_01/A.2', '123456789'], ['', '   ', '@@@', 'ABC 123', 'ABC<123', '💊-01']],
            'username' => [['jose.oconnor-2', 'demo_admin'], ['user@name', 'user/name', 'user name', '---']],
            'phone' => [['+63 (917) 123-4567', '(02) 8123-4567', '09171234567'], ['++639171234567', '0917+1234567', '---', '123', '0917@1234567']],
            'address' => [["Unit #3, 12/F, St. Niño St.\nCagayan de Oro"], ['@@@', '<b>Address</b>', "Room\0One"]],
            'money' => [['0', '10.25', '99999999.99'], ['₱100', '1e2', '1.001', '-5', '100000000', '10.00' . "\n"]],
            'quantity' => [['0', '120', '1000000'], ['1e2', '1.5', '-5', '1000001']],
        ];
        foreach ($cases as $type => [$valid, $invalid]) {
            foreach ($valid as $value) $this->assertTrue($rules->management_text($value, $type, []), $type . ' should accept ' . $value);
            foreach ($invalid as $value) $this->assertFalse($rules->management_text($value, $type, []), $type . ' should reject ' . json_encode($value));
            $this->assertFalse($rules->management_text([], $type, []));
        }
    }

    public function testRequiredSkuAndProductCharacterRulesCannotBeBypassedOnCreateOrEdit(): void
    {
        $forms = $this->managementFixtures();
        $post = $forms['product'][3];
        $product = $this->row('products');
        $branch = $this->row('branch_products');
        foreach ([['sku' => ''], ['sku' => null], ['sku' => '   '], ['sku' => '@@@'], ['sku' => 'BIO 500'],
            ['product_name' => '@@@'], ['product_name' => '<script>'], ['unit' => '!!!'], ['generic_name' => '$$$'],
            ['manufacturer' => '<b>Maker</b>'], ['strength' => '500mg<script>'], ['price' => '1e2'], ['cost_price' => '1.001'], ['stock' => '1e2'],
        ] as $change) {
            $field = array_key_first($change);
            foreach (['store', 'update'] as $action) {
                $controller = $this->controller(\App\Controllers\Products::class, array_replace($post, $change));
                $action === 'store' ? $controller->store() : $controller->update(1);
                $this->assertArrayHasKey($field, session()->getFlashdata('errors') ?? [], $action . ': ' . json_encode($change));
                $this->assertSame(1, $this->db->table('products')->countAllResults());
                $this->assertSame($product, $this->row('products'));
                $this->assertSame($branch, $this->row('branch_products'));
                $this->assertSame(0, $this->db->table('stock_logs')->countAllResults());
            }
        }
        session()->set(['role' => 'cashier', 'branch_id' => 1]);
        $this->controller(\App\Controllers\Products::class, array_replace($post, ['sku' => '']))->store();
        $this->assertArrayHasKey('sku', session()->getFlashdata('errors'));
        $this->assertSame(1, $this->db->table('products')->countAllResults());
    }

    public function testManagementCreateAndUpdateKeepSupportedSymbolsAndPasswordPunctuation(): void
    {
        helper(['url', 'form']);
        foreach ($this->managementFixtures() as $name => [$class, $table, $field, $post]) {
            $count = $this->db->table($table)->countAllResults();
            $this->controller($class, $post)->store();
            $this->assertSame($count + 1, $this->db->table($table)->countAllResults(), $name . ': ' . json_encode(session()->getFlashdata('errors')));
            $created = $this->db->table($table)->where($field, $post[$field])->get()->getRowArray();
            $this->assertNotNull($created, $name);
            if ($name === 'user') $this->assertTrue(password_verify($post['password'], $created['password']));
            $post[$field] .= $name === 'user' ? ' Jr.' : ' - Updated';
            $this->controller($class, $post)->update((int) $created['id']);
            $saved = $this->db->table($table)->where('id', $created['id'])->get()->getRowArray();
            $this->assertSame($post[$field], $saved[$field], $name . ' update');
            if ($name === 'user') $this->assertSame($created['password'], $saved['password']);
            if ($name === 'supplier') $this->assertSame($post['email'], $saved['email']);
        }
    }

    public function testEveryManagementControllerRejectsSymbolOnlyNamesOnCreateAndUpdate(): void
    {
        helper(['url', 'form']);
        foreach ($this->managementFixtures() as $name => [$class, $table, $field, $post]) {
            $before = $this->db->table($table)->orderBy('id')->get()->getResultArray();
            foreach (['store', 'update'] as $action) {
                $controller = $this->controller($class, array_replace($post, [$field => '@@@']));
                $action === 'store' ? $controller->store() : $controller->update(1);
                $this->assertArrayHasKey($field, service('validation')->getErrors(), $name . ' ' . $action);
                $this->assertSame($before, $this->db->table($table)->orderBy('id')->get()->getResultArray(), $name . ' ' . $action);
            }
        }
    }

    public function testManagementCodesContactsAndAmountsRejectBadSymbolsOnServer(): void
    {
        helper(['url', 'form']);
        $forms = $this->managementFixtures();
        $cases = [
            'user' => [['username' => 'ana@cruz']],
            'branch' => [['branch_code' => 'BR@02'], ['contact_number' => '123'], ['address' => '<b>Office</b>']],
            'supplier' => [['contact_person' => 'Ana123'], ['contact_number' => '++639171234567'],
                ['email' => 'not-an-email'], ['address' => '<script>Office</script>']],
            'discount' => [['discount_value' => '1e2'], ['minimum_purchase' => '100.001'], ['max_discount_amount' => '₱10']],
        ];
        foreach ($cases as $name => $changes) {
            [$class, $table, $field, $post] = $forms[$name];
            $before = $this->db->table($table)->orderBy('id')->get()->getResultArray();
            foreach ($changes as $change) foreach (['store', 'update'] as $action) {
                $controller = $this->controller($class, array_replace($post, $change));
                $action === 'store' ? $controller->store() : $controller->update(1);
                $this->assertArrayHasKey(array_key_first($change), service('validation')->getErrors(), $name . ' ' . $action);
                $this->assertSame($before, $this->db->table($table)->orderBy('id')->get()->getResultArray());
            }
        }
    }

    public function testSupplierValidationErrorsAppearAfterTheRedirect(): void
    {
        helper(['url', 'form']);
        $forms = $this->managementFixtures();
        [$class, $table, $field, $post] = $forms['supplier'];
        $this->controller($class, array_replace($post, ['supplier_name' => '@@@']))->store();
        $errors = session()->getFlashdata('errors');
        $this->assertArrayHasKey('supplier_name', $errors);
        service('validation')->reset(); // The redirected GET is a new request.
        $html = $this->controller($class, [])->create();
        $this->assertTrue(str_contains($html, esc($errors['supplier_name'])));
        $this->assertSame(1, $this->db->table('suppliers')->countAllResults());
    }

    public function testProductUnitDropdownRejectsEmptyAndUnlistedChoicesOnCreateAndEdit(): void
    {
        $forms = $this->managementFixtures();
        $post = $forms['product'][3];
        $product = $this->row('products');
        $inventory = $this->row('branch_products');
        foreach (['', 'new made-up unit', '!!!'] as $unit) foreach (['store', 'update'] as $action) {
            $controller = $this->controller(\App\Controllers\Products::class, array_replace($post, ['unit' => $unit]));
            $action === 'store' ? $controller->store() : $controller->update(1);
            $this->assertArrayHasKey('unit', session()->getFlashdata('errors'));
            $this->assertSame(1, $this->db->table('products')->countAllResults());
            $this->assertSame($product, $this->row('products'));
            $this->assertSame($inventory, $this->row('branch_products'));
            $this->assertSame(0, $this->db->table('stock_logs')->countAllResults());
        }
    }

    public function testProductUnitDropdownPreservesExistingUnitsAndSavesListedChoices(): void
    {
        helper(['url', 'form']);
        $forms = $this->managementFixtures();
        $this->db->table('products')->where('id', 1)->update(['unit' => 'custom carton']);
        $this->db->table('products')->insert(['id' => 3, 'product_name' => 'Uppercase unit', 'sku' => 'CASE-03', 'unit' => 'Tablet', 'status' => 'active']);
        $options = \App\Libraries\ProductUnit::options($this->db);
        foreach (['tablet', 'capsule', 'bottle', 'piece', 'custom carton', 'Tablet'] as $unit) $this->assertArrayHasKey($unit, $options);
        session()->remove('_ci_old_input');
        $html = $this->controller(\App\Controllers\Products::class, [])->edit(1);
        $document = new DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($document);
        $this->assertSame('custom carton', $xpath->evaluate('string(//select[@name="unit"]/option[@selected]/@value)'));
        $this->assertSame(1, $xpath->query('//select[@name="unit" and @required]')->length);
        $this->assertSame(0, $xpath->query('//input[@name="unit"]')->length);
        $post = array_replace($forms['product'][3], ['unit' => 'custom carton', 'sku' => 'TEST']);
        $this->controller(\App\Controllers\Products::class, $post)->update(1);
        $this->assertSame('custom carton', $this->row('products')['unit']);
        $this->assertSame(10, (int) $this->row('branch_products')['stock']);
        $this->assertSame(0, $this->db->table('stock_logs')->countAllResults());
        $post = array_replace($post, ['unit' => 'capsule', 'sku' => 'UNIT-CAPSULE-02']);
        $this->controller(\App\Controllers\Products::class, $post)->store();
        $created = $this->db->table('products')->where('sku', 'UNIT-CAPSULE-02')->get()->getRowArray();
        $this->assertNotNull($created);
        $this->assertSame('capsule', $created['unit']);
        $this->assertSame(10, (int) $this->db->table('branch_products')->where('product_id', $created['id'])->get()->getRowArray()['stock']);
    }
}

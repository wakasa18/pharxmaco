<?php

namespace App\Controllers;

use App\Models\ProductModel;
use App\Models\StockLogModel;
use App\Models\BranchProductModel;
use App\Models\BranchModel;
use App\Models\ActivityLogModel;
use App\Models\SupplierModel;

class Products extends BaseController
{
    protected ProductModel $productModel;
    protected StockLogModel $stockLogModel;
    protected BranchProductModel $branchProductModel;
    protected BranchModel $branchModel;
    protected ActivityLogModel $activityLogModel;
    protected SupplierModel $supplierModel;
    protected $db;

    public function __construct()
    {
        $this->productModel = new ProductModel();
        $this->stockLogModel = new StockLogModel();
        $this->branchProductModel = new BranchProductModel();
        $this->branchModel = new BranchModel();
        $this->activityLogModel = new ActivityLogModel();
        $this->supplierModel = new SupplierModel();
        $this->db = \Config\Database::connect();
    }

    public function index()
    {
        $keyword = trim((string) ($this->request->getGet('keyword') ?? ''));
        $role = (string) session('role');
        $sessionBranchId = (int) session('branch_id');
        $branchId = trim((string) ($this->request->getGet('branch_id') ?? ''));
        $categoryId = trim((string) ($this->request->getGet('category_id') ?? ''));
        $status = trim((string) ($this->request->getGet('status') ?? ''));
        $stockInput = $this->request->getGet('stock_filter');
        $stockFilter = is_string($stockInput) ? trim($stockInput) : '';
        $manufacturerInput = $this->request->getGet('manufacturer');
        $manufacturer = is_string($manufacturerInput) ? trim($manufacturerInput) : '';
        if (mb_strlen($manufacturer) > 150) $manufacturer = '';
        $medicineType = \App\Libraries\MedicineType::filter($this->request->getGet('medicine_type'));

        if (!in_array($status, ['', 'active', 'inactive'], true)) {
            $status = '';
        }
        if ($categoryId !== '' && !ctype_digit($categoryId)) {
            $categoryId = '';
        }
        if (!in_array($stockFilter, ['', 'in', 'low', 'out', 'healthy'], true)) {
            $stockFilter = '';
        }

        if ($role === 'cashier') {
            if ($sessionBranchId <= 0) {
                return redirect()->back()->with('error', 'No branch is assigned to your account.');
            }
            $branchId = (string) $sessionBranchId;
            $assignedBranch = $this->branchModel->find($sessionBranchId);
            $branches = $assignedBranch ? [$assignedBranch] : [];
        } else {
            if ($branchId !== '' && !ctype_digit($branchId)) {
                $branchId = '';
            }
            $branches = $this->branchModel->orderBy('branch_name', 'ASC')->findAll();
        }

        $categories = $this->db->table('categories')
            ->where('deleted_at IS NULL', null, false)->where('is_deleted', 0)->where('is_permanently_deleted', 0)
            ->orderBy('category_name', 'ASC')
            ->get()
            ->getResultArray();

        // Keep options independent of the other filters and the table row cap,
        // while respecting the same retained-record and branch boundaries.
        $manufacturerBuilder = (new ProductModel())
            ->distinct()->select('TRIM(products.manufacturer) AS manufacturer')
            ->join('branch_products', 'branch_products.product_id = products.id')
            ->where('branch_products.deleted_at IS NULL', null, false)
            ->where('branch_products.is_deleted', 0)->where('branch_products.is_permanently_deleted', 0)
            ->where('products.manufacturer IS NOT NULL', null, false)
            ->where('TRIM(products.manufacturer) !=', '');
        if ($branchId !== '') $manufacturerBuilder->where('branch_products.branch_id', (int) $branchId);
        $manufacturers = array_column($manufacturerBuilder->orderBy('manufacturer', 'ASC')->findAll(), 'manufacturer');

        $builder = $this->productModel
            ->select('products.*, categories.category_name, branch_products.id AS branch_product_id,
                branch_products.branch_id, branches.branch_name, branch_products.stock AS branch_stock,
                branch_products.reorder_level AS branch_reorder_level, branch_products.price AS branch_price,
                branch_products.cost_price AS branch_cost_price, branch_products.expiration_date AS branch_expiration_date,
                branch_products.status AS branch_status, suppliers.supplier_name')
            ->select(\App\Libraries\StockBatches::batchCountSql('branch_products') . ' AS batch_count', false)
            ->join('categories', 'categories.id = products.category_id', 'left')
            ->join('branch_products', 'branch_products.product_id = products.id', 'left')
            ->join('branches', 'branches.id = branch_products.branch_id', 'left')
            ->join('suppliers', 'suppliers.id = products.supplier_id', 'left')
            ->where('branch_products.deleted_at IS NULL', null, false)->where('branch_products.is_deleted', 0)->where('branch_products.is_permanently_deleted', 0);

        if ($branchId !== '') {
            $builder->where('branch_products.branch_id', (int) $branchId);
        }
        \App\Libraries\MedicineType::applyFilter($builder, $medicineType);
        if ($categoryId !== '') {
            $builder->where('products.category_id', (int) $categoryId);
        }
        if ($status !== '') {
            $builder->where('branch_products.status', $status);
        }
        if ($manufacturer !== '') {
            $builder->where('TRIM(products.manufacturer)', $manufacturer);
        }
        if ($stockFilter === 'in') {
            $builder->where('branch_products.stock >', 0);
        } elseif ($stockFilter === 'low') {
            $builder->where('branch_products.stock <= branch_products.reorder_level', null, false);
        } elseif ($stockFilter === 'out') {
            $builder->where('branch_products.stock <=', 0);
        } elseif ($stockFilter === 'healthy') {
            $builder->where('branch_products.stock >', 0)
                ->where('branch_products.stock > branch_products.reorder_level', null, false);
        }
        if ($keyword !== '') {
            $builder->groupStart()
                ->like('products.product_name', $keyword)
                ->orLike('products.sku', $keyword)
                ->orLike('categories.category_name', $keyword)
                ->orLike('branches.branch_name', $keyword)
                ->orLike('suppliers.supplier_name', $keyword)
                ->groupEnd();
        }

        [$products, $tableTruncated] = $this->capTableRows(
            $builder->orderBy('products.product_name', 'ASC')->orderBy('branches.branch_name', 'ASC')->findAll(self::TABLE_ROW_LIMIT + 1)
        );

        $batchMap = [];
        $batchNumbers = [];
        $branchProductIds = array_values(array_unique(array_filter(array_map(static fn ($row) => (int) ($row['branch_product_id'] ?? 0), $products))));
        if ($branchProductIds && \App\Libraries\StockBatches::ready($this->db)) {
            $batchRows = $this->db->table('product_batches')->whereIn('branch_product_id', $branchProductIds)->where('quantity_remaining >', 0)
                ->orderBy('expiration_date IS NULL', 'ASC', false)->orderBy('expiration_date', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray();
            foreach ($batchRows as $batchRow) $batchMap[(int) $batchRow['branch_product_id']][] = $batchRow;
            $batchNumbers = \App\Libraries\StockBatches::numbers($this->db, array_column($batchRows, 'id'));
        }

        $data = [
            'products' => $products,
            'batchMap' => $batchMap,
            'batchNumbers' => $batchNumbers,
            'tableTruncated' => $tableTruncated,
            'keyword' => $keyword,
            'branches' => $branches,
            'branchId' => $branchId,
            'categories' => $categories,
            'categoryId' => $categoryId,
            'status' => $status,
            'stockFilter' => $stockFilter,
            'manufacturer' => $manufacturer,
            'manufacturers' => $manufacturers,
            'medicineType' => $medicineType,
        ];
        if ($this->request->isAJAX()) {
            return $this->response->setHeader('Cache-Control', 'no-store')->setJSON([
                'ok' => true,
                'rows_html' => view('products/_rows', $data, ['saveData' => false]),
                'truncated' => $tableTruncated,
                'manufacturers' => $manufacturers,
                'filters' => ['keyword' => $keyword, 'branch_id' => $branchId, 'category_id' => $categoryId,
                    'status' => $status, 'stock_filter' => $stockFilter, 'manufacturer' => $manufacturer,
                    'medicine_type' => $medicineType],
            ]);
        }
        return view('products/index', $data);
    }

    public function create()
    {
        $branchError = $this->cashierBranchError();
        if ($branchError !== null) {
            return redirect()->to(site_url('products'))->with('error', $branchError);
        }

        return view('products/create', $this->formOptions());
    }

    public function catalog()
    {
        if (session('role') !== 'cashier') {
            return $this->response->setStatusCode(403)->setBody('Only cashiers can use their assigned branch catalog.');
        }
        if ($error = $this->cashierBranchError()) {
            return redirect()->to(site_url('products'))->with('error', $error);
        }
        $branchId = (int) session('branch_id');
        $keyword = trim((string) ($this->request->getGet('keyword') ?? ''));
        $selectedId = (int) ($this->request->getGet('product_id') ?? 0);
        $medicineType = \App\Libraries\MedicineType::filter($this->request->getGet('medicine_type'));
        $builder = $this->productModel
            ->select('products.*, categories.category_name, suppliers.supplier_name')
            ->join('categories', 'categories.id = products.category_id', 'left')
            ->join('suppliers', 'suppliers.id = products.supplier_id', 'left')
            ->join('branch_products assigned', 'assigned.product_id = products.id AND assigned.branch_id = ' . $branchId, 'left')
            ->where('products.status', 'active')
            ->where('assigned.id IS NULL', null, false);
        \App\Libraries\MedicineType::applyFilter($builder, $medicineType);
        if ($selectedId > 0) {
            $product = $builder->where('products.id', $selectedId)->first();
            if (!$product) {
                return redirect()->to(site_url('products/catalog'))->with('error', 'This product is unavailable or already assigned to your branch. Ask an administrator to review inactive products.');
            }
            return view('products/catalog', [
                'branch' => $this->branchModel->find($branchId), 'selected' => $product,
                'products' => [], 'pager' => null, 'keyword' => $keyword,
                'medicineType' => $medicineType,
            ]);
        }
        if ($keyword !== '') {
            $builder->groupStart()->like('products.product_name', $keyword)
                ->orLike('products.sku', $keyword)->orLike('categories.category_name', $keyword)->groupEnd();
        }
        return view('products/catalog', [
            'branch' => $this->branchModel->find($branchId), 'selected' => null,
            'products' => $builder->orderBy('products.product_name')->orderBy('products.id')->paginate(12),
            'pager' => $this->productModel->pager, 'keyword' => $keyword,
            'medicineType' => $medicineType,
        ]);
    }

    public function addExisting()
    {
        if (session('role') !== 'cashier') {
            return $this->response->setStatusCode(403)->setBody('Only cashiers can add to their assigned branch.');
        }
        if ($error = $this->cashierBranchError()) {
            return redirect()->to(site_url('products'))->with('error', $error);
        }
        $input = [];
        foreach (['product_id', 'stock', 'reorder_level', 'price', 'cost_price', 'expiration_date', 'batch_no'] as $field) {
            $value = $this->request->getPost($field);
            $input[$field] = is_scalar($value) ? trim((string) $value) : '';
        }
        $back = site_url('products/catalog?product_id=' . (int) $input['product_id']);
        $quantityRule = 'required|integer|greater_than_equal_to[0]|less_than_equal_to[1000000]';
        $moneyRule = 'required|regex_match[/^\d{1,8}(\.\d{1,2})?$/]|greater_than_equal_to[0]|less_than_equal_to[99999999.99]';
        if (!$this->validateData($input, [
            'product_id' => 'required|is_natural_no_zero', 'stock' => $quantityRule,
            'reorder_level' => $quantityRule, 'price' => $moneyRule, 'cost_price' => $moneyRule,
            'expiration_date' => 'permit_empty|valid_date[Y-m-d]', 'batch_no' => 'permit_empty|max_length[60]',
        ])) {
            return redirect()->to($back)->withInput()->with('errors', $this->validator->getErrors());
        }
        if ((float) $input['price'] < (float) $input['cost_price']) {
            return redirect()->to($back)->withInput()->with('error', 'Selling price cannot be lower than cost.');
        }
        try {
            \App\Libraries\ExpiryDatePolicy::assertAllowed($input['expiration_date']);
            \App\Libraries\StockBatches::cleanBatchNo($input['batch_no']);
        } catch (\InvalidArgumentException $error) {
            return redirect()->to($back)->withInput()->with('error', $error->getMessage());
        }
        $branchId = (int) session('branch_id'); // Never accept a posted branch or shared product fields.
        $productId = (int) $input['product_id'];
        $now = date('Y-m-d H:i:s');
        $this->db->transBegin();
        try {
            $branch = $this->db->query('SELECT * FROM branches WHERE id = ? FOR UPDATE', [$branchId])->getRowArray();
            $product = $this->db->query('SELECT * FROM products WHERE id = ? FOR UPDATE', [$productId])->getRowArray();
            if (!$branch || $branch['status'] !== 'active' || !$product || $product['status'] !== 'active' || (!empty($product['deleted_at']) || !empty($product['is_deleted']))) {
                throw new \DomainException('The branch or product is no longer active. Please reload the catalog.');
            }
            // The locked master row serializes submissions for this product, including a double click.
            $assigned = $this->db->query('SELECT id FROM branch_products WHERE product_id = ? AND branch_id = ? FOR UPDATE', [$productId, $branchId])->getRowArray();
            if ($assigned) {
                throw new \DomainException('This product is already assigned to your branch. Use its stock controls, or ask an administrator if it is inactive.');
            }
            $this->db->table('branch_products')->insert([
                'product_id' => $productId, 'branch_id' => $branchId,
                'stock' => 0, 'reorder_level' => (int) $input['reorder_level'],
                'price' => $input['price'], 'cost_price' => $input['cost_price'],
                'expiration_date' => null, 'status' => 'active',
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $newBranchProductId = (int) $this->db->insertID();
            if ((int) $input['stock'] > 0) {
                // Opening stock is the branch product's first batch.
                (new \App\Libraries\StockBatches($this->db))->receive(
                    ['id' => $newBranchProductId], (int) $input['stock'], $input['expiration_date'] ?: null, $input['batch_no'] ?: null,
                    (int) session('user_id'), 'Initial stock from existing catalog product', $now,
                    ['source' => 'initial', 'supplier_id' => $product['supplier_id'] ?? null, 'manufacturer' => $product['manufacturer'] ?? null, 'cost_price' => $input['cost_price']]
                );
            }
            $this->syncMasterStock($productId);
            $this->logActivity('Added existing catalog product #' . $productId . ' to branch #' . $branchId);
            if ($this->db->transStatus() === false) {
                throw new \RuntimeException('Catalog assignment transaction failed.');
            }
            $this->db->transCommit();
        } catch (\Throwable $error) {
            $this->db->transRollback();
            if (!$error instanceof \DomainException) {
                log_message('error', 'Catalog assignment failed: {message}', ['message' => $error->getMessage()]);
            }
            return redirect()->to(site_url('products/catalog'))->with('error', $error instanceof \DomainException
                ? $error->getMessage() : 'The product could not be added. No stock was saved. Please try again.');
        }
        return redirect()->to(site_url('products'))->with('success', $product['product_name'] . ' added to your branch.');
    }

    public function store()
    {
        $branchError = $this->cashierBranchError();
        if ($branchError !== null) {
            return redirect()->to(site_url('products'))->with('error', $branchError);
        }

        $input = $this->normalizedInput();
        $rules = $this->productRules(true);

        if (!$this->validateData($input, $rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        try {
            \App\Libraries\ExpiryDatePolicy::assertAllowed($input['expiration_date']);
        } catch (\InvalidArgumentException $error) {
            return redirect()->back()->withInput()->with('errors', ['expiration_date' => $error->getMessage()]);
        }
        try {
            \App\Libraries\StockBatches::cleanBatchNo($input['batch_no']);
        } catch (\InvalidArgumentException $error) {
            return redirect()->back()->withInput()->with('errors', ['batch_no' => $error->getMessage()]);
        }
        if ($error = $this->referenceError($input)) {
            return redirect()->back()->withInput()->with('error', $error);
        }
        if ((float) $input['price'] < (float) $input['cost_price']) {
            return redirect()->back()->withInput()->with('errors', ['price' => 'Selling price cannot be lower than the product cost.']);
        }

        $branchId = (int) $input['branch_id'];
        $branch = $this->branchModel->find($branchId);
        $existingProduct = $this->findProductBySku($input['sku']);

        if ($existingProduct !== null) {
            if (!empty($existingProduct['is_permanently_deleted'])) {
                return redirect()->back()->withInput()->with('errors', ['sku'=>'This SKU is retained for a permanently removed product. Ask an administrator to review the catalog.']);
            }
            if ((!empty($existingProduct['deleted_at']) || !empty($existingProduct['is_deleted']))) {
                return redirect()->back()->withInput()->with('errors', [
                    'sku' => 'This SKU belongs to a product in Trash. Ask an administrator to restore it first.',
                ]);
            }
            if (($existingProduct['status'] ?? 'inactive') !== 'active') {
                return redirect()->back()->withInput()->with('errors', [
                    'sku' => 'This SKU belongs to an inactive product. Ask an administrator to reactivate or review it first.',
                ]);
            }

            $existingBranchProduct = $this->branchProductModel->withDeleted()
                ->where('branch_id', $branchId)
                ->where('product_id', (int) $existingProduct['id'])
                ->first();

            if ($existingBranchProduct) {
                if (!empty($existingBranchProduct['is_permanently_deleted'])) {
                    return redirect()->back()->withInput()->with('errors', ['sku'=>'This branch product was permanently removed and is retained in the database. It cannot be restored here.']);
                }
                return redirect()->back()->withInput()->with('errors', [
                    'sku' => (!empty($existingBranchProduct['deleted_at']) || !empty($existingBranchProduct['is_deleted']))
                        ? 'This product is in your branch Trash. Ask an administrator to restore it.'
                        : 'This product is already available in ' . ($branch['branch_name'] ?? 'your branch') . '.',
                ]);
            }

            if (!$this->matchesExistingProduct($existingProduct, $input)) {
                return redirect()->back()->withInput()->with('errors', [
                    'sku' => 'This SKU already belongs to “' . $existingProduct['product_name'] . '”. Use the same product name, category, and unit, or ask an administrator to review it.',
                ]);
            }

            $this->db->transStart();
            $branchProductId = $this->branchProductModel->insert([
                'branch_id' => $branchId,
                'product_id' => (int) $existingProduct['id'],
                'stock' => 0,
                'reorder_level' => (int) $input['reorder_level'],
                'price' => (float) $input['price'],
                'cost_price' => (float) $input['cost_price'],
                'expiration_date' => null,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ], true);

            if ($branchProductId && (int) $input['stock'] > 0) {
                try {
                    // Opening stock is the branch product's first batch.
                    (new \App\Libraries\StockBatches($this->db))->receive(
                        ['id' => (int) $branchProductId], (int) $input['stock'], $input['expiration_date'] ?: null, $input['batch_no'] ?: null,
                        (int) session('user_id'), 'Initial stock when the existing product was added to this branch', date('Y-m-d H:i:s'),
                        ['source' => 'initial', 'supplier_id' => $existingProduct['supplier_id'] ?? null, 'manufacturer' => $existingProduct['manufacturer'] ?? null, 'cost_price' => (float) $input['cost_price']]
                    );
                } catch (\Throwable $error) {
                    $this->db->transRollback();
                    log_message('error', 'Initial batch failed: {message}', ['message' => $error->getMessage()]);
                    return redirect()->back()->withInput()->with('error', 'The product could not be added to the branch. Please try again.');
                }
            }

            $this->syncMasterStock((int) $existingProduct['id']);
            $this->db->transComplete();

            if (!$branchProductId || $this->db->transStatus() === false) {
                return redirect()->back()->withInput()->with('error', 'The product could not be added to the branch. Please try again.');
            }

            $this->logActivity(
                'Added existing product to branch: ' . $existingProduct['product_name']
                . ' → ' . ($branch['branch_name'] ?? ('Branch #' . $branchId))
            );

            return redirect()->to(site_url('products?branch_id=' . $branchId))
                ->with('success', 'Existing product added to ' . ($branch['branch_name'] ?? 'the branch') . ' successfully.');
        }

        $this->db->transStart();

        $productId = $this->productModel->insert([
            'product_name' => $input['product_name'],
            'senior_pwd_eligible' => $input['senior_pwd_eligible'] === '' ? null : (int) $input['senior_pwd_eligible'],
            'medicine_type' => $input['medicine_type'] ?: null,
            'generic_name' => $input['generic_name'] ?: null,
            'strength' => $input['strength'] ?: null,
            'tax_treatment' => $input['tax_treatment'] ?: null,
            'category_id' => (int) $input['category_id'],
            'sku' => $input['sku'],
            'unit' => $input['unit'],
            'cost_price' => (float) $input['cost_price'],
            'price' => (float) $input['price'],
            'stock' => (int) $input['stock'],
            'reorder_level' => (int) $input['reorder_level'],
            'manufacturer' => $input['manufacturer'] ?: null,
            'supplier_id' => $input['supplier_id'] !== '' ? (int) $input['supplier_id'] : null,
            'status' => 'active',
        ], true);

        if ($productId) {
            $newBranchProductId = $this->branchProductModel->insert([
                'branch_id' => $branchId,
                'product_id' => (int) $productId,
                'stock' => 0,
                'reorder_level' => (int) $input['reorder_level'],
                'price' => (float) $input['price'],
                'cost_price' => (float) $input['cost_price'],
                'expiration_date' => null,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ], true);

            if ($newBranchProductId && (int) $input['stock'] > 0) {
                try {
                    // Opening stock is the branch product's first batch.
                    (new \App\Libraries\StockBatches($this->db))->receive(
                        ['id' => (int) $newBranchProductId], (int) $input['stock'], $input['expiration_date'] ?: null, $input['batch_no'] ?: null,
                        (int) session('user_id'), 'Initial stock when the product was created', date('Y-m-d H:i:s'),
                        ['source' => 'initial', 'supplier_id' => $input['supplier_id'] !== '' ? (int) $input['supplier_id'] : null,
                         'manufacturer' => $input['manufacturer'] ?: null, 'cost_price' => (float) $input['cost_price']]
                    );
                } catch (\Throwable $error) {
                    $this->db->transRollback();
                    log_message('error', 'Initial batch failed: {message}', ['message' => $error->getMessage()]);
                    return redirect()->back()->withInput()->with('error', 'The product could not be saved. Please try again.');
                }
            }
        }

        $this->db->transComplete();
        if (!$productId || $this->db->transStatus() === false) {
            return redirect()->back()->withInput()->with('error', 'The product could not be saved. Please try again.');
        }

        $this->logActivity(
            'Created product: ' . $input['product_name']
            . ' for ' . ($branch['branch_name'] ?? ('Branch #' . $branchId))
        );
        return redirect()->to(site_url('products?branch_id=' . $branchId))->with('success', 'Product added successfully.');
    }

    public function edit($id)
    {
        $id = (int) $id;
        $product = $this->productModel->find($id);
        if (!$product) {
            return redirect()->to(site_url('products'))->with('error', 'Product not found.');
        }

        $branchId = (int) ($this->request->getGet('branch_id') ?? 0);
        $branchProduct = null;

        if ($branchId > 0) {
            $branchProduct = $this->branchProductModel->where('branch_id', $branchId)->where('product_id', $id)->first();
            if (!$branchProduct) {
                return redirect()->to(site_url('products'))->with('error', 'This branch product is unavailable or in Trash. Restore it before editing.');
            }
        }
        if (!$branchProduct) {
            $branchProduct = $this->branchProductModel->where('product_id', $id)->orderBy('branch_id', 'ASC')->first();
            $branchId = (int) ($branchProduct['branch_id'] ?? 0);
        }
        if ($branchId <= 0) {
            $firstBranch = $this->branchModel->where('status', 'active')->orderBy('branch_name', 'ASC')->first();
            $branchId = (int) ($firstBranch['id'] ?? 0);
        }

        return view('products/edit', array_merge($this->formOptions(), [
            'product' => $product,
            'branchProduct' => $branchProduct,
            'branchId' => $branchId,
        ]));
    }

    public function update($id)
    {
        $id = (int) $id;
        $product = $this->productModel->find($id);
        if (!$product) {
            return redirect()->to(site_url('products'))->with('error', 'Product not found.');
        }

        $input = $this->normalizedInput();
        foreach (['generic_name', 'strength', 'tax_treatment', 'medicine_type', 'senior_pwd_eligible'] as $field) {
            if ($this->request->getPost($field) === null) $input[$field] = $product[$field] ?? '';
        }
        // Stock and expiry of an existing branch product live in its batches and are
        // changed through Add / Remove stock, never from this form.
        $existingBranchProduct = $this->branchProductModel->where('branch_id', (int) $input['branch_id'])->where('product_id', $id)->first();
        if ($existingBranchProduct) {
            $input['stock'] = (string) (int) $existingBranchProduct['stock'];
            $input['expiration_date'] = (string) ($existingBranchProduct['expiration_date'] ?? '');
        }
        if (!$this->validateData($input, $this->productRules(true))) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        if ($error = $this->referenceError($input)) {
            return redirect()->back()->withInput()->with('error', $error);
        }
        if ($this->skuExists($input['sku'], $id)) {
            return redirect()->back()->withInput()->with('errors', ['sku' => 'This SKU is already used by another product.']);
        }
        if ((float) $input['price'] < (float) $input['cost_price']) {
            return redirect()->back()->withInput()->with('errors', ['price' => 'Selling price cannot be lower than the product cost.']);
        }

        $branchId = (int) $input['branch_id'];
        $branchProduct = $this->branchProductModel->where('branch_id', $branchId)->where('product_id', $id)->first();
        $oldStock = (int) ($branchProduct['stock'] ?? 0);
        $expectedStock = filter_var($this->request->getPost('old_stock'), FILTER_VALIDATE_INT);
        $newStock = (int) $input['stock'];
        $stockReason = trim((string) ($this->request->getPost('stock_reason') ?? ''));

        if ($oldStock !== $newStock && mb_strlen($stockReason) < 3) {
            return redirect()->back()->withInput()->with('errors', [
                'stock_reason' => 'Enter a short reason for changing the stock quantity.',
            ]);
        }

        $this->db->transStart();

        // Lock this branch inventory row before applying an absolute stock
        // quantity so a checkout or another stock edit cannot be overwritten.
        $branchProduct = $this->db->query(
            'SELECT * FROM branch_products WHERE branch_id = ? AND product_id = ? FOR UPDATE',
            [$branchId, $id]
        )->getRowArray();
        $oldStock = (int) ($branchProduct['stock'] ?? 0);
        if ($branchProduct && (!empty($branchProduct['deleted_at']) || !empty($branchProduct['is_deleted']))) {
            $this->db->transRollback();
            return redirect()->to(site_url('products'))->with('error', 'This branch product is in Trash. Restore it before editing.');
        }
        if ($oldStock !== $newStock && mb_strlen($stockReason) < 3) {
            $this->db->transRollback();
            return redirect()->back()->withInput()->with('errors', [
                'stock_reason' => 'Stock changed while this page was open. Enter a reason before saving the new quantity.',
            ]);
        }

        if (!$branchProduct && ($expectedStock === false || $expectedStock !== $oldStock)) {
            $this->db->transRollback();
            return redirect()->to(site_url('products/edit/' . $id . '?branch_id=' . $branchId))
                ->with('error', 'Stock changed while this page was open. Review the current count before saving again. No changes were saved.');
        }

        try {
            // Use the locked stored date, never a posted original date. Historical
            // values may stay unchanged when other product fields are edited.
            \App\Libraries\ExpiryDatePolicy::assertAllowed($input['expiration_date'], $branchProduct['expiration_date'] ?? null);
        } catch (\InvalidArgumentException $error) {
            $this->db->transRollback();
            return redirect()->back()->withInput()->with('errors', ['expiration_date' => $error->getMessage()]);
        }

        $this->productModel->update($id, [
            'product_name' => $input['product_name'],
            'senior_pwd_eligible' => $input['senior_pwd_eligible'] === '' ? null : (int) $input['senior_pwd_eligible'],
            'medicine_type' => $input['medicine_type'] ?: null,
            'generic_name' => $input['generic_name'] ?: null,
            'strength' => $input['strength'] ?: null,
            'tax_treatment' => $input['tax_treatment'] ?: null,
            'category_id' => (int) $input['category_id'],
            'sku' => $input['sku'],
            'unit' => $input['unit'],
            'cost_price' => (float) $input['cost_price'],
            'price' => (float) $input['price'],
            'reorder_level' => (int) $input['reorder_level'],
            'manufacturer' => $input['manufacturer'] ?: null,
            'supplier_id' => $input['supplier_id'] !== '' ? (int) $input['supplier_id'] : null,
        ]);

        $payload = [
            'reorder_level' => (int) $input['reorder_level'],
            'price' => (float) $input['price'],
            'cost_price' => (float) $input['cost_price'],
            'status' => $input['status'],
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($branchProduct) {
            $this->branchProductModel->update((int) $branchProduct['id'], $payload);
        } else {
            $newBranchProductId = $this->branchProductModel->insert(array_merge($payload, [
                'branch_id' => $branchId,
                'product_id' => $id,
                'stock' => 0,
                'expiration_date' => null,
                'created_at' => date('Y-m-d H:i:s'),
            ]), true);
            if ($newBranchProductId && $newStock > 0) {
                try {
                    (new \App\Libraries\StockBatches($this->db))->receive(
                        ['id' => (int) $newBranchProductId], $newStock, $input['expiration_date'] ?: null, $input['batch_no'] ?: null,
                        (int) session('user_id'), $stockReason !== '' ? $stockReason : 'Initial stock', date('Y-m-d H:i:s'),
                        ['source' => 'initial', 'cost_price' => (float) $input['cost_price']]
                    );
                } catch (\Throwable $error) {
                    $this->db->transRollback();
                    return redirect()->back()->withInput()->with('error', $error->getMessage());
                }
            }
        }

        $this->syncMasterStock($id);
        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            return redirect()->back()->withInput()->with('error', 'The product could not be updated.');
        }

        $this->logActivity('Updated product: ' . $input['product_name'] . ' (ID: ' . $id . ')');
        return redirect()->to(site_url('products?branch_id=' . $branchId))->with('success', 'Product updated successfully.');
    }

    public function delete($id)
    {
        return $this->changeBranchTrash((int)$id, true);
    }

    private function changeBranchTrash(int $id, bool $trash)
    {
        if (session('role') !== 'admin') return $this->response->setStatusCode(403)->setBody('Administrator access required.');
        $postedBranch = $this->request->getPost('branch_id');
        $branchId = is_scalar($postedBranch) ? filter_var($postedBranch, FILTER_VALIDATE_INT) : false;
        $destination = site_url($trash ? 'products' : 'products/trash');
        try {
            if (!$branchId || $branchId < 1) throw new \InvalidArgumentException('Select a specific branch product. No product was changed.');
            $changed = (new \App\Libraries\BranchProductTrash($this->db))->change($id, $branchId, $trash, (string)session('role'), (int)session('user_id'));
        } catch (\DomainException|\InvalidArgumentException $error) {
            return redirect()->to($destination)->with('error', $error->getMessage());
        } catch (\Throwable $error) {
            log_message('error', 'Branch trash action failed: {message}', ['message'=>$error->getMessage()]);
            return redirect()->to($destination)->with('error', 'The branch product could not be changed. Check that the branch-trash database upgrade is installed.');
        }
        return redirect()->to($destination)->with('success', $changed
            ? ($trash ? 'Product moved to Trash in the selected branch only. Stock is retained; use Dispose / Return to supplier for physical stock removal.' : 'Product restored in the selected branch only. Its previous status, prices, stock and expiry are retained.')
            : ($trash ? 'This branch product is already in Trash.' : 'This branch product is already restored.'));
    }

    public function trash()
    {
        if (session('role') !== 'admin') return $this->response->setStatusCode(403)->setBody('Administrator access required.');
        $keyword = trim((string) ($this->request->getGet('keyword') ?? ''));
        $branchId = (int) ($this->request->getGet('branch_id') ?? 0);
        $builder = $this->branchProductModel->onlyDeleted()
            ->select('products.*, branch_products.deleted_at AS deleted_at, branch_products.is_deleted AS is_deleted, branch_products.branch_id,
                branch_products.stock AS branch_stock, branches.branch_name, categories.category_name, suppliers.supplier_name')
            ->join('products', 'products.id = branch_products.product_id')
            ->join('branches', 'branches.id = branch_products.branch_id')
            ->join('categories', 'categories.id = products.category_id', 'left')
            ->join('suppliers', 'suppliers.id = products.supplier_id', 'left')
            ->where('products.deleted_at IS NULL', null, false)->where('products.is_deleted', 0)->where('products.is_permanently_deleted', 0)
            ->orderBy('branch_products.deleted_at', 'DESC');

        if ($branchId > 0) $builder->where('branch_products.branch_id', $branchId);

        if ($keyword !== '') {
            $builder->groupStart()->like('products.product_name', $keyword)->orLike('products.sku', $keyword)->groupEnd();
        }

        $legacy = $this->productModel->onlyDeleted()->orderBy('deleted_at', 'DESC')->findAll();
        return view('products/trash', [
            'products' => $builder->findAll(),
            'legacyProducts' => $legacy,
            'keyword' => $keyword,
            'branchId' => $branchId,
            'branches' => $this->branchModel->orderBy('branch_name')->findAll(),
        ]);
    }

    public function restore(int $id)
    {
        if (session('role') !== 'admin') return $this->response->setStatusCode(403)->setBody('Administrator access required.');
        if ($this->request->getPost('branch_id') !== null) return $this->changeBranchTrash($id, false);
        if ($this->request->getPost('legacy_restore') !== '1') {
            return redirect()->to(site_url('products/trash'))->with('error', 'Select the branch product to restore. No product was changed.');
        }
        $product = $this->productModel->onlyDeleted()->find($id);
        if (!$product) {
            return redirect()->to(site_url('products/trash'))->with('error', 'Product not found in trash.');
        }

        $restored = $this->db->table('products')->where('id', $id)->where('is_permanently_deleted', 0)->update([
            'deleted_at' => null,
            'is_deleted' => 0,
            'status' => 'active',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        if (!$restored || $this->db->affectedRows() === 0) {
            return redirect()->to(site_url('products/trash'))->with('error', 'The product could not be restored.');
        }

        $this->logActivity('Restored product: ' . $product['product_name'] . ' (ID: ' . $id . ')');
        return redirect()->to(site_url('products/trash'))->with('success', 'Product restored successfully. Review its branch status before selling it.');
    }

    public function forceDelete(int $id)
    {
        if (session('role') !== 'admin') {
            return $this->response->setStatusCode(403)->setBody('Administrator access required.');
        }
        try {
            $postedBranch = $this->request->getPost('branch_id');
            if ($postedBranch !== null) {
                $branchId = is_scalar($postedBranch) ? filter_var($postedBranch, FILTER_VALIDATE_INT) : false;
                if (!$branchId || $branchId < 1) throw new \InvalidArgumentException('Select a specific branch product. No product was changed.');
                $changed = (new \App\Libraries\BranchProductTrash($this->db))->permanentlyDelete($id, $branchId, (string)session('role'), (int)session('user_id'));
            } elseif ($this->request->getPost('legacy_delete') === '1') {
                $changed = (new \App\Libraries\RetainedRecordDeletion($this->db))->hide('products', $id, (string)session('role'), (int)session('user_id'));
            } else {
                throw new \InvalidArgumentException('Select a branch product or the explicit older shared-product action. No product was changed.');
            }
        } catch (\DomainException|\InvalidArgumentException $error) {
            return redirect()->to(site_url('products/trash'))->with('error', $error->getMessage());
        } catch (\Throwable $error) {
            log_message('error', 'Permanent product hiding failed: {message}', ['message'=>$error->getMessage()]);
            return redirect()->to(site_url('products/trash'))->with('error', 'The product could not be removed. No changes were saved.');
        }
        return redirect()->to(site_url('products/trash'))->with('success', $changed ? 'Product removed from the system and Trash. The selected database record, stock and history are retained.' : 'This product is already permanently hidden.');
    }

    public function updateStock()
    {
        $role = (string) session('role');
        if (!in_array($role, ['admin', 'cashier'], true)) {
            return redirect()->to(site_url('products'))->with('error', 'Unauthorized access.');
        }
        if ($this->request->getPost('stock_mode') === 'receive') {
            return redirect()->back()->with('error', 'Legacy receiving is no longer supported. Refresh Products and use Add stock with the current balance.');
        }

        $rules = [
            'product_id' => 'required|integer|greater_than[0]',
            'branch_id' => 'required|integer|greater_than[0]',
            'stock' => 'required|integer|greater_than_equal_to[0]|less_than_equal_to[2147483647]',
            'remarks' => 'required|min_length[3]|max_length[255]',
            'old_stock' => 'required|integer|greater_than_equal_to[0]',
            'stock_mode' => 'required|in_list[add,remove]',
            'expiration_date' => 'permit_empty|valid_date[Y-m-d]',
            'batch_no' => 'permit_empty|max_length[60]',
            'batch_id' => 'permit_empty|integer|greater_than[0]',
            'base_batch_id' => 'permit_empty|integer|greater_than[0]',
        ];
        if (!$this->validateData($this->request->getPost(), $rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        $productId = (int) $this->request->getPost('product_id');
        $postedBranchId = (int) $this->request->getPost('branch_id');
        $branchId = $role === 'cashier' ? (int) session('branch_id') : $postedBranchId;
        $newStock = (int) $this->request->getPost('stock');
        $remarks = trim((string) $this->request->getPost('remarks'));

        if ($branchId <= 0 || ($role === 'cashier' && $branchId !== $postedBranchId)) {
            return redirect()->back()->with('error', 'You can only update stock for your assigned branch.');
        }
        if (!$this->branchModel->where('id', $branchId)->where('status', 'active')->first()) {
            return redirect()->back()->with('error', 'The selected branch is not active.');
        }

        $product = $this->productModel->find($productId);
        $branchProduct = $this->branchProductModel->where('branch_id', $branchId)->where('product_id', $productId)->first();
        if (!$product || !$branchProduct) {
            return redirect()->back()->with('error', 'The branch inventory record was not found.');
        }

        $stockMode = (string) $this->request->getPost('stock_mode');
        $batchNo = trim((string) $this->request->getPost('batch_no'));
        $batchExpiry = trim((string) $this->request->getPost('expiration_date'));
        $batchId = (int) $this->request->getPost('batch_id');
        // The form pre-fills the latest batch's lot and expiry. If they are left as they were,
        // the stock is added to that batch; if either was changed, a new batch is created.
        $baseBatchId = (int) $this->request->getPost('base_batch_id');
        if ($stockMode === 'add') {
            try {
                // Each delivery is its own batch with its own expiry date.
                \App\Libraries\ExpiryDatePolicy::assertAllowed($batchExpiry);
                \App\Libraries\StockBatches::cleanBatchNo($batchNo);
            } catch (\InvalidArgumentException $error) {
                return redirect()->back()->with('error', $error->getMessage());
            }
        }
        if (in_array($stockMode, ['add', 'remove'], true)) {
            if ($newStock <= 0 || $newStock > 1000000 || mb_strlen($remarks) < 3 || mb_strlen($remarks) > 200) {
                return redirect()->back()->with('error', 'Enter 1 to 1,000,000 whole units and a reason of 3 to 200 characters.');
            }
            $tokenKey = 'stock_adjustment_' . $productId . '_' . $branchId;
            $token = $this->request->getPost('stock_token');
            $expected = session($tokenKey);
            if (!is_string($token) || !is_string($expected) || !hash_equals($expected, $token)) {
                return redirect()->back()->with('error', 'This stock action was already submitted or expired. Refresh the product list before trying again.');
            }
            session()->remove($tokenKey);
        }

        try {
            if (!$this->db->transBegin()) {
                throw new \RuntimeException('Could not start stock adjustment.');
            }
            $branchProduct = $this->db->query(
                'SELECT * FROM branch_products WHERE id = ? AND branch_id = ? FOR UPDATE',
                [(int) $branchProduct['id'], $branchId]
            )->getRowArray();
            if (!$branchProduct || (!empty($branchProduct['deleted_at']) || !empty($branchProduct['is_deleted']) || !empty($branchProduct['is_permanently_deleted']))) {
                $this->db->transRollback();
                return redirect()->back()->with('error', 'The branch inventory record is no longer available.');
            }

            $oldStock = (int) $branchProduct['stock'];
            if (in_array($stockMode, ['add', 'remove'], true) && (int) $this->request->getPost('old_stock') !== $oldStock) {
                $this->db->transRollback();
                return redirect()->back()->with('error', 'Stock changed while this page was open. Review the latest balance before adding or removing units.');
            }
            $batches = new \App\Libraries\StockBatches($this->db);
            $quantity = $newStock;
            $now = date('Y-m-d H:i:s');
            try {
                if ($stockMode === 'remove') {
                    $batches->remove($branchProduct, $quantity, (int) session('user_id'), 'Manual stock adjustment: ' . $remarks, $now, $batchId > 0 ? $batchId : null);
                } else {
                    $batches->receive($branchProduct, $quantity, $batchExpiry !== '' ? $batchExpiry : null, $batchNo !== '' ? $batchNo : null,
                        (int) session('user_id'), 'Manual stock adjustment: ' . $remarks, $now,
                        ['supplier_id' => $product['supplier_id'] ?? null, 'manufacturer' => $product['manufacturer'] ?? null,
                         'into_batch_id' => $baseBatchId > 0 ? $baseBatchId : null]);
                }
            } catch (\InvalidArgumentException $error) {
                $this->db->transRollback();
                return redirect()->back()->with('error', $error->getMessage());
            }
            $newStock = (int) ($this->db->query('SELECT stock FROM branch_products WHERE id = ?', [(int) $branchProduct['id']])->getRowArray()['stock'] ?? 0);
            if (!$this->syncMasterStock($productId)) {
                throw new \RuntimeException('Could not save stock movement.');
            }
            if (!$this->logActivity('Adjusted stock for ' . $product['product_name'] . ': ' . $oldStock . ' → ' . $newStock . '. Reason: ' . $remarks)) {
                throw new \RuntimeException('Could not save stock audit.');
            }
            if ($this->db->transStatus() === false || !$this->db->transCommit()) {
                throw new \RuntimeException('Could not commit stock adjustment.');
            }
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Stock adjustment failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->back()->with('error', 'The stock quantity could not be updated. Refresh the product list and try again.');
        }

        return redirect()->back()->with('success', 'Stock updated successfully.');
    }

    private function formOptions(): array
    {
        $isCashier = (string) session('role') === 'cashier';
        $assignedBranch = null;

        if ($isCashier) {
            $assignedBranch = $this->branchModel
                ->where('id', (int) session('branch_id'))
                ->where('status', 'active')
                ->first();
            $branches = $assignedBranch ? [$assignedBranch] : [];
        } else {
            $branches = $this->branchModel->where('status', 'active')->orderBy('branch_name', 'ASC')->findAll();
        }

        return [
            'categories' => $this->db->table('categories')->where('deleted_at IS NULL', null, false)->where('is_deleted', 0)->where('is_permanently_deleted', 0)->orderBy('category_name', 'ASC')->get()->getResultArray(),
            'unitOptions' => \App\Libraries\ProductUnit::options($this->db),
            'branches' => $branches,
            'assignedBranch' => $assignedBranch,
            'isCashier' => $isCashier,
            'suppliers' => $this->supplierModel->where('status', 'active')->orderBy('supplier_name', 'ASC')->findAll(),
            'validation' => \Config\Services::validation(),
        ];
    }

    private function normalizedInput(): array
    {
        $sku = trim((string) $this->request->getPost('sku'));
        return [
            'product_name' => trim((string) $this->request->getPost('product_name')),
            'category_id' => trim((string) $this->request->getPost('category_id')),
            'sku' => $sku !== '' ? $sku : null,
            'unit' => trim((string) $this->request->getPost('unit')),
            'cost_price' => trim((string) $this->request->getPost('cost_price')),
            'price' => trim((string) $this->request->getPost('price')),
            'stock' => trim((string) $this->request->getPost('stock')),
            'reorder_level' => trim((string) $this->request->getPost('reorder_level')),
            'expiration_date' => trim((string) $this->request->getPost('expiration_date')),
            'batch_no' => trim((string) $this->request->getPost('batch_no')),
            'branch_id' => (string) ((string) session('role') === 'cashier'
                ? (int) session('branch_id')
                : trim((string) $this->request->getPost('branch_id'))),
            'manufacturer' => trim((string) $this->request->getPost('manufacturer')),
            'generic_name' => trim((string) ($this->request->getPost('generic_name') ?? '')),
            'medicine_type' => trim((string) ($this->request->getPost('medicine_type') ?? '')),
            'senior_pwd_eligible' => trim((string) ($this->request->getPost('senior_pwd_eligible') ?? '')),
            'strength' => trim((string) ($this->request->getPost('strength') ?? '')),
            'tax_treatment' => trim((string) ($this->request->getPost('tax_treatment') ?? 'taxable')),
            'supplier_id' => trim((string) $this->request->getPost('supplier_id')),
            'status' => trim((string) ($this->request->getPost('status') ?? 'active')),
        ];
    }

    private function productRules(bool $branchRequired): array
    {
        return [
            'product_name' => 'required|min_length[2]|max_length[150]|management_text[catalog]',
            'category_id' => 'required|integer|greater_than[0]',
            'sku' => 'required|max_length[50]|management_text[code]',
            'unit' => 'required|max_length[30]|management_text[catalog]|product_unit',
            'cost_price' => 'required|management_text[money]|greater_than_equal_to[0]',
            'price' => 'required|management_text[money]|greater_than_equal_to[0]',
            'stock' => 'required|integer|greater_than_equal_to[0]|management_text[quantity]',
            'reorder_level' => 'required|integer|greater_than_equal_to[0]|management_text[quantity]',
            'expiration_date' => 'permit_empty|valid_date[Y-m-d]',
            'branch_id' => ($branchRequired ? 'required|' : 'permit_empty|') . 'integer|greater_than[0]',
            'manufacturer' => 'permit_empty|max_length[150]|management_text[catalog]',
            'generic_name' => 'permit_empty|max_length[150]|management_text[catalog]',
            'medicine_type' => 'permit_empty|in_list[branded,generic,non_medicine]',
            'senior_pwd_eligible' => 'permit_empty|in_list[0,1]',
            'strength' => 'permit_empty|max_length[80]|management_text[catalog]',
            'tax_treatment' => 'permit_empty|in_list[taxable,vat_exempt,zero_rated]',
            'supplier_id' => 'permit_empty|integer|greater_than[0]',
            'status' => 'required|in_list[active,inactive]',
        ];
    }

    private function referenceError(array $input): ?string
    {
        $category = $this->db->table('categories')->where('id', (int) $input['category_id'])->where('deleted_at IS NULL', null, false)->where('is_deleted', 0)->where('is_permanently_deleted', 0)->get()->getRowArray();
        if (!$category) {
            return 'Please select a valid category.';
        }
        if (!$this->branchModel->where('id', (int) $input['branch_id'])->where('status', 'active')->first()) {
            return 'Please select a valid active branch.';
        }
        if ($input['supplier_id'] !== '' && !$this->supplierModel->where('id', (int) $input['supplier_id'])->where('status', 'active')->first()) {
            return 'Please select a valid active supplier.';
        }
        return null;
    }

    private function skuExists(?string $sku, ?int $ignoreId = null): bool
    {
        if ($sku === null || $sku === '') {
            return false;
        }
        $builder = $this->productModel->withDeleted()->where('sku', $sku);
        if ($ignoreId !== null) {
            $builder->where('id !=', $ignoreId);
        }
        return $builder->first() !== null;
    }

    private function cashierBranchError(): ?string
    {
        if ((string) session('role') !== 'cashier') {
            return null;
        }

        $branchId = (int) session('branch_id');
        if ($branchId <= 0) {
            return 'No branch is assigned to your cashier account. Ask an administrator to assign one before adding products.';
        }

        $branch = $this->branchModel->find($branchId);
        if (!$branch || ($branch['status'] ?? 'inactive') !== 'active') {
            return 'Your assigned branch is inactive or unavailable. Ask an administrator to review your account.';
        }

        return null;
    }

    private function findProductBySku(?string $sku): ?array
    {
        if ($sku === null || trim($sku) === '') {
            return null;
        }

        return $this->productModel->withDeleted()->where('sku', trim($sku))->first();
    }

    private function matchesExistingProduct(array $product, array $input): bool
    {
        $normalize = static fn (string $value): string => mb_strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? $value));

        return $normalize((string) ($product['product_name'] ?? '')) === $normalize((string) $input['product_name'])
            && (int) ($product['category_id'] ?? 0) === (int) $input['category_id']
            && $normalize((string) ($product['unit'] ?? '')) === $normalize((string) $input['unit']);
    }

    private function syncMasterStock(int $productId): bool
    {
        $row = $this->db->table('branch_products')->selectSum('stock', 'total_stock')->where('product_id', $productId)->get()->getRowArray();
        return $this->db->table('products')->where('id', $productId)->update([
            'stock' => (int) ($row['total_stock'] ?? 0),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function logActivity(string $activity): bool
    {
        return $this->activityLogModel->insert([
            'user_id' => session('user_id'),
            'activity' => $activity,
            'log_time' => date('Y-m-d H:i:s'),
        ]) !== false;
    }
}

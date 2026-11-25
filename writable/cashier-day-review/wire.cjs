const fs = require('node:fs');
let file = 'app/Controllers/Cashier/SalesController.php';
let source = fs.readFileSync(file, 'utf8').replace(/\r\n/g, '\n');
for (const method of ['addToCart', 'updateCart', 'searchProducts']) {
  source = source.replace(`public function ${method}()\n    {\n        $branchId = session('branch_id');`, `public function ${method}()\n    {\n        $branchId = \\App\\Libraries\\CashierShift::operatingBranch($this->db);`);
}
source = source.replace(`$sale = $this->salesModel\n            ->select('id, invoice_no, status')\n            ->where('checkout_token_hash', hash('sha256', $token))\n            ->where('user_id', $userId)\n            ->where('branch_id', $branchId)\n            ->first();`, `$lookup = $this->salesModel\n            ->select('id, invoice_no, status')\n            ->where('checkout_token_hash', hash('sha256', $token))\n            ->where('user_id', $userId);\n        if (session('role') !== 'admin') $lookup->where('branch_id', $branchId);\n        $sale = $lookup->first();`);
fs.writeFileSync(file, source);
file = 'app/Libraries/ProductExchange.php';
source = fs.readFileSync(file, 'utf8').replace(/\r\n/g, '\n');
const marker = `$sellingSale = $this->sale($saleId, $branchId, $role);`;
if (!source.includes('Recover a committed exchange')) source = source.replace(marker, marker + `\n            // Recover a committed exchange even after its original shift has closed.\n            $existing = $this->db->table('sales')->where('invoice_no', $invoice)->where('user_id', $userId)->get()->getRowArray();\n            if ($existing) { $this->db->transCommit(); return (int) $existing['id']; }`);
fs.writeFileSync(file, source);

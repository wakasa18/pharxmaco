<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use RuntimeException;

final class CashierShift
{
    public function __construct(private BaseConnection $db) {}

    public function enabled(): bool { return $this->db->tableExists('cashier_shifts'); }
    public static function now(): string { return (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s'); }
    public static function money(mixed $value): int
    {
        if (!is_scalar($value) || !preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', (string) $value)) throw new InvalidArgumentException('Enter a cash amount from 0 to 99,999,999.99 with at most two decimal places.');
        return DiscountPolicy::cents((float) $value);
    }
    public static function note(mixed $value, bool $required = true): string
    {
        $value = is_string($value) ? trim($value) : '';
        if (mb_strlen($value) > 500 || ($required && mb_strlen($value) < 3)) throw new InvalidArgumentException('Enter a reason between 3 and 500 characters.');
        return $value;
    }
    private function lock(): string { return $this->db->DBDriver === 'SQLite3' ? '' : ' FOR UPDATE'; }
    private function transaction(callable $work): mixed
    {
        $this->db->transBegin();
        try { $result = $work(); if (!$this->db->transStatus() || !$this->db->transCommit()) throw new RuntimeException('The drawer update could not be saved.'); return $result; }
        catch (\Throwable $error) { $this->db->transRollback(); throw $error; }
    }
    private function insert(string $table, array $values): int
    {
        if (!$this->db->table($table)->insert($values)) throw new RuntimeException('Could not save the cashier record.');
        return (int) $this->db->insertID();
    }
    private function update(string $table, int $id, array $values): void
    {
        if (!$this->db->table($table)->where('id', $id)->update($values)) throw new RuntimeException('Could not update the cashier record.');
    }
    private function user(int $id, bool $lock = false, bool $active = true): array
    {
        $user = $this->db->query('SELECT * FROM users WHERE id = ?' . ($lock ? $this->lock() : ''), [$id])->getRowArray();
        if (!$user || ($active && ($user['status'] !== 'active' || !in_array($user['role'], ['admin','cashier'], true)))) throw new InvalidArgumentException('This cashier account is unavailable.');
        return $user;
    }
    private function admin(int $id): void { if ($this->user($id)['role'] !== 'admin') throw new InvalidArgumentException('Administrator access is required.'); }
    public function audit(int $user, string $message): void
    {
        $this->insert('activity_logs', ['user_id' => $user, 'activity' => $message, 'log_time' => self::now()]);
    }
    public function activeFor(int $userId, int $branch = 0, bool $lock = false): ?array
    {
        if (!$this->enabled()) return null;
        $user = $this->user($userId, $lock);
        $shift = $this->db->table('cashier_shifts')->where('active_user_id', $userId)->where('status', 'open')->get()->getRowArray();
        if (!$shift || ($branch > 0 && (int) $shift['branch_id'] !== $branch)) return null;
        if ($user['role'] === 'cashier' && (int) $user['branch_id'] !== (int) $shift['branch_id']) return null;
        if ($lock) {
            $drawer = $this->db->query('SELECT * FROM cashier_drawers WHERE id = ?' . $this->lock(), [$shift['drawer_id']])->getRowArray();
            $shift = $this->db->query('SELECT * FROM cashier_shifts WHERE id = ?' . $this->lock(), [$shift['id']])->getRowArray();
            if (!$drawer || $drawer['status'] !== 'active' || (int) $drawer['active_shift_id'] !== (int) $shift['id'] || $shift['status'] !== 'open') return null;
        }
        return $shift;
    }
    public function requireActive(int $user, int $branch): ?array
    {
        if (!$this->enabled()) return null; // Upgrade is additive; earlier installations retain checkout until migration.
        $shift = $this->activeFor($user, $branch, true);
        if (!$shift) throw new InvalidArgumentException('Start a cashier shift in this branch before completing sales, refunds or exchanges.');
        if (!$this->db->table('branches')->where('id', $shift['branch_id'])->where('status', 'active')->countAllResults()) throw new InvalidArgumentException('The selling branch is inactive.');
        return $shift;
    }
    public static function cashierHome(BaseConnection $db): string
    {
        $workflow = new self($db);
        return $workflow->enabled() && $workflow->activeFor((int) session('user_id'), (int) session('branch_id'))
            ? 'cashier/sales' : 'cashier/start-shift';
    }
    public static function operatingBranch(BaseConnection $db): int
    {
        $branch = (int) session('branch_id');
        if (session('role') === 'admin') {
            $workflow = new self($db);
            if ($workflow->enabled()) {
                $shift = $workflow->activeFor((int) session('user_id'));
                if ($shift) return (int) $shift['branch_id'];
                $last = $db->table('cashier_shifts')->select('branch_id')->where('user_id', (int) session('user_id'))->orderBy('id', 'DESC')->get()->getRowArray();
                if ($last) return (int) $last['branch_id'];
            }
        }
        return $branch;
    }
    public function open(int $userId, int $drawerId, mixed $cash, string $key, mixed $note = ''): int
    {
        $amount = self::money($cash); $note = self::note($note, false);
        if (!preg_match('/^[a-f0-9]{32}$/D', $key)) throw new InvalidArgumentException('Refresh this page before opening a shift.');
        return $this->transaction(function () use ($userId, $drawerId, $amount, $key, $note) {
            $user = $this->user($userId, true);
            $openKey = hash('sha256', $userId . ':' . $key);
            $existing = $this->db->table('cashier_shifts')->where('open_key', $openKey)->get()->getRowArray();
            if ($existing) return (int) $existing['id'];
            if ($this->db->table('cashier_shifts')->where('active_user_id', $userId)->countAllResults()) throw new InvalidArgumentException('Close your current shift before opening another drawer.');
            $drawer = $this->db->query('SELECT * FROM cashier_drawers WHERE id = ?' . $this->lock(), [$drawerId])->getRowArray();
            if (!$drawer || $drawer['status'] !== 'active' || !empty($drawer['active_shift_id'])) throw new InvalidArgumentException('This drawer is unavailable or already in use.');
            if ($user['role'] === 'cashier' && (int) $user['branch_id'] !== (int) $drawer['branch_id']) throw new InvalidArgumentException('Choose a drawer in your assigned branch.');
            if (!$this->db->table('branches')->where('id', $drawer['branch_id'])->where('status', 'active')->countAllResults()) throw new InvalidArgumentException('Choose an active branch.');
            $last = empty($drawer['last_shift_id']) ? null : $this->db->query('SELECT * FROM cashier_shifts WHERE id = ?' . $this->lock(), [$drawer['last_shift_id']])->getRowArray();
            if ($last && $last['handover_status'] === 'pending' && (int) $last['handover_to_user_id'] !== $userId) throw new InvalidArgumentException('This drawer is reserved for the named handover cashier. An administrator can release the handover.');
            $expected = $last ? DiscountPolicy::cents((float) $last['cash_left']) : null;
            $difference = $expected === null ? 0 : $amount - $expected;
            if ($difference !== 0) self::note($note);
            $id = $this->insert('cashier_shifts', ['drawer_id' => $drawerId, 'branch_id' => $drawer['branch_id'], 'user_id' => $userId,
                'active_user_id' => $userId, 'active_drawer_id' => $drawerId, 'open_key' => $openKey, 'status' => 'open',
                'opening_cash' => $amount / 100, 'opening_expected' => $expected === null ? null : $expected / 100,
                'opening_difference' => $difference / 100, 'opening_note' => $note, 'opened_at' => self::now()]);
            $this->update('cashier_drawers', $drawerId, ['active_shift_id' => $id]);
            if ($last && $last['handover_status'] === 'pending') {
                $this->update('cashier_shifts', (int) $last['id'], ['handover_status' => 'accepted', 'handover_accepted_shift_id' => $id]);
                $this->action((int) $last['id'], $userId, 'handover_accepted', 'Received in shift #' . $id . '. Opening difference: PHP ' . number_format($difference / 100, 2, '.', ''));
            }
            $this->action($id, $userId, 'opened', $note);
            $this->audit($userId, 'Opened cashier shift #' . $id . ' at drawer #' . $drawerId);
            return $id;
        });
    }
    public function events(int $shift, bool $lock = false): array { return $this->db->query('SELECT * FROM cashier_events WHERE shift_id = ? ORDER BY id' . ($lock ? $this->lock() : ''), [$shift])->getResultArray(); }
    public function summary(array $shift, bool $lock = false): array
    {
        if ($shift['status'] === 'closed' && !empty($shift['summary_json'])) return json_decode($shift['summary_json'], true, 512, JSON_THROW_ON_ERROR);
        $events = $this->events((int) $shift['id'], $lock);
        return $this->withShiftTotals($shift, $events, CashierDayReport::summarize($events));
    }
    /** Print every original movement; closed financial totals retain their saved snapshot. */
    public function printReport(array $shift): array
    {
        $events = $this->events((int) $shift['id']);
        $report = CashierDayReport::summarize($events, null);
        $summary = $shift['status'] === 'closed' && !empty($shift['summary_json'])
            ? json_decode($shift['summary_json'], true, 512, JSON_THROW_ON_ERROR)
            : $this->withShiftTotals($shift, $events, $report);
        return ['summary' => $summary, 'activity' => array_reverse($report['activity'])];
    }
    private function withShiftTotals(array $shift, array $events, array $summary): array
    {
        $summary['opening_cash'] = (float) $shift['opening_cash'];
        $summary['expected_cash'] = (DiscountPolicy::cents((float) $shift['opening_cash'])
            + DiscountPolicy::cents($summary['payments']['cash']['net'])
            + DiscountPolicy::cents($summary['cash_added']) - DiscountPolicy::cents($summary['cash_removed'])) / 100.0;
        $summary['revision'] = hash('sha256', json_encode([(int) $shift['id'], array_map(static fn ($e) => [$e['id'],$e['event_key'],$e['amount']], $events)], JSON_THROW_ON_ERROR));
        return $summary;
    }
    /** Caller owns the surrounding sale/refund transaction and has locked this active shift. */
    public function event(?array $shift, string $key, string $type, string $method, int $cents, ?int $sale, array $snapshot = [], string $reason = ''): void
    {
        if ($shift === null) return;
        if (!in_array($method, ['cash','gcash','card'], true) || !in_array($type, ['sale','refund','exchange','adjustment','cash_in','cash_out'], true)) throw new InvalidArgumentException('Invalid cashier movement.');
        if ($this->db->table('cashier_events')->where('event_key', $key)->countAllResults()) return;
        if (!$this->db->table('cashier_shifts')->where('id', $shift['id'])->where('status', 'open')->countAllResults()) throw new InvalidArgumentException('This shift has closed. Start a new shift before continuing.');
        if ($method === 'cash' && $cents < 0 && DiscountPolicy::cents($this->summary($shift, true)['expected_cash']) + $cents < 0) throw new InvalidArgumentException('This drawer has insufficient recorded cash for the payout. Record a cash addition or choose the actual payout method.');
        $this->insert('cashier_events', ['event_key' => $key, 'shift_id' => $shift['id'], 'user_id' => $shift['user_id'], 'branch_id' => $shift['branch_id'],
            'event_type' => $type, 'payment_method' => $method, 'amount' => $cents / 100, 'sale_id' => $sale, 'reason' => $reason,
            'snapshot_json' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'created_at' => self::now()]);
    }
    public static function product(array $item, int $quantity, int $net, string $direction): array
    {
        return ['id' => (int) $item['product_id'], 'name' => (string) ($item['product_name_snapshot'] ?? $item['product_name'] ?? ''),
            'quantity' => $quantity, 'amount' => $net / 100.0, 'direction' => $direction];
    }
    public function recordSale(?array $shift, int $id): void
    {
        if ($shift === null) return;
        $sale = $this->db->table('sales')->where('id', $id)->get()->getRowArray();
        $items = $this->db->table('sale_items')->where('sale_id', $id)->get()->getResultArray();
        $products = array_map(static fn ($item) => self::product($item, (int) $item['quantity'], DiscountPolicy::cents(StatutoryDiscount::netItem($item)), 'sold'), $items);
        $this->event($shift, 'sale:' . $id, 'sale', $sale['payment_method'], DiscountPolicy::cents((float) $sale['final_total']), $id,
            ['invoice' => $sale['invoice_no'], 'products' => $products, 'discount' => (float) $sale['discount_amount'], 'vat_relief' => array_sum(array_column($items, 'vat_exemption_amount'))]);
    }
    public function cashMovement(int $user, int $branch, string $type, mixed $amount, mixed $reason, string $token): void
    {
        $cents = self::money($amount); $reason = self::note($reason);
        if ($cents <= 0 || !in_array($type, ['cash_in','cash_out'], true) || !preg_match('/^[a-f0-9]{32}$/D', $token)) throw new InvalidArgumentException('Choose cash added or removed, enter a positive amount and refresh an expired form.');
        $this->transaction(function () use ($user, $branch, $type, $cents, $reason, $token) {
            $shift = $this->requireActive($user, $branch);
            $key = 'cash:' . $user . ':' . $token;
            if ($this->db->table('cashier_events')->where('event_key', $key)->countAllResults()) return;
            $this->event($shift, $key, $type, 'cash', $type === 'cash_out' ? -$cents : $cents, null, [], $reason);
            $this->audit($user, 'Cash ' . ($type === 'cash_in' ? 'added to' : 'removed from') . ' shift #' . $shift['id'] . ': PHP ' . number_format($cents / 100, 2, '.', '') . '. ' . $reason);
        });
    }
    public function recordCorrection(?array $shift, array $sale, array $oldItems, array $plans, float $newTotal, string $method, string $reason): void
    {
        if ($shift === null) return;
        $old = DiscountPolicy::cents((float) $sale['final_total']); $new = DiscountPolicy::cents($newTotal);
        $products = [];
        foreach ($plans as $plan) {
            $net = DiscountPolicy::cents((float) $plan['subtotal'] - (float) $plan['discount'] - (float) ($plan['item']['vat_exemption_amount'] ?? 0));
            $delta = $net - DiscountPolicy::cents(StatutoryDiscount::netItem($plan['item']));
            if ($delta || $plan['qty_diff']) $products[] = self::product($plan['item'], (int) $plan['qty_diff'], $delta, 'adjustment');
        }
        if ($old === $new && $method === $sale['payment_method'] && !$products) return;
        $existing = $this->db->table('cashier_events')->where('sale_id', $sale['id'])->whereIn('event_type', ['sale','exchange'])->countAllResults();
        if (!$existing) {
            $original = array_intersect_key($sale, array_flip(['id','invoice_no','user_id','branch_id','payment_method','final_total','sale_date','discount_amount']));
            $this->event($shift, 'baseline:' . $sale['id'], 'adjustment', $sale['payment_method'], 0, (int) $sale['id'],
                ['baseline_only' => true, 'original_sale' => $original, 'original_products' => array_map(static fn ($item) => self::product($item, (int) $item['quantity'], DiscountPolicy::cents(StatutoryDiscount::netItem($item)), 'sold'), $oldItems)]);
        }
        $key = 'correction:' . bin2hex(random_bytes(16));
        if ($method !== $sale['payment_method']) {
            $this->event($shift, $key . ':out', 'adjustment', $sale['payment_method'], -$old, (int) $sale['id'], ['invoice' => $sale['invoice_no']], $reason);
            $this->event($shift, $key . ':in', 'adjustment', $method, $new, (int) $sale['id'], ['invoice' => $sale['invoice_no'], 'products' => $products], $reason);
        } else $this->event($shift, $key, 'adjustment', $method, $new - $old, (int) $sale['id'], ['invoice' => $sale['invoice_no'], 'products' => $products], $reason);
    }
    public function close(int $id, int $actor, mixed $counted, mixed $left, mixed $note, string $revision, ?int $handover, bool $discardHeld = false): void
    {
        $counted = self::money($counted); $left = self::money($left); $note = self::note($note, false);
        if ($left > $counted) throw new InvalidArgumentException('Cash left in the drawer cannot exceed the cash counted.');
        $this->transaction(function () use ($id, $actor, $counted, $left, $note, $revision, $handover, $discardHeld) {
            $actorRow = $this->user($actor);
            $shift = $this->db->table('cashier_shifts')->where('id', $id)->get()->getRowArray();
            if (!$shift || ($actorRow['role'] !== 'admin' && ((int) $shift['user_id'] !== $actor || (int) $shift['branch_id'] !== (int) $actorRow['branch_id']))) throw new InvalidArgumentException('Shift not found.');
            $this->user((int) $shift['user_id'], true, false);
            $drawer = $this->db->query('SELECT * FROM cashier_drawers WHERE id = ?' . $this->lock(), [$shift['drawer_id']])->getRowArray();
            $shift = $this->db->query('SELECT * FROM cashier_shifts WHERE id = ?' . $this->lock(), [$id])->getRowArray();
            if ($shift['status'] === 'closed') return;
            if (!$drawer || (int) $drawer['active_shift_id'] !== $id) throw new InvalidArgumentException('The drawer assignment changed. Reload the shift.');
            $summary = $this->summary($shift, true);
            if (!hash_equals($summary['revision'], $revision)) throw new InvalidArgumentException('New drawer activity arrived while you were counting. Refresh the shift and count again.');
            $held = $this->held((int) $shift['user_id'], (int) $shift['branch_id']);
            if ($held && !($actorRow['role'] === 'admin' && $discardHeld)) throw new InvalidArgumentException('Resume or discard held sales before closing this shift.');
            $difference = $counted - DiscountPolicy::cents($summary['expected_cash']);
            if ($difference !== 0 || $actor !== (int) $shift['user_id'] || $held) self::note($note);
            if ($handover) {
                $next = $this->user($handover);
                if ($handover === (int) $shift['user_id'] || (int) $next['branch_id'] !== (int) $shift['branch_id']) throw new InvalidArgumentException('Choose a different active cashier assigned to this branch.');
            }
            if ($held) {
                $this->db->table('cashier_held_sales')->where('user_id', $shift['user_id'])->where('branch_id', $shift['branch_id'])->delete();
                $this->action($id, $actor, 'held_sales_discarded', count($held) . ' held carts discarded by administrator. ' . $note);
            }
            $summary += ['counted_cash' => $counted / 100.0, 'cash_difference' => $difference / 100.0,
                'cash_left' => $left / 100.0, 'banked_cash' => ($counted - $left) / 100.0];
            $this->update('cashier_shifts', $id, ['status' => 'closed', 'active_user_id' => null, 'active_drawer_id' => null,
                'closed_at' => self::now(), 'expected_cash' => $summary['expected_cash'], 'counted_cash' => $counted / 100,
                'cash_difference' => $difference / 100, 'cash_left' => $left / 100, 'banked_cash' => ($counted - $left) / 100,
                'closing_note' => $note, 'summary_json' => json_encode($summary, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'handover_to_user_id' => $handover ?: null, 'handover_status' => $handover ? 'pending' : null]);
            $this->update('cashier_drawers', (int) $drawer['id'], ['active_shift_id' => null, 'last_shift_id' => $id]);
            $this->action($id, $actor, 'closed', $note); $this->audit($actor, 'Closed cashier shift #' . $id . '. Difference PHP ' . number_format($difference / 100, 2, '.', '') . '. ' . $note);
        });
    }
    private function action(int $shift, int $user, string $action, string $note): void
    {
        $this->insert('cashier_shift_actions', ['shift_id' => $shift, 'user_id' => $user, 'action' => $action, 'note' => $note, 'created_at' => self::now()]);
    }
    public function review(int $actor, string $kind, int $id, mixed $note): void
    {
        $this->admin($actor); $note = self::note($note);
        $this->transaction(function () use ($actor, $kind, $id, $note) {
            if (!in_array($kind, ['shift','event'], true)) throw new InvalidArgumentException('Choose a shift or movement to review.');
            $table = $kind === 'shift' ? 'cashier_shifts' : 'cashier_events';
            $record = $this->db->query('SELECT * FROM ' . $table . ' WHERE id = ?' . $this->lock(), [$id])->getRowArray();
            if (!$record || ($kind === 'shift' && $record['status'] !== 'closed')) throw new InvalidArgumentException('Only completed records can be reviewed.');
            $reviewTable = $kind === 'shift' ? 'cashier_shift_reviews' : 'cashier_event_reviews'; $key = $kind . '_id';
            if ($this->db->table($reviewTable)->where($key, $id)->countAllResults()) throw new InvalidArgumentException('This record has already been reviewed.');
            $this->insert($reviewTable, [$key => $id, 'reviewed_by' => $actor, 'review_note' => $note, 'reviewed_at' => self::now()]);
            $this->audit($actor, 'Reviewed cashier ' . $kind . ' #' . $id . '. ' . $note);
        });
    }
    public function releaseHandover(int $actor, int $id, mixed $note): void
    {
        $this->admin($actor); $note = self::note($note);
        $this->transaction(function () use ($actor, $id, $note) {
            $shift = $this->db->table('cashier_shifts')->where('id', $id)->get()->getRowArray();
            if (!$shift) throw new InvalidArgumentException('Shift not found.');
            $this->user((int) $shift['user_id'], true, false);
            $drawer = $this->db->query('SELECT * FROM cashier_drawers WHERE id = ?' . $this->lock(), [$shift['drawer_id']])->getRowArray();
            $shift = $this->db->query('SELECT * FROM cashier_shifts WHERE id = ?' . $this->lock(), [$id])->getRowArray();
            if ($shift['handover_status'] !== 'pending' || (int) ($drawer['last_shift_id'] ?? 0) !== $id || !empty($drawer['active_shift_id'])) throw new InvalidArgumentException('This handover is no longer waiting.');
            $this->update('cashier_shifts', $id, ['handover_status' => 'released']);
            $this->action($id, $actor, 'handover_released', $note); $this->audit($actor, 'Released handover for shift #' . $id . '. ' . $note);
        });
    }
    public function held(int $user, int $branch): array
    {
        if (!$this->enabled()) return [];
        $rows = $this->db->table('cashier_held_sales')->where('user_id', $user)->where('branch_id', $branch)->orderBy('held_at')->get()->getResultArray();
        $result = [];
        foreach ($rows as $row) { $row['cart'] = json_decode($row['cart_json'], true, 512, JSON_THROW_ON_ERROR); $result[(int) $row['id']] = $row; }
        return $result;
    }
    public function hold(int $userId, int $branch, array $cart, mixed $label, ?string $key = null, ?string $heldAt = null): int
    {
        $label = is_string($label) ? trim($label) : '';
        if (!$cart || mb_strlen($label) > 120) throw new InvalidArgumentException('Add products first and keep the held-sale label within 120 characters.');
        return $this->transaction(function () use ($userId, $branch, $cart, $label, $key, $heldAt) {
            $user = $this->user($userId, true);
            if ($branch < 1 || ($user['role'] === 'cashier' && (int) $user['branch_id'] !== $branch)) throw new InvalidArgumentException('Choose your assigned selling branch.');
            $key ??= bin2hex(random_bytes(32));
            $existing = $this->db->table('cashier_held_sales')->where('hold_key', $key)->get()->getRowArray();
            if ($existing) return (int) $existing['id'];
            if (count($this->held($userId, $branch)) >= 100) throw new InvalidArgumentException('Resolve existing held sales before holding more carts.');
            return $this->insert('cashier_held_sales', ['hold_key' => $key, 'user_id' => $userId, 'branch_id' => $branch,
                'label' => $label ?: null, 'cart_json' => json_encode($cart, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'held_at' => $heldAt ?? self::now()]);
        });
    }
    public function takeHeld(int $user, int $branch, int $id, bool $discard = false, bool $refresh = false): array
    {
        return $this->transaction(function () use ($user, $branch, $id, $discard, $refresh) {
            $account = $this->user($user, true);
            if ($account['role'] === 'cashier' && (int)$account['branch_id'] !== $branch) throw new InvalidArgumentException('Choose your assigned branch.');
            $row = $this->db->query('SELECT * FROM cashier_held_sales WHERE id = ? AND user_id = ? AND branch_id = ?' . $this->lock(), [$id,$user,$branch])->getRowArray();
            if (!$row) throw new InvalidArgumentException('This held sale is no longer available for your account and branch.');
            $cart = json_decode($row['cart_json'], true, 512, JSON_THROW_ON_ERROR);
            if (!$discard && $refresh) {
                $current = [];
                foreach ($cart as $item) {
                    $id = (int) ($item['product_id'] ?? 0); $quantity = (int) ($item['quantity'] ?? 0);
                    $product = $this->db->table('branch_products bp')->select('bp.*, p.product_name, p.category_id, p.tax_treatment, p.senior_pwd_eligible')
                        ->join('products p','p.id = bp.product_id')->where('bp.product_id',$id)->where('bp.branch_id',$branch)
                        ->where('bp.status','active')->where('p.status','active')
                        ->where('bp.deleted_at',null)->where('bp.is_deleted',0)->where('bp.is_permanently_deleted',0)
                        ->where('p.deleted_at',null)->where('p.is_deleted',0)->where('p.is_permanently_deleted',0)->get()->getRowArray();
                    if (!$product || $quantity < 1 || $quantity > (int)$product['stock'] || (!empty($product['expiration_date']) && $product['expiration_date'] <= substr(self::now(),0,10))) throw new InvalidArgumentException('The held cart is still saved. A product is unavailable, expired or has insufficient stock. Review branch stock or discard this held cart.');
                    $current[$id] = ['product_id'=>$id,'branch_product_id'=>(int)$product['id'],'product_name'=>$product['product_name'],
                        'category_id'=>$product['category_id'],'tax_treatment'=>$product['tax_treatment'],'senior_pwd_eligible'=>$product['senior_pwd_eligible'],
                        'cost_price'=>(float)$product['cost_price'],'price'=>(float)$product['price'],'quantity'=>$quantity,
                        'subtotal'=>round((float)$product['price']*$quantity,2),'stock'=>(int)$product['stock']];
                }
                $cart = $current;
            }
            if (!$this->db->table('cashier_held_sales')->where('id', $row['id'])->delete()) throw new RuntimeException('Could not update held sale.');
            if ($discard) $this->audit($user, 'Discarded held sale #' . $id);
            return $cart;
        });
    }
    public function importSessionHeld(int $user, int $branch): void
    {
        if (!$this->enabled() || $branch < 1) return;
        foreach (session('held_sales') ?? [] as $index => $held) {
            $this->hold($user, $branch, $held['cart'], $held['label'] ?? '', hash('sha256', json_encode([$user,$branch,$index,$held], JSON_THROW_ON_ERROR)), $held['held_at'] ?? null);
        }
        session()->remove('held_sales');
    }
}

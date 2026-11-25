<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;

/** Read-only ledger of returned units explicitly classified as damaged. */
final class DamagedItems
{
    public const SOURCES = ['refund' => 'Refund', 'exchange' => 'Exchange', 'quarantine' => 'Quarantine inspection'];

    public function __construct(private BaseConnection $db) {}

    public function includesInspections(): bool
    {
        return $this->db->tableExists('quarantine_resolutions');
    }

    private function query(?int $id = null, int $branch = 0)
    {
        $builder = $this->db->table('refund_items r')
            ->select('r.*, s.invoice_no, s.branch_id, b.branch_name, p.sku, p.unit, u.full_name AS returned_by_name')
            ->join('sales s', 's.id = r.sale_id')->join('branches b', 'b.id = s.branch_id', 'left')
            ->join('products p', 'p.id = r.product_id', 'left')->join('users u', 'u.id = r.refunded_by', 'left');
        if ($this->includesInspections()) {
            $builder->select('q.outcome AS inspection_outcome, q.inspected_at, q.note AS inspection_note, q.quantity AS inspected_quantity, i.full_name AS inspector')
                ->join('quarantine_resolutions q', 'q.refund_item_id = r.id', 'left')->join('users i', 'i.id = q.inspected_by', 'left')
                ->groupStart()->where('r.return_condition', 'damaged')
                ->orGroupStart()->where('r.return_condition', 'quarantined')->where('q.outcome', 'damaged')->groupEnd()->groupEnd();
        } else {
            $builder->select('NULL AS inspection_outcome, NULL AS inspected_at, NULL AS inspection_note, NULL AS inspected_quantity, NULL AS inspector', false)
                ->where('r.return_condition', 'damaged');
        }
        if ($id !== null) $builder->where('r.id', $id);
        if ($branch > 0) $builder->where('s.branch_id', $branch);
        return $builder->orderBy('r.id', 'DESC');
    }

    /** Match durable exchange invoice/event/branch identity, never free-text return reasons. */
    private function describe(array $records): array
    {
        $candidates = [];
        foreach ($records as $record) {
            if (preg_match('/^[a-f0-9]{32}$/D', (string) $record['refund_event_id'])) {
                $candidates[] = 'EXC-' . $record['sale_id'] . '-' . $record['refund_event_id'];
            }
        }
        $exchanges = [];
        foreach (array_chunk(array_values(array_unique($candidates)), 400) as $chunk) {
            foreach ($this->db->table('sales')->select('id, invoice_no, branch_id')->whereIn('invoice_no', $chunk)->get()->getResultArray() as $sale) {
                $identity = ExchangeRecord::identity($sale);
                if ($identity) $exchanges[$identity['original_id'] . ':' . $identity['event_id'] . ':' . $sale['branch_id']] = $sale;
            }
        }
        foreach ($records as &$record) {
            $exchange = $exchanges[$record['sale_id'] . ':' . $record['refund_event_id'] . ':' . $record['branch_id']] ?? null;
            $inspected = $record['return_condition'] === 'quarantined' && $record['inspection_outcome'] === 'damaged';
            $record['source'] = $inspected ? 'quarantine' : ($exchange ? 'exchange' : 'refund');
            $record['exchange'] = $exchange;
            $record['recorded_at'] = $inspected ? $record['inspected_at'] : $record['created_at'];
            $record['recorded_by'] = $inspected ? $record['inspector'] : $record['returned_by_name'];
            $record['damaged_quantity'] = (int) ($inspected ? $record['inspected_quantity'] : $record['quantity_refunded']);
            $record['damage_note'] = $inspected ? $record['inspection_note'] : $record['reason'];
        }
        unset($record);
        usort($records, static fn ($a, $b) => strcmp((string) $b['recorded_at'], (string) $a['recorded_at']) ?: ((int) $b['id'] <=> (int) $a['id']));
        return $records;
    }

    public function records(int $userId, string $source = 'all', int $branch = 0): array
    {
        $branch = ReturnRecordAccess::scope($this->db, $userId, $branch)['branch_id'];
        if ($source !== 'all' && !isset(self::SOURCES[$source])) throw new InvalidArgumentException('Choose a valid damage source.');
        if ($branch < 0) throw new InvalidArgumentException('Choose a valid branch.');
        $records = $this->describe($this->query(null, $branch)->get()->getResultArray());
        return $source === 'all' ? $records : array_values(array_filter($records, static fn ($record) => $record['source'] === $source));
    }

    public function item(int $id, int $userId): array
    {
        $scope = ReturnRecordAccess::scope($this->db, $userId);
        $records = $this->describe($this->query($id, $scope['branch_id'])->get()->getResultArray());
        if (!$records) throw new InvalidArgumentException('Damaged return not found.');
        return $records[0];
    }
}

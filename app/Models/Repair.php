<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Repair extends Model
{
    public const STATUSES = ['received', 'diagnosing', 'repairing', 'ready', 'delivered', 'cancelled'];

    /** Statuses that mean "still in the workshop". */
    public const OPEN_STATUSES = ['received', 'diagnosing', 'repairing', 'ready'];

    /**
     * Create a repair ticket (+ its first status-history entry).
     *
     * @param array{customer_id:int, device_type:string, brand:string, model:string,
     *              serial_no:string, problem:string, labor_cost:float, deposit:float} $d
     */
    public function create(array $d, int $userId): int
    {
        return (int) $this->transaction(function () use ($d, $userId): int {
            $this->execute(
                'INSERT INTO repairs
                    (ticket_no, customer_id, user_id, device_type, brand, model, serial_no,
                     problem, status, labor_cost, parts_cost, total_cost, paid_amount)
                 VALUES (:no, :cust, :user, :type, :brand, :model, :serial,
                         :problem, \'received\', :labor, 0, :labor2, :paid)',
                [
                    'no'      => 'TMP-' . bin2hex(random_bytes(6)),
                    'cust'    => $d['customer_id'],
                    'user'    => $userId,
                    'type'    => $d['device_type'],
                    'brand'   => $d['brand'] ?: null,
                    'model'   => $d['model'] ?: null,
                    'serial'  => $d['serial_no'] ?: null,
                    'problem' => $d['problem'],
                    'labor'   => $d['labor_cost'],
                    'labor2'  => $d['labor_cost'],
                    'paid'    => $d['deposit'],
                ]
            );
            $id = $this->lastId();
            $ticketNo = 'RT-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
            $this->execute('UPDATE repairs SET ticket_no = :no WHERE id = :id', ['no' => $ticketNo, 'id' => $id]);

            $this->logStatus($id, 'received', $d['deposit'] > 0
                ? 'Ticket created — deposit taken'
                : 'Ticket created', $userId);

            return $id;
        });
    }

    /** Update the editable header fields and re-derive the total. */
    public function updateDetails(int $id, array $d): void
    {
        $this->transaction(function () use ($id, $d): void {
            $this->execute(
                'UPDATE repairs SET device_type = :type, brand = :brand, model = :model,
                        serial_no = :serial, problem = :problem, tech_notes = :notes,
                        labor_cost = :labor
                 WHERE id = :id',
                [
                    'type'    => $d['device_type'],
                    'brand'   => $d['brand'] ?: null,
                    'model'   => $d['model'] ?: null,
                    'serial'  => $d['serial_no'] ?: null,
                    'problem' => $d['problem'],
                    'notes'   => $d['tech_notes'] ?: null,
                    'labor'   => $d['labor_cost'],
                    'id'      => $id,
                ]
            );
            $this->recomputeTotals($id);
        });
    }

    /**
     * Move the ticket to a new status. Delivering stamps delivered_at;
     * cancelling returns any stock-sourced parts to inventory.
     */
    public function setStatus(int $id, string $status, ?string $note, int $userId): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new \RuntimeException('Unknown repair status.');
        }

        $this->transaction(function () use ($id, $status, $note, $userId): void {
            $repair = $this->fetch('SELECT * FROM repairs WHERE id = :id FOR UPDATE', ['id' => $id]);
            if ($repair === null) {
                throw new \RuntimeException('Repair not found.');
            }
            if ($repair['status'] === $status) {
                return;
            }
            if ($repair['status'] === 'cancelled') {
                throw new \RuntimeException('A cancelled ticket cannot change status.');
            }

            $this->execute(
                'UPDATE repairs SET status = :s, delivered_at = :dat WHERE id = :id',
                [
                    's'   => $status,
                    'dat' => $status === 'delivered' ? date('Y-m-d H:i:s') : $repair['delivered_at'],
                    'id'  => $id,
                ]
            );

            if ($status === 'cancelled') {
                $this->restockParts($id, (string) $repair['ticket_no'], $userId);
            }

            $this->logStatus($id, $status, $note, $userId);
        });
    }

    /**
     * Attach a part. Stock parts ($productId) are deducted from inventory;
     * external parts are free-form.
     */
    public function addPart(
        int $repairId,
        ?int $productId,
        string $name,
        int $qty,
        float $unitCost,
        float $unitPrice,
        int $userId
    ): void {
        $this->transaction(function () use ($repairId, $productId, $name, $qty, $unitCost, $unitPrice, $userId): void {
            $repair = $this->fetch(
                'SELECT id, ticket_no, status FROM repairs WHERE id = :id FOR UPDATE',
                ['id' => $repairId]
            );
            if ($repair === null) {
                throw new \RuntimeException('Repair not found.');
            }
            if (!in_array($repair['status'], self::OPEN_STATUSES, true)) {
                throw new \RuntimeException('Parts can only be added while the repair is open.');
            }

            if ($productId !== null) {
                $product = $this->fetch(
                    'SELECT id, name, cost_price, selling_price FROM products WHERE id = :id AND is_active = 1',
                    ['id' => $productId]
                );
                if ($product === null) {
                    throw new \RuntimeException('Selected product not found.');
                }
                $name = (string) $product['name'];
                $unitCost = (float) $product['cost_price'];
                if ($unitPrice <= 0) {
                    if ($product['selling_price'] === null) {
                        throw new \RuntimeException(
                            '"' . $name . '" has no selling price yet — set one on the product, '
                            . 'or add it as an external part with a custom charge.'
                        );
                    }
                    $unitPrice = (float) $product['selling_price'];
                }

                (new Product())->applyStockChange(
                    $productId,
                    -$qty,
                    'repair',
                    (string) $repair['ticket_no'],
                    null,
                    $userId
                );
            }

            $this->execute(
                'INSERT INTO repair_parts (repair_id, product_id, part_name, quantity, unit_cost, unit_price)
                 VALUES (:r, :p, :name, :qty, :cost, :price)',
                [
                    'r' => $repairId, 'p' => $productId, 'name' => $name,
                    'qty' => $qty, 'cost' => $unitCost, 'price' => $unitPrice,
                ]
            );
            $this->recomputeTotals($repairId);
        });
    }

    public function removePart(int $partId, int $userId): void
    {
        $this->transaction(function () use ($partId, $userId): void {
            $part = $this->fetch(
                'SELECT rp.*, r.ticket_no, r.status
                 FROM repair_parts rp JOIN repairs r ON r.id = rp.repair_id
                 WHERE rp.id = :id FOR UPDATE',
                ['id' => $partId]
            );
            if ($part === null) {
                throw new \RuntimeException('Part not found.');
            }
            if (!in_array($part['status'], self::OPEN_STATUSES, true)) {
                throw new \RuntimeException('Parts can only be removed while the repair is open.');
            }

            if ($part['product_id'] !== null) {
                (new Product())->applyStockChange(
                    (int) $part['product_id'],
                    (int) $part['quantity'],
                    'repair_remove',
                    (string) $part['ticket_no'],
                    'Part removed from ticket',
                    $userId
                );
            }

            $this->execute('DELETE FROM repair_parts WHERE id = :id', ['id' => $partId]);
            $this->recomputeTotals((int) $part['repair_id']);
        });
    }

    public function recordPayment(int $id, float $amount): void
    {
        $this->execute(
            'UPDATE repairs SET paid_amount = paid_amount + :a WHERE id = :id',
            ['a' => round($amount, 2), 'id' => $id]
        );
    }

    public function search(array $f, int $page, int $perPage = 15): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($f['q'])) {
            $where[] = '(r.ticket_no LIKE :q OR c.name LIKE :q OR r.serial_no LIKE :q
                         OR r.brand LIKE :q OR r.model LIKE :q)';
            $params['q'] = '%' . $f['q'] . '%';
        }
        if (!empty($f['status']) && in_array($f['status'], self::STATUSES, true)) {
            $where[] = 'r.status = :status';
            $params['status'] = $f['status'];
        } elseif (($f['status'] ?? '') === 'open') {
            $where[] = "r.status IN ('received','diagnosing','repairing','ready')";
        }
        if (!empty($f['from'])) {
            $where[] = 'DATE(r.received_at) >= :dfrom';
            $params['dfrom'] = $f['from'];
        }
        if (!empty($f['to'])) {
            $where[] = 'DATE(r.received_at) <= :dto';
            $params['dto'] = $f['to'];
        }

        $whereSql = implode(' AND ', $where);

        return $this->paginate(
            "SELECT r.*, c.name AS customer_name, c.phone AS customer_phone,
                    (r.total_cost - r.paid_amount) AS balance
             FROM repairs r
             JOIN customers c ON c.id = r.customer_id
             WHERE {$whereSql}
             ORDER BY FIELD(r.status,'received','diagnosing','repairing','ready','delivered','cancelled'),
                      r.received_at DESC",
            "SELECT COUNT(*) FROM repairs r JOIN customers c ON c.id = r.customer_id WHERE {$whereSql}",
            $params,
            $page,
            $perPage
        );
    }

    public function find(int $id): ?array
    {
        return $this->fetch(
            'SELECT r.*, c.name AS customer_name, c.phone AS customer_phone,
                    c.address AS customer_address, u.full_name AS created_by
             FROM repairs r
             JOIN customers c ON c.id = r.customer_id
             LEFT JOIN users u ON u.id = r.user_id
             WHERE r.id = :id',
            ['id' => $id]
        );
    }

    public function parts(int $repairId): array
    {
        return $this->fetchAll(
            'SELECT * FROM repair_parts WHERE repair_id = :id ORDER BY id',
            ['id' => $repairId]
        );
    }

    public function statusHistory(int $repairId): array
    {
        return $this->fetchAll(
            'SELECT h.*, u.username
             FROM repair_status_history h
             LEFT JOIN users u ON u.id = h.user_id
             WHERE h.repair_id = :id
             ORDER BY h.id',
            ['id' => $repairId]
        );
    }

    // ------------------------------------------------------------ private --

    private function recomputeTotals(int $repairId): void
    {
        $this->execute(
            'UPDATE repairs r
             SET r.parts_cost = COALESCE((
                    SELECT SUM(rp.unit_price * rp.quantity) FROM repair_parts rp
                    WHERE rp.repair_id = r.id
                 ), 0),
                 r.total_cost = r.labor_cost + COALESCE((
                    SELECT SUM(rp.unit_price * rp.quantity) FROM repair_parts rp
                    WHERE rp.repair_id = r.id
                 ), 0)
             WHERE r.id = :id',
            ['id' => $repairId]
        );
    }

    private function restockParts(int $repairId, string $ticketNo, int $userId): void
    {
        $productModel = new Product();
        $parts = $this->fetchAll(
            'SELECT product_id, quantity FROM repair_parts
             WHERE repair_id = :id AND product_id IS NOT NULL',
            ['id' => $repairId]
        );
        foreach ($parts as $part) {
            $productModel->applyStockChange(
                (int) $part['product_id'],
                (int) $part['quantity'],
                'repair_remove',
                $ticketNo,
                'Repair cancelled',
                $userId
            );
        }
    }

    private function logStatus(int $repairId, string $status, ?string $note, int $userId): void
    {
        $this->execute(
            'INSERT INTO repair_status_history (repair_id, status, note, user_id)
             VALUES (:r, :s, :n, :u)',
            ['r' => $repairId, 's' => $status, 'n' => $note ?: null, 'u' => $userId]
        );
    }
}

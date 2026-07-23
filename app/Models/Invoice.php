<?php
class Invoice extends BaseModel {
    protected string $table = 'invoices';

    /**
     * 請求書一覧を取得
     */
    public function search(array $conditions, int $page = 1, int $perPage = 20): array {
        $scope = $this->scope();
        $where = "WHERE i.tenant_id = ? AND i.fiscal_year_id = ?";
        $params = $scope;

        if (!empty($conditions['customer_code'])) {
            $where .= " AND i.customer_code = ?";
            $params[] = $conditions['customer_code'];
        }

        if (!empty($conditions['date_from'])) {
            $where .= " AND i.invoice_date >= ?";
            $params[] = $conditions['date_from'];
        }

        if (!empty($conditions['date_to'])) {
            $where .= " AND i.invoice_date <= ?";
            $params[] = $conditions['date_to'];
        }

        if (isset($conditions['status']) && $conditions['status'] !== '') {
            $where .= " AND i.status = ?";
            $params[] = $conditions['status'];
        }

        $countSql = "SELECT COUNT(*) FROM invoices i {$where}";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT i.*, c.customer_name
                FROM invoices i
                LEFT JOIN customers c ON i.customer_code = c.customer_code AND c.tenant_id = i.tenant_id AND c.fiscal_year_id = i.fiscal_year_id
                {$where}
                ORDER BY i.invoice_date DESC, i.invoice_no DESC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return [
            'data' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($total / $perPage),
        ];
    }

    /**
     * 請求書詳細を取得（関連売上含む）
     */
    public function getWithDetails(int $id): ?array {
        $invoice = $this->findById($id);
        if (!$invoice) return null;

        // 関連する売上伝票を取得
        $stmt = $this->db->prepare("
            SELECT s.* FROM sales_slips s
            JOIN invoice_sales_links isl ON s.id = isl.sales_slip_id
            WHERE isl.invoice_id = ?
        ");
        $stmt->execute([$id]);
        $invoice['sales_slips'] = $stmt->fetchAll();

        return $invoice;
    }

    /**
     * 請求書を作成（締め請求）
     */
    public function createInvoice(array $data, array $salesSlipIds): int {
        $this->db->beginTransaction();

        try {
            $data['tenant_id'] = Session::getTenantId();
            $data['fiscal_year_id'] = Session::getFiscalYearId();
            $data['created_by'] = Session::getUserId();
            $data['status'] = 1; // 請求締済
            $data['created_at'] = date('Y-m-d H:i:s');

            $fields = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            $sql = "INSERT INTO {$this->table} ({$fields}) VALUES ({$placeholders})";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_values($data));
            $invoiceId = (int)$this->db->lastInsertId();

            // 関連売上を登録
            foreach ($salesSlipIds as $slipId) {
                $stmt = $this->db->prepare("INSERT INTO invoice_sales_links (invoice_id, sales_slip_id) VALUES (?, ?)");
                $stmt->execute([$invoiceId, $slipId]);

                // 売上伝票の状態を更新
                $stmt = $this->db->prepare("UPDATE sales_slips SET status = 1 WHERE id = ?");
                $stmt->execute([$slipId]);
            }

            $this->db->commit();
            return $invoiceId;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * 請求書を削除（締解除）
     */
    public function releaseInvoice(int $id): bool {
        $this->db->beginTransaction();

        try {
            $invoice = $this->findById($id);
            if (!$invoice) throw new Exception('請求書が見つかりません。');

            // 入金紐付チェック
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM payment_invoice_links WHERE invoice_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('入金が紐付いているため解除できません。');
            }

            // 関連売上の状態を戻す
            $stmt = $this->db->prepare("
                UPDATE sales_slips SET status = 0
                WHERE id IN (SELECT sales_slip_id FROM invoice_sales_links WHERE invoice_id = ?)
            ");
            $stmt->execute([$id]);

            // 関連を削除
            $stmt = $this->db->prepare("DELETE FROM invoice_sales_links WHERE invoice_id = ?");
            $stmt->execute([$id]);

            // 請求書を削除
            $this->delete($id);

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * 後続の請求書も同時に解除
     */
    public function releaseWithFollowing(int $id): bool {
        $this->db->beginTransaction();

        try {
            $targetInvoice = $this->findById($id);
            if (!$targetInvoice) throw new Exception('請求書が見つかりません。');

            // 対象以降の請求書を取得
            $stmt = $this->db->prepare("
                SELECT id FROM invoices
                WHERE tenant_id = ? AND fiscal_year_id = ?
                AND invoice_date >= ?
                ORDER BY invoice_date, id
            ");
            $stmt->execute([
                $targetInvoice['tenant_id'],
                $targetInvoice['fiscal_year_id'],
                $targetInvoice['invoice_date']
            ]);
            $invoices = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // 各請求書を解除
            foreach ($invoices as $invoiceId) {
                $this->releaseInvoice($invoiceId);
            }

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * 締め請求対象の未請求売上を取得
     */
    public function getUnbilledSales(string $customerCode, string $cutoffDate, bool $followCutoffDay = true): array {
        $scope = $this->scope();

        $where = "WHERE s.tenant_id = ? AND s.fiscal_year_id = ? AND s.status = 0";
        $params = [$scope[0], $scope[1]];

        if ($customerCode) {
            $where .= " AND s.customer_code = ?";
            $params[] = $customerCode;
        }

        $where .= " AND s.sales_date <= ?";
        $params[] = $cutoffDate;

        // 締日準拠の場合
        if ($followCutoffDay && $customerCode) {
            $stmt = $this->db->prepare("
                SELECT closing_day FROM customers
                WHERE tenant_id = ? AND fiscal_year_id = ? AND customer_code = ?
            ");
            $stmt->execute([$scope[0], $scope[1], $customerCode]);
            $closingDay = $stmt->fetchColumn();

            if ($closingDay && $closingDay < 31) {
                // 締日に基づいて絞り込み
                $cutoffDay = (int)$closingDay;
                $cutoffDateObj = new DateTime($cutoffDate);
                $cutoffMonth = $cutoffDateObj->format('Y-m');
                $maxDate = $cutoffMonth . '-' . str_pad($cutoffDay, 2, '0', STR_PAD_LEFT);
                $where .= " AND s.sales_date <= ?";
                $params[] = $maxDate;
            }
        }

        $sql = "SELECT s.* FROM sales_slips s {$where} ORDER BY s.sales_date, s.sales_slip_no";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * 未請求売上の合計を取得
     */
    public function getUnbilledTotal(string $customerCode, string $cutoffDate, bool $followCutoffDay = true): array {
        $sales = $this->getUnbilledSales($customerCode, $cutoffDate, $followCutoffDay);

        $totalExcl = 0;
        $totalTax = 0;

        foreach ($sales as $row) {
            $totalExcl += $row['total_amount'];
            $totalTax += $row['tax_amount'];
        }

        return [
            'total_excl' => $totalExcl,
            'total_tax' => $totalTax,
            'total_incl' => $totalExcl + $totalTax,
            'count' => count($sales),
        ];
    }
}

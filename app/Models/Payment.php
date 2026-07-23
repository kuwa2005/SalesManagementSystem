<?php
class Payment extends BaseModel {
    protected string $table = 'payment_slips';

    /**
     * 入金伝票一覧を取得
     */
    public function search(array $conditions, int $page = 1, int $perPage = 20): array {
        $scope = $this->scope();
        $where = "WHERE p.tenant_id = ? AND p.fiscal_year_id = ?";
        $params = $scope;

        if (!empty($conditions['customer_code'])) {
            $where .= " AND p.customer_code = ?";
            $params[] = $conditions['customer_code'];
        }

        if (!empty($conditions['date_from'])) {
            $where .= " AND p.payment_date >= ?";
            $params[] = $conditions['date_from'];
        }

        if (!empty($conditions['date_to'])) {
            $where .= " AND p.payment_date <= ?";
            $params[] = $conditions['date_to'];
        }

        $countSql = "SELECT COUNT(*) FROM {$this->table} p {$where}";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT p.*, c.customer_name
                FROM {$this->table} p
                LEFT JOIN customers c ON p.customer_code = c.customer_code AND c.tenant_id = p.tenant_id AND c.fiscal_year_id = p.fiscal_year_id
                {$where}
                ORDER BY p.payment_date DESC, p.payment_slip_no DESC
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
     * 入金伝票詳細を取得（明細+請求書紐付含む）
     */
    public function getWithDetails(int $id): ?array {
        $payment = $this->findById($id);
        if (!$payment) return null;

        $stmt = $this->db->prepare("
            SELECT pd.*, pt.payment_type_name
            FROM payment_details pd
            LEFT JOIN payment_types pt ON pd.payment_type_code = pt.payment_type_code AND pt.tenant_id = ?
            WHERE pd.payment_slip_id = ?
            ORDER BY pd.line_no
        ");
        $stmt->execute([Session::getTenantId(), $id]);
        $payment['details'] = $stmt->fetchAll();

        // 請求書紐付
        $stmt = $this->db->prepare("
            SELECT i.* FROM invoices i
            JOIN payment_invoice_links pil ON i.id = pil.invoice_id
            WHERE pil.payment_slip_id = ?
        ");
        $stmt->execute([$id]);
        $payment['invoices'] = $stmt->fetchAll();

        return $payment;
    }

    /**
     * 入金伝票を登録（明細+紐付含む）
     */
    public function createWithDetails(array $header, array $details, array $invoiceIds = []): int {
        $this->db->beginTransaction();

        try {
            $header['tenant_id'] = Session::getTenantId();
            $header['fiscal_year_id'] = Session::getFiscalYearId();
            $header['created_by'] = Session::getUserId();
            $header['status'] = 0;
            $header['created_at'] = date('Y-m-d H:i:s');

            $fields = implode(', ', array_keys($header));
            $placeholders = implode(', ', array_fill(0, count($header), '?'));
            $sql = "INSERT INTO {$this->table} ({$fields}) VALUES ({$placeholders})";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_values($header));
            $paymentId = (int)$this->db->lastInsertId();

            // 明細登録
            foreach ($details as $i => $detail) {
                $detail['payment_slip_id'] = $paymentId;
                $detail['line_no'] = $i + 1;

                $detailFields = implode(', ', array_keys($detail));
                $detailPlaceholders = implode(', ', array_fill(0, count($detail), '?'));
                $detailSql = "INSERT INTO payment_details ({$detailFields}) VALUES ({$detailPlaceholders})";
                $detailStmt = $this->db->prepare($detailSql);
                $detailStmt->execute(array_values($detail));
            }

            // 請求書紐付
            foreach ($invoiceIds as $invoiceId) {
                $stmt = $this->db->prepare("INSERT INTO payment_invoice_links (payment_slip_id, invoice_id) VALUES (?, ?)");
                $stmt->execute([$paymentId, $invoiceId]);

                // 請求書の状態を更新
                $stmt = $this->db->prepare("UPDATE invoices SET status = 2 WHERE id = ?");
                $stmt->execute([$invoiceId]);
            }

            $this->db->commit();
            return $paymentId;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * 入金伝票を削除（明細+紐付含む）
     */
    public function deleteWithDetails(int $id): bool {
        $this->db->beginTransaction();

        try {
            // 請求書紐付を解除
            $stmt = $this->db->prepare("
                UPDATE invoices SET status = 1
                WHERE id IN (SELECT invoice_id FROM payment_invoice_links WHERE payment_slip_id = ?)
            ");
            $stmt->execute([$id]);

            $stmt = $this->db->prepare("DELETE FROM payment_invoice_links WHERE payment_slip_id = ?");
            $stmt->execute([$id]);

            $stmt = $this->db->prepare("DELETE FROM payment_details WHERE payment_slip_id = ?");
            $stmt->execute([$id]);

            $this->delete($id);

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}

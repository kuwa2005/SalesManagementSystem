<?php
class SalesSlip extends BaseModel {
    protected string $table = 'sales_slips';

    /**
     * 売上伝票一覧を取得
     */
    public function search(array $conditions, int $page = 1, int $perPage = 20): array {
        $scope = $this->scope();
        $where = "WHERE s.tenant_id = ? AND s.fiscal_year_id = ?";
        $params = $scope;

        if (!empty($conditions['keyword'])) {
            $keyword = '%' . $conditions['keyword'] . '%';
            $where .= " AND (s.sales_slip_no LIKE ? OR c.customer_name LIKE ?)";
            $params[] = $keyword;
            $params[] = $keyword;
        }

        if (!empty($conditions['customer_code'])) {
            $where .= " AND s.customer_code = ?";
            $params[] = $conditions['customer_code'];
        }

        if (!empty($conditions['date_from'])) {
            $where .= " AND s.sales_date >= ?";
            $params[] = $conditions['date_from'];
        }

        if (!empty($conditions['date_to'])) {
            $where .= " AND s.sales_date <= ?";
            $params[] = $conditions['date_to'];
        }

        if (isset($conditions['status']) && $conditions['status'] !== '') {
            $where .= " AND s.status = ?";
            $params[] = $conditions['status'];
        }

        $countSql = "SELECT COUNT(*) FROM {$this->table} s LEFT JOIN customers c ON s.customer_code = c.customer_code AND c.tenant_id = s.tenant_id AND c.fiscal_year_id = s.fiscal_year_id {$where}";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT s.*, c.customer_name
                FROM {$this->table} s
                LEFT JOIN customers c ON s.customer_code = c.customer_code AND c.tenant_id = s.tenant_id AND c.fiscal_year_id = s.fiscal_year_id
                {$where}
                ORDER BY s.sales_date DESC, s.sales_slip_no DESC
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
     * 売上伝票詳細を取得（明細含む）
     */
    public function getWithDetails(int $id): ?array {
        $slip = $this->findById($id);
        if (!$slip) return null;

        $stmt = $this->db->prepare("
            SELECT * FROM sales_details
            WHERE sales_slip_id = ?
            ORDER BY line_no
        ");
        $stmt->execute([$id]);
        $slip['details'] = $stmt->fetchAll();

        return $slip;
    }

    /**
     * 未請求かチェック
     */
    public function isUnbilled(int $id): bool {
        $slip = $this->findById($id);
        return $slip && $slip['status'] == 0;
    }

    /**
     * 売上伝票を登録（明細含む）
     */
    public function createWithDetails(array $header, array $details): int {
        $this->db->beginTransaction();

        try {
            // ヘッダ登録
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
            $slipId = (int)$this->db->lastInsertId();

            // 明細登録
            foreach ($details as $i => $detail) {
                $detail['sales_slip_id'] = $slipId;
                $detail['line_no'] = $i + 1;

                $detailFields = implode(', ', array_keys($detail));
                $detailPlaceholders = implode(', ', array_fill(0, count($detail), '?'));
                $detailSql = "INSERT INTO sales_details ({$detailFields}) VALUES ({$detailPlaceholders})";
                $detailStmt = $this->db->prepare($detailSql);
                $detailStmt->execute(array_values($detail));
            }

            $this->db->commit();
            return $slipId;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * 売上伝票を更新（明細含む）
     */
    public function updateWithDetails(int $id, array $header, array $details): bool {
        $this->db->beginTransaction();

        try {
            // ヘッダ更新
            $header['updated_at'] = date('Y-m-d H:i:s');
            $this->update($id, $header);

            // 既存明細を削除して再登録
            $stmt = $this->db->prepare("DELETE FROM sales_details WHERE sales_slip_id = ?");
            $stmt->execute([$id]);

            // 明細登録
            foreach ($details as $i => $detail) {
                $detail['sales_slip_id'] = $id;
                $detail['line_no'] = $i + 1;

                $detailFields = implode(', ', array_keys($detail));
                $detailPlaceholders = implode(', ', array_fill(0, count($detail), '?'));
                $detailSql = "INSERT INTO sales_details ({$detailFields}) VALUES ({$detailPlaceholders})";
                $detailStmt = $this->db->prepare($detailSql);
                $detailStmt->execute(array_values($detail));
            }

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * 売上伝票を削除（明細含む）
     */
    public function deleteWithDetails(int $id): bool {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare("DELETE FROM sales_details WHERE sales_slip_id = ?");
            $stmt->execute([$id]);

            $this->delete($id);

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * 複写（新しい番号で複製）
     */
    public function copy(int $id, string $newSlipNo): int {
        $slip = $this->getWithDetails($id);
        if (!$slip) throw new Exception('伝票が見つかりません。');

        unset($slip['id']);
        $slip['sales_slip_no'] = $newSlipNo;
        $slip['status'] = 0;
        $slip['output_status_slip'] = 0;
        $slip['output_status_delivery'] = 0;
        $slip['output_status_receipt'] = 0;
        $slip['output_status_invoice'] = 0;
        $slip['invoice_no'] = null;
        $slip['invoice_close_date'] = null;

        $details = $slip['details'];
        unset($slip['details']);

        return $this->createWithDetails($slip, $details);
    }

    /**
     * 赤伝登録（負数明細の新規伝票）
     */
    public function createRedSlip(int $id, string $newSlipNo): int {
        $slip = $this->getWithDetails($id);
        if (!$slip) throw new Exception('伝票が見つかりません。');

        unset($slip['id']);
        $slip['sales_slip_no'] = $newSlipNo;
        $slip['status'] = 0;
        $slip['output_status_slip'] = 0;
        $slip['output_status_delivery'] = 0;
        $slip['output_status_receipt'] = 0;
        $slip['output_status_invoice'] = 0;
        $slip['invoice_no'] = null;
        $slip['invoice_close_date'] = null;

        // 明細の数量・金額を負数に
        $details = [];
        foreach ($slip['details'] as $detail) {
            unset($detail['id']);
            $detail['quantity'] = -$detail['quantity'];
            $detail['amount'] = -$detail['amount'];
            $details[] = $detail;
        }

        unset($slip['details']);
        $slip['total_amount'] = -$slip['total_amount'];
        $slip['tax_amount'] = -$slip['tax_amount'];
        $slip['gross_profit'] = -$slip['gross_profit'];

        return $this->createWithDetails($slip, $details);
    }

    /**
     * 出力状態を更新
     */
    public function updateOutputStatus(int $id, string $type): bool {
        $field = 'output_status_' . $type;
        return $this->update($id, [$field => 1]);
    }

    /**
     * 得意先の得意先情報を取得
     */
    public function getCustomerInfo(string $customerCode): ?array {
        $scope = $this->scope();
        $stmt = $this->db->prepare("
            SELECT * FROM customers
            WHERE tenant_id = ? AND fiscal_year_id = ? AND customer_code = ?
        ");
        $stmt->execute([$scope[0], $scope[1], $customerCode]);
        return $stmt->fetch() ?: null;
    }
}

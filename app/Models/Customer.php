<?php
class Customer extends BaseModel {
    protected string $table = 'customers';

    protected function getCodeField(): string {
        return 'customer_code';
    }

    /**
     * 得意先一覧を取得（検索対応）
     */
    public function search(array $conditions, int $page = 1, int $perPage = 20): array {
        $scope = $this->scope();
        $where = "WHERE {$this->tenantColumn} = ? AND {$this->fiscalYearColumn} = ?";
        $params = $scope;

        if (!empty($conditions['keyword'])) {
            $keyword = '%' . $conditions['keyword'] . '%';
            $where .= " AND (customer_code LIKE ? OR customer_name LIKE ? OR customer_name_kana LIKE ?)";
            $params[] = $keyword;
            $params[] = $keyword;
            $params[] = $keyword;
        }

        // 総件数
        $countSql = "SELECT COUNT(*) FROM {$this->table} {$where}";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        // データ取得
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT * FROM {$this->table} {$where} ORDER BY customer_name_kana, customer_code LIMIT {$perPage} OFFSET {$offset}";
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
     * 得意先を使用中かチェック
     */
    public function isInUse(int $customerId): bool {
        $db = Database::getConnection();

        // 売上伝票で使用中
        $stmt = $db->prepare("SELECT COUNT(*) FROM sales_slips WHERE customer_code = (SELECT customer_code FROM customers WHERE id = ?)");
        $stmt->execute([$customerId]);
        if ($stmt->fetchColumn() > 0) {
            return true;
        }

        return false;
    }

    /**
     * 未請求売上があるかチェック
     */
    public function hasUnbilledSales(string $customerCode): bool {
        $scope = $this->scope();
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM sales_slips
            WHERE {$this->tenantColumn} = ? AND {$this->fiscalYearColumn} = ?
            AND customer_code = ? AND status = 0
        ");
        $stmt->execute([$scope[0], $scope[1], $customerCode]);
        return $stmt->fetchColumn() > 0;
    }
}

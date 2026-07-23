<?php
class Product extends BaseModel {
    protected string $table = 'products';

    protected function getCodeField(): string {
        return 'product_code';
    }

    /**
     * 商品一覧を取得（検索対応）
     */
    public function search(array $conditions, int $page = 1, int $perPage = 20): array {
        $scope = $this->scope();
        $where = "WHERE {$this->tenantColumn} = ? AND {$this->fiscalYearColumn} = ?";
        $params = $scope;

        if (!empty($conditions['keyword'])) {
            $keyword = '%' . $conditions['keyword'] . '%';
            $where .= " AND (product_code LIKE ? OR product_name LIKE ? OR product_name_kana LIKE ?)";
            $params[] = $keyword;
            $params[] = $keyword;
            $params[] = $keyword;
        }

        $countSql = "SELECT COUNT(*) FROM {$this->table} {$where}";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT * FROM {$this->table} {$where} ORDER BY product_name_kana, product_code LIMIT {$perPage} OFFSET {$offset}";
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
     * 商品を使用中かチェック
     */
    public function isInUse(int $productId): bool {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM sales_details
            WHERE product_code = (SELECT product_code FROM products WHERE id = ?)
        ");
        $stmt->execute([$productId]);
        return $stmt->fetchColumn() > 0;
    }
}

<?php
class Category extends BaseModel {
    protected string $table = 'categories';

    public function search(array $conditions, int $page = 1, int $perPage = 20): array {
        $scope = $this->scope();
        $where = "WHERE {$this->tenantColumn} = ? AND {$this->fiscalYearColumn} = ?";
        $params = $scope;

        if (!empty($conditions['keyword'])) {
            $keyword = '%' . $conditions['keyword'] . '%';
            $where .= " AND (large_name LIKE ? OR medium_name LIKE ? OR small_name LIKE ?)";
            $params[] = $keyword;
            $params[] = $keyword;
            $params[] = $keyword;
        }

        $countSql = "SELECT COUNT(*) FROM {$this->table} {$where}";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT * FROM {$this->table} {$where} ORDER BY large_code, medium_code, small_code LIMIT {$perPage} OFFSET {$offset}";
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

    public function getLargeCategories(): array {
        $scope = $this->scope();
        $stmt = $this->db->prepare("
            SELECT DISTINCT large_code, large_name FROM {$this->table}
            WHERE {$this->tenantColumn} = ? AND {$this->fiscalYearColumn} = ?
            AND large_code IS NOT NULL AND large_code != ''
            ORDER BY large_code
        ");
        $stmt->execute($scope);
        return $stmt->fetchAll();
    }

    public function getMediumCategories(string $largeCode): array {
        $scope = $this->scope();
        $stmt = $this->db->prepare("
            SELECT DISTINCT medium_code, medium_name FROM {$this->table}
            WHERE {$this->tenantColumn} = ? AND {$this->fiscalYearColumn} = ?
            AND large_code = ? AND medium_code IS NOT NULL AND medium_code != ''
            ORDER BY medium_code
        ");
        $stmt->execute([$scope[0], $scope[1], $largeCode]);
        return $stmt->fetchAll();
    }

    public function isInUse(int $id): bool {
        $cat = $this->findById($id);
        if (!$cat) return false;

        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM products
            WHERE large_category_code = ? OR medium_category_code = ? OR small_category_code = ?
        ");
        $stmt->execute([$cat['large_code'], $cat['medium_code'], $cat['small_code']]);
        return $stmt->fetchColumn() > 0;
    }
}

<?php
class Department extends BaseModel {
    protected string $table = 'departments';

    protected function getCodeField(): string {
        return 'department_code';
    }

    public function search(array $conditions, int $page = 1, int $perPage = 20): array {
        $scope = $this->scope();
        $where = "WHERE {$this->tenantColumn} = ? AND {$this->fiscalYearColumn} = ?";
        $params = $scope;

        if (!empty($conditions['keyword'])) {
            $keyword = '%' . $conditions['keyword'] . '%';
            $where .= " AND (department_code LIKE ? OR department_name LIKE ? OR department_name_kana LIKE ?)";
            $params[] = $keyword;
            $params[] = $keyword;
            $params[] = $keyword;
        }

        $countSql = "SELECT COUNT(*) FROM {$this->table} {$where}";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT * FROM {$this->table} {$where} ORDER BY department_name_kana, department_code LIMIT {$perPage} OFFSET {$offset}";
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

    public function isInUse(int $id): bool {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM sales_slips
            WHERE department_code = (SELECT department_code FROM departments WHERE id = ?)
        ");
        $stmt->execute([$id]);
        return $stmt->fetchColumn() > 0;
    }

    public function getAll(): array {
        $scope = $this->scope();
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table}
            WHERE {$this->tenantColumn} = ? AND {$this->fiscalYearColumn} = ?
            ORDER BY department_name_kana
        ");
        $stmt->execute($scope);
        return $stmt->fetchAll();
    }
}

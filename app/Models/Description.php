<?php
class Description extends BaseModel {
    protected string $table = 'descriptions';

    protected function getCodeField(): string {
        return 'description_code';
    }

    public function search(array $conditions, int $page = 1, int $perPage = 20): array {
        $scope = $this->scope();
        $where = "WHERE {$this->tenantColumn} = ? AND {$this->fiscalYearColumn} = ?";
        $params = $scope;

        if (!empty($conditions['type'])) {
            $where .= " AND description_type = ?";
            $params[] = $conditions['type'];
        }

        if (!empty($conditions['keyword'])) {
            $keyword = '%' . $conditions['keyword'] . '%';
            $where .= " AND (description_code LIKE ? OR description_name LIKE ?)";
            $params[] = $keyword;
            $params[] = $keyword;
        }

        $countSql = "SELECT COUNT(*) FROM {$this->table} {$where}";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT * FROM {$this->table} {$where} ORDER BY description_type, description_code LIMIT {$perPage} OFFSET {$offset}";
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
        return false; // 摘要は削除制限なし
    }

    public function getAll(int $type = 0): array {
        $scope = $this->scope();
        $where = "WHERE {$this->tenantColumn} = ? AND {$this->fiscalYearColumn} = ?";
        $params = $scope;

        if ($type > 0) {
            $where .= " AND description_type = ?";
            $params[] = $type;
        }

        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table} {$where} ORDER BY description_type, description_code
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getTypeName(int $type): string {
        $names = [1 => '売上伝票', 2 => '入金伝票', 3 => '請求書', 4 => '領収書'];
        return $names[$type] ?? '';
    }
}

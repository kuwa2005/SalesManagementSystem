<?php
class PaymentType extends BaseModel {
    protected string $table = 'payment_types';

    protected function getCodeField(): string {
        return 'payment_type_code';
    }

    public function search(array $conditions, int $page = 1, int $perPage = 20): array {
        $scope = $this->scope();
        $where = "WHERE {$this->tenantColumn} = ? AND {$this->fiscalYearColumn} = ?";
        $params = $scope;

        if (!empty($conditions['keyword'])) {
            $keyword = '%' . $conditions['keyword'] . '%';
            $where .= " AND (payment_type_code LIKE ? OR payment_type_name LIKE ?)";
            $params[] = $keyword;
            $params[] = $keyword;
        }

        $countSql = "SELECT COUNT(*) FROM {$this->table} {$where}";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT * FROM {$this->table} {$where} ORDER BY payment_type_code LIMIT {$perPage} OFFSET {$offset}";
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
            SELECT COUNT(*) FROM payment_details
            WHERE payment_type_code = (SELECT payment_type_code FROM payment_types WHERE id = ?)
        ");
        $stmt->execute([$id]);
        return $stmt->fetchColumn() > 0;
    }

    public function getAll(): array {
        $scope = $this->scope();
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table}
            WHERE {$this->tenantColumn} = ? AND {$this->fiscalYearColumn} = ?
            ORDER BY payment_type_code
        ");
        $stmt->execute($scope);
        return $stmt->fetchAll();
    }

    public function getCategoryName(int $category): string {
        $names = [1 => '現金', 2 => '振込', 3 => '手数料', 4 => '手形'];
        return $names[$category] ?? '';
    }
}

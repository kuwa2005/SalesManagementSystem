<?php
class BaseModel {
    protected PDO $db;
    protected string $table;
    protected string $tenantColumn = 'tenant_id';
    protected string $fiscalYearColumn = 'fiscal_year_id';

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * テナント・年度のスコープを取得
     */
    protected function scope(): array {
        return [
            Session::getTenantId(),
            Session::getFiscalYearId(),
        ];
    }

    /**
     * IDで取得
     */
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * 一覧取得（ページネーション）
     */
    public function list(int $page = 1, int $perPage = 20, array $conditions = []): array {
        $scope = $this->scope();
        $where = "WHERE {$this->tenantColumn} = ? AND {$this->fiscalYearColumn} = ?";
        $params = $scope;

        foreach ($conditions as $field => $value) {
            if ($value !== null && $value !== '') {
                $where .= " AND {$field} = ?";
                $params[] = $value;
            }
        }

        // 総件数
        $countSql = "SELECT COUNT(*) FROM {$this->table} {$where}";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        // データ取得
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT * FROM {$this->table} {$where} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}";
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
     * 登録
     */
    public function create(array $data): int {
        $data['tenant_id'] = Session::getTenantId();
        $data['fiscal_year_id'] = Session::getFiscalYearId();
        $data['created_at'] = date('Y-m-d H:i:s');

        $fields = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$this->table} ({$fields}) VALUES ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($data));

        return (int)$this->db->lastInsertId();
    }

    /**
     * 更新
     */
    public function update(int $id, array $data): bool {
        $data['updated_at'] = date('Y-m-d H:i:s');

        $set = [];
        $params = [];
        foreach ($data as $field => $value) {
            $set[] = "{$field} = ?";
            $params[] = $value;
        }
        $params[] = $id;

        $sql = "UPDATE {$this->table} SET " . implode(', ', $set) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * 削除
     */
    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * コードで検索
     */
    public function findByCode(string $code): ?array {
        $scope = $this->scope();
        $codeField = $this->getCodeField();

        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table}
            WHERE {$this->tenantColumn} = ? AND {$this->fiscalYearColumn} = ? AND {$codeField} = ?
        ");
        $stmt->execute([$scope[0], $scope[1], $code]);
        return $stmt->fetch() ?: null;
    }

    /**
     * コードフィールド名を取得（サブクラスでオーバーライド）
     */
    protected function getCodeField(): string {
        return 'code';
    }
}

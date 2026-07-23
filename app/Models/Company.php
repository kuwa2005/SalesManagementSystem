<?php
class Company extends BaseModel {
    protected string $table = 'company_info';

    /**
     * 現在の年度の基本情報を取得
     */
    public function getCurrent(): ?array {
        $scope = $this->scope();
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table}
            WHERE {$this->tenantColumn} = ? AND {$this->fiscalYearColumn} = ?
        ");
        $stmt->execute($scope);
        return $stmt->fetch() ?: null;
    }

    /**
     * 基本情報を登録/更新
     */
    public function save(array $data): int {
        $existing = $this->getCurrent();

        if ($existing) {
            $this->update($existing['id'], $data);
            return $existing['id'];
        } else {
            return $this->create($data);
        }
    }
}

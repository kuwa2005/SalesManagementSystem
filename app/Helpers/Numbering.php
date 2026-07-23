<?php
class Numbering {
    /**
     * 伝票番号を生成
     */
    public static function generateSalesSlipNo(int $tenantId, int $fiscalYearId, int $method): string {
        $db = Database::getConnection();

        if ($method === 1) {
            // 自動付番（年度）
            $stmt = $db->prepare("
                SELECT sales_slip_no FROM sales_slips
                WHERE tenant_id = ? AND fiscal_year_id = ?
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute([$tenantId, $fiscalYearId]);
            $last = $stmt->fetchColumn();

            if ($last) {
                $nextNum = intval(substr($last, -6)) + 1;
            } else {
                $nextNum = 1;
            }

            $fy = self::getFiscalYearLabel($fiscalYearId);
            return $fy . str_pad($nextNum, 6, '0', STR_PAD_LEFT);

        } elseif ($method === 2) {
            // 自動付番（月度）
            $fy = self::getFiscalYearLabel($fiscalYearId);
            $month = date('Ym');

            $stmt = $db->prepare("
                SELECT sales_slip_no FROM sales_slips
                WHERE tenant_id = ? AND fiscal_year_id = ?
                AND sales_slip_no LIKE ?
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute([$tenantId, $fiscalYearId, $fy . $month . '%']);
            $last = $stmt->fetchColumn();

            if ($last) {
                $nextNum = intval(substr($last, -4)) + 1;
            } else {
                $nextNum = 1;
            }

            return $fy . $month . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
        }

        // 手入力の場合は呼び出し側で設定
        return '';
    }

    /**
     * 請求書番号を生成
     */
    public static function generateInvoiceNo(int $tenantId, int $fiscalYearId, int $method): string {
        $db = Database::getConnection();

        if ($method === 1) {
            $stmt = $db->prepare("
                SELECT invoice_no FROM invoices
                WHERE tenant_id = ? AND fiscal_year_id = ?
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute([$tenantId, $fiscalYearId]);
            $last = $stmt->fetchColumn();

            if ($last) {
                $nextNum = intval(substr($last, -6)) + 1;
            } else {
                $nextNum = 1;
            }

            $fy = self::getFiscalYearLabel($fiscalYearId);
            return $fy . str_pad($nextNum, 6, '0', STR_PAD_LEFT);

        } elseif ($method === 2) {
            $fy = self::getFiscalYearLabel($fiscalYearId);
            $month = date('Ym');

            $stmt = $db->prepare("
                SELECT invoice_no FROM invoices
                WHERE tenant_id = ? AND fiscal_year_id = ?
                AND invoice_no LIKE ?
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute([$tenantId, $fiscalYearId, $fy . $month . '%']);
            $last = $stmt->fetchColumn();

            if ($last) {
                $nextNum = intval(substr($last, -4)) + 1;
            } else {
                $nextNum = 1;
            }

            return $fy . $month . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
        }

        return '';
    }

    /**
     * 入金伝票番号を生成
     */
    public static function generatePaymentSlipNo(int $tenantId, int $fiscalYearId, int $method): string {
        $db = Database::getConnection();

        if ($method === 1) {
            $stmt = $db->prepare("
                SELECT payment_slip_no FROM payment_slips
                WHERE tenant_id = ? AND fiscal_year_id = ?
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute([$tenantId, $fiscalYearId]);
            $last = $stmt->fetchColumn();

            if ($last) {
                $nextNum = intval(substr($last, -6)) + 1;
            } else {
                $nextNum = 1;
            }

            $fy = self::getFiscalYearLabel($fiscalYearId);
            return $fy . str_pad($nextNum, 6, '0', STR_PAD_LEFT);

        } elseif ($method === 2) {
            $fy = self::getFiscalYearLabel($fiscalYearId);
            $month = date('Ym');

            $stmt = $db->prepare("
                SELECT payment_slip_no FROM payment_slips
                WHERE tenant_id = ? AND fiscal_year_id = ?
                AND payment_slip_no LIKE ?
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute([$tenantId, $fiscalYearId, $fy . $month . '%']);
            $last = $stmt->fetchColumn();

            if ($last) {
                $nextNum = intval(substr($last, -4)) + 1;
            } else {
                $nextNum = 1;
            }

            return $fy . $month . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
        }

        return '';
    }

    private static function getFiscalYearLabel(int $fiscalYearId): string {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT year_label FROM fiscal_years WHERE id = ?");
        $stmt->execute([$fiscalYearId]);
        return $stmt->fetchColumn() ?: date('Y');
    }
}

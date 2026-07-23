<?php
class TaxCalculator {
    /**
     * 税率を取得
     */
    public static function getTaxRate(int $tenantId, int $fiscalYearId, string $date): array {
        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT standard_rate, reduced_rate FROM tax_rates
            WHERE tenant_id = ? AND fiscal_year_id = ? AND effective_date <= ?
            ORDER BY effective_date DESC LIMIT 1
        ");
        $stmt->execute([$tenantId, $fiscalYearId, $date]);
        $rate = $stmt->fetch();

        if (!$rate) {
            // デフォルト税率
            return ['standard' => 10.0, 'reduced' => 8.0];
        }

        return [
            'standard' => (float)$rate['standard_rate'],
            'reduced' => (float)$rate['reduced_rate'],
        ];
    }

    /**
     * 税抜→税込変換
     */
    public static function exclToIncl(int $amount, float $rate, int $fractionMethod): int {
        $tax = $amount * $rate / 100;
        $tax = self::applyFraction($tax, $fractionMethod);
        return $amount + $tax;
    }

    /**
     * 税込→税抜変換
     */
    public static function inclToExcl(int $amount, float $rate, int $fractionMethod): int {
        $excl = $amount / (1 + $rate / 100);
        return self::applyFraction($excl, $fractionMethod);
    }

    /**
     * 消費税額を計算
     */
    public static function calculateTax(int $amount, float $rate, int $fractionMethod): int {
        $tax = $amount * $rate / 100;
        return self::applyFraction($tax, $fractionMethod);
    }

    /**
     * 端数処理を適用
     * 1=切捨て, 2=切上げ, 3=四捨五入
     */
    public static function applyFraction(float $value, int $method): int {
        switch ($method) {
            case 1: // 切捨て
                return (int)floor($value);
            case 2: // 切上げ
                return (int)ceil($value);
            case 3: // 四捨五入
            default:
                return (int)round($value);
        }
    }

    /**
     * 金額端数処理
     */
    public static function applyAmountFraction(float $value, int $method): int {
        return self::applyFraction($value, $method);
    }

    /**
     * 単価端数処理
     */
    public static function applyUnitPriceFraction(float $value, int $method): int {
        return self::applyFraction($value, $method);
    }

    /**
     * 適用税率を決定
     * 売上日付と商品課税区分・軽減税率区分から
     */
    public static function getApplicableRate(
        int $tenantId,
        int $fiscalYearId,
        string $salesDate,
        int $taxCategory,
        bool $isReducedTax
    ): float {
        // 非課税・対象外
        if ($taxCategory !== 1) {
            return 0.0;
        }

        $rates = self::getTaxRate($tenantId, $fiscalYearId, $salesDate);

        // 軽減税率対象
        if ($isReducedTax) {
            return $rates['reduced'];
        }

        return $rates['standard'];
    }

    /**
     * 得意先の税処理に応じた消費税計算
     */
    public static function calculateByTaxProcessing(
        int $totalAmount,
        int $taxProcessing,
        float $taxRate,
        int $taxFractionMethod
    ): array {
        switch ($taxProcessing) {
            case 1: // 外税/伝票計
            case 2: // 外税/請求時
                $tax = self::calculateTax($totalAmount, $taxRate, $taxFractionMethod);
                return ['excl_amount' => $totalAmount, 'tax' => $tax, 'incl_amount' => $totalAmount + $tax];

            case 3: // 内税/伝票計
            case 4: // 内税/請求時
                $tax = self::calculateTax($totalAmount, $taxRate, $taxFractionMethod);
                return ['excl_amount' => $totalAmount - $tax, 'tax' => $tax, 'incl_amount' => $totalAmount];

            case 5: // 免税
                return ['excl_amount' => $totalAmount, 'tax' => 0, 'incl_amount' => $totalAmount];

            case 6: // 外税/手入力
                return ['excl_amount' => $totalAmount, 'tax' => 0, 'incl_amount' => $totalAmount];

            default:
                return ['excl_amount' => $totalAmount, 'tax' => 0, 'incl_amount' => $totalAmount];
        }
    }
}

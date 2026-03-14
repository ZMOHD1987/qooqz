<?php
declare(strict_types=1);

/**
 * Static validation for entity financial balance operations.
 */
final class EntityFinancialBalancesValidator
{
    public static function validateUpsert(array $data): array
    {
        $errors = [];

        if (empty($data['entity_id'])) {
            $errors[] = 'entity_id is required';
        }

        if (empty($data['tenant_id'])) {
            $errors[] = 'tenant_id is required';
        }

        $numericFields = [
            'total_transactions', 'total_sales_count', 'total_refunds_count',
            'total_sales_amount', 'total_refunds_amount', 'net_sales',
            'total_commission', 'total_vat', 'total_net_commission',
            'total_invoiced', 'total_paid', 'total_balance',
            'pending_balance', 'invoiced_balance',
            'total_invoices', 'total_payments', 'total_credit_notes',
        ];

        foreach ($numericFields as $field) {
            if (isset($data[$field]) && !is_numeric($data[$field])) {
                $errors[] = "{$field} must be numeric";
            }
        }

        return $errors;
    }
}

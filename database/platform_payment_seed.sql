-- Seed platform (entity_id=1) bank account and payment methods
-- Run this SQL to populate payment options for the plan selection page
-- Update the bank account details with real platform banking information

-- Platform bank account
INSERT IGNORE INTO entity_bank_accounts
    (entity_id, bank_name, account_holder_name, account_number, iban, swift_code, is_primary, is_verified)
VALUES
    (1, 'البنك الرئيسي للمنصة', 'شركة المنصة', '0123456789', 'SA0000000000000000000001', 'BANKSA00', 1, 1);

-- Platform payment methods (link to payment_methods table)
-- Stripe
INSERT IGNORE INTO entity_payment_methods (entity_id, payment_method_id, account_email, account_id, is_active)
SELECT 1, pm.id, 'platform@example.com', 'acct_platform_stripe', 1
FROM payment_methods pm WHERE pm.method_key = 'stripe' LIMIT 1;

-- Bank transfer
INSERT IGNORE INTO entity_payment_methods (entity_id, payment_method_id, account_email, account_id, is_active)
SELECT 1, pm.id, 'platform@example.com', 'bank_transfer_platform', 1
FROM payment_methods pm WHERE pm.method_key = 'bank_transfer' LIMIT 1;

-- PayPal
INSERT IGNORE INTO entity_payment_methods (entity_id, payment_method_id, account_email, account_id, is_active)
SELECT 1, pm.id, 'platform@example.com', 'platform_paypal', 1
FROM payment_methods pm WHERE pm.method_key = 'paypal' LIMIT 1;

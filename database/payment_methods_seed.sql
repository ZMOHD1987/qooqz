-- Payment Methods Seed Data
-- Run this SQL to populate the payment_methods table with common payment gateways

INSERT IGNORE INTO payment_methods (method_key, method_name, description, gateway_name, icon_url) VALUES
('paypal', 'PayPal', 'الدفع عبر باي بال', 'PayPal', '/assets/icons/payments/paypal.svg'),
('stripe', 'Stripe', 'الدفع عبر سترايب', 'Stripe', '/assets/icons/payments/stripe.svg'),
('tap', 'Tap Payments', 'بوابة تاب للدفع الإلكتروني', 'Tap', '/assets/icons/payments/tap.svg'),
('hyperpay', 'HyperPay', 'بوابة هايبر باي', 'HyperPay', '/assets/icons/payments/hyperpay.svg'),
('paytabs', 'PayTabs', 'بوابة باي تابز', 'PayTabs', '/assets/icons/payments/paytabs.svg'),
('telr', 'Telr', 'بوابة تلر للدفع', 'Telr', '/assets/icons/payments/telr.svg'),
('moyasar', 'Moyasar', 'بوابة ميسر للدفع', 'Moyasar', '/assets/icons/payments/moyasar.svg'),
('tabby', 'Tabby', 'الدفع بالتقسيط عبر تابي', 'Tabby', '/assets/icons/payments/tabby.svg'),
('tamara', 'Tamara', 'الدفع بالتقسيط عبر تمارا', 'Tamara', '/assets/icons/payments/tamara.svg'),
('mada', 'Mada', 'الدفع عبر بطاقة مدى', 'Mada', '/assets/icons/payments/mada.svg'),
('stc_pay', 'STC Pay', 'الدفع عبر إس تي سي باي', 'STC Pay', '/assets/icons/payments/stcpay.svg'),
('apple_pay', 'Apple Pay', 'الدفع عبر أبل باي', 'Apple Pay', '/assets/icons/payments/applepay.svg'),
('google_pay', 'Google Pay', 'الدفع عبر جوجل باي', 'Google Pay', '/assets/icons/payments/googlepay.svg'),
('bank_transfer', 'Bank Transfer', 'تحويل بنكي مباشر', 'Bank Transfer', '/assets/icons/payments/bank.svg'),
('cod', 'Cash on Delivery', 'الدفع عند الاستلام', 'COD', '/assets/icons/payments/cod.svg'),
('cash', 'Cash', 'الدفع نقداً', 'Cash', '/assets/icons/payments/cash.svg'),
('square', 'Square', 'الدفع عبر سكوير', 'Square', '/assets/icons/payments/square.svg'),
('adyen', 'Adyen', 'بوابة أدين للدفع', 'Adyen', '/assets/icons/payments/adyen.svg'),
('klarna', 'Klarna', 'الدفع بالتقسيط عبر كلارنا', 'Klarna', '/assets/icons/payments/klarna.svg'),
('braintree', 'Braintree', 'بوابة برينتري للدفع', 'Braintree', '/assets/icons/payments/braintree.svg');

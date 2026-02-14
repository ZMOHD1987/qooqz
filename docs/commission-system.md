# 📊 نظام العمولات المحاسبي الاحترافي

## 🎯 نظرة عامة

نظام عمولات احترافي متكامل مصمم وفقاً لأفضل الممارسات المحاسبية العالمية. يدعم النظام:

- ✅ حساب العمولات (نسبة مئوية أو مبلغ ثابت)
- ✅ إصدار الفواتير الدورية
- ✅ تتبع المدفوعات
- ✅ إشعارات الخصم (Credit Notes)
- ✅ سجل تدقيق كامل (Audit Trail)
- ✅ أرصدة مالية فورية للأداء العالي
- ✅ حماية من الأخطاء المحاسبية

---

## 📁 هيكل قاعدة البيانات

### الجداول الرئيسية

```
1. commission_settings         - إعدادات العمولة
2. commission_transactions     - دفتر العمولات (Ledger)
3. commission_invoices         - رأس الفواتير
4. commission_invoice_items    - عناصر الفواتير (Snapshot)
5. commission_payments         - المدفوعات
6. commission_credit_notes     - إشعارات الخصم
7. entity_financial_balances   - الأرصدة المالية (Performance)
8. commission_audit_log        - سجل التدقيق (Immutable)
```

---

## 🏗️ الجداول بالتفصيل

### 1️⃣ جدول إعدادات العمولة
**`commission_settings`**

يحدد كيفية حساب العمولة لكل متجر أو نوع كيان.

```sql
CREATE TABLE commission_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    entity_type_id BIGINT UNSIGNED NULL,
    entity_id BIGINT UNSIGNED NULL,
    
    commission_type ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
    commission_value DECIMAL(10,4) NOT NULL,
    vat_percentage DECIMAL(5,2) DEFAULT 15.00,
    
    apply_vat_on_commission TINYINT(1) DEFAULT 1,
    min_order_amount DECIMAL(15,2) NULL,
    
    is_active TINYINT(1) DEFAULT 1,
    effective_from DATE NULL,
    effective_to DATE NULL,
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**الحقول المهمة:**
- `commission_type`: نوع العمولة (نسبة مئوية أو ثابت)
- `commission_value`: القيمة (مثال: 5.00 = 5% أو 50.00 ريال)
- `vat_percentage`: نسبة ضريبة القيمة المضافة
- `effective_from/to`: فترة صلاحية الإعداد

**مثال:**
```sql
-- عمولة 5% على جميع متاجر الإلكترونيات
INSERT INTO commission_settings (
    tenant_id, entity_type_id, commission_type, commission_value
) VALUES (1, 2, 'percentage', 5.00);

-- عمولة 100 ريال ثابت لمتجر محدد
INSERT INTO commission_settings (
    tenant_id, entity_id, commission_type, commission_value
) VALUES (1, 123, 'fixed', 100.00);
```

---

### 2️⃣ جدول دفتر العمولات
**`commission_transactions`**

الدفتر المحاسبي الرئيسي - يسجل كل معاملة عمولة.

```sql
CREATE TABLE commission_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NOT NULL,
    commission_setting_id BIGINT UNSIGNED NULL,

    transaction_type ENUM('sale','refund','adjustment') NOT NULL DEFAULT 'sale',
    parent_transaction_id BIGINT UNSIGNED NULL,

    order_date DATETIME NOT NULL,
    order_amount DECIMAL(15,2) NOT NULL,
    
    commission_type ENUM('percentage','fixed') NOT NULL,
    commission_rate DECIMAL(10,4) NULL,
    commission_amount DECIMAL(15,2) NOT NULL,
    
    vat_percentage DECIMAL(5,2) DEFAULT 15.00,
    vat_amount DECIMAL(15,2) DEFAULT 0.00,
    net_commission DECIMAL(15,2) NOT NULL,

    status ENUM('pending','invoiced','paid','cancelled') DEFAULT 'pending',
    
    is_locked TINYINT(1) DEFAULT 0,
    locked_at DATETIME NULL,
    
    notes TEXT NULL,
    cancelled_at DATETIME NULL,
    cancellation_reason VARCHAR(255) NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**الحقول المهمة:**
- `transaction_type`: نوع الحركة (بيع / استرداد / تعديل)
- `parent_transaction_id`: المعاملة الأصلية (في حالة الاسترداد)
- `is_locked`: مقفل بعد الفوترة (لا يمكن تعديله)
- `status`: الحالة (معلق / مفوتر / مدفوع / ملغى)

**دورة حياة المعاملة:**
```
pending → invoiced → paid
         ↓
     cancelled
```

**مثال - بيع:**
```sql
INSERT INTO commission_transactions (
    tenant_id, entity_id, order_id,
    transaction_type, order_date, order_amount,
    commission_type, commission_rate, commission_amount,
    vat_percentage, vat_amount, net_commission,
    status
) VALUES (
    1, 123, 5001,
    'sale', '2026-02-14 10:30:00', 1000.00,
    'percentage', 5.00, 50.00,
    15.00, 7.50, 57.50,
    'pending'
);
```

**مثال - استرداد:**
```sql
-- إنشاء حركة عكسية (بقيمة سالبة)
INSERT INTO commission_transactions (
    tenant_id, entity_id, order_id,
    transaction_type, parent_transaction_id,
    order_date, order_amount,
    commission_amount, vat_amount, net_commission,
    status
) VALUES (
    1, 123, 5002,
    'refund', 12345,
    '2026-02-15 14:00:00', -1000.00,
    -50.00, -7.50, -57.50,
    'pending'
);
```

---

### 3️⃣ جدول الفواتير
**`commission_invoices`**

رأس الفاتورة - يحتوي على الإجماليات فقط.

```sql
CREATE TABLE commission_invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,

    invoice_number VARCHAR(50) NOT NULL,
    invoice_type ENUM('monthly','quarterly','custom') DEFAULT 'monthly',
    
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,

    total_orders INT UNSIGNED DEFAULT 0,
    total_orders_amount DECIMAL(15,2) NOT NULL,
    total_commission DECIMAL(15,2) NOT NULL,
    total_vat DECIMAL(15,2) DEFAULT 0.00,
    grand_total DECIMAL(15,2) NOT NULL,
    
    amount_paid DECIMAL(15,2) DEFAULT 0.00,
    amount_remaining DECIMAL(15,2) GENERATED ALWAYS AS (grand_total - amount_paid) STORED,

    status ENUM('draft','issued','partially_paid','paid','overdue','cancelled') DEFAULT 'draft',

    issued_at DATETIME NULL,
    due_date DATE NULL,
    paid_at DATETIME NULL,
    
    is_locked TINYINT(1) DEFAULT 0,
    locked_at DATETIME NULL,
    
    payment_terms VARCHAR(255) NULL,
    notes TEXT NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY uniq_invoice_period (tenant_id, entity_id, period_start, period_end)
);
```

**الحقول المهمة:**
- `invoice_number`: رقم الفاتورة (فريد)
- `period_start/end`: فترة الفاتورة
- `amount_remaining`: **محسوب تلقائياً** = grand_total - amount_paid
- `is_locked`: مقفلة بعد السداد الكامل

**القيد الفريد:**
```sql
UNIQUE (tenant_id, entity_id, period_start, period_end)
```
**يمنع:** إنشاء فاتورتين لنفس المتجر ونفس الفترة ✅

**مثال:**
```sql
CALL sp_generate_commission_invoice(
    1,                    -- tenant_id
    123,                  -- entity_id
    '2026-02-01',        -- period_start
    '2026-02-28',        -- period_end
    'monthly',           -- invoice_type
    501,                 -- created_by (user_id)
    @invoice_id,         -- output
    @message             -- output
);

SELECT @invoice_id, @message;
```

---

### 4️⃣ جدول عناصر الفاتورة (Snapshot)
**`commission_invoice_items`**

**🔥 جدول حرج - يحفظ نسخة ثابتة من المعاملات**

```sql
CREATE TABLE commission_invoice_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id BIGINT UNSIGNED NOT NULL,
    transaction_id BIGINT UNSIGNED NOT NULL,
    
    -- Snapshot من المعاملة
    order_id BIGINT UNSIGNED NOT NULL,
    order_date DATETIME NOT NULL,
    order_amount DECIMAL(15,2) NOT NULL,
    commission_amount DECIMAL(15,2) NOT NULL,
    vat_amount DECIMAL(15,2) NOT NULL,
    net_commission DECIMAL(15,2) NOT NULL,
    
    transaction_type ENUM('sale','refund','adjustment') NOT NULL,
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY uniq_transaction_invoice (transaction_id)
);
```

**لماذا هذا الجدول؟**

❌ **الطريقة الخاطئة:**
```sql
-- ربط مباشر بالمعاملة
commission_transactions.invoice_id = 123
```
**المشكلة:** إذا تم تعديل المعاملة → تتغير الفاتورة! 😱

✅ **الطريقة الصحيحة:**
```sql
-- نسخ القيم إلى جدول منفصل (Snapshot)
INSERT INTO commission_invoice_items SELECT ...
```
**الفائدة:** الفاتورة ثابتة ولا تتأثر بأي تعديلات! 🎯

---

### 5️⃣ جدول المدفوعات
**`commission_payments`**

يسجل كل دفعة للفاتورة.

```sql
CREATE TABLE commission_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    commission_invoice_id BIGINT UNSIGNED NOT NULL,

    payment_number VARCHAR(50) NOT NULL,
    payment_reference VARCHAR(100) NULL,
    payment_method ENUM('bank_transfer','cash','cheque','credit_card','other') NULL,
    
    amount_paid DECIMAL(15,2) NOT NULL,
    payment_currency VARCHAR(3) DEFAULT 'SAR',
    
    paid_at DATETIME NOT NULL,
    
    bank_name VARCHAR(100) NULL,
    account_number VARCHAR(50) NULL,
    transaction_id VARCHAR(100) NULL,
    
    attachment_path VARCHAR(500) NULL,
    notes TEXT NULL,
    
    verified_by INT UNSIGNED NULL,
    verified_at DATETIME NULL,
    
    is_cancelled TINYINT(1) DEFAULT 0,
    cancelled_at DATETIME NULL,
    cancellation_reason TEXT NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_payment_number (tenant_id, payment_number)
);
```

**الحماية من الدفع الزائد:**
```sql
-- في Trigger
IF (v_previous_paid + NEW.amount_paid) > v_grand_total THEN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'المبلغ المدفوع يتجاوز قيمة الفاتورة';
END IF;
```

**مثال - دفعة جزئية:**
```sql
INSERT INTO commission_payments (
    tenant_id, entity_id, commission_invoice_id,
    payment_number, payment_method, amount_paid, paid_at
) VALUES (
    1, 123, 456,
    'PAY-000001', 'bank_transfer', 5000.00, NOW()
);
-- النتيجة: invoice.status = 'partially_paid'
```

**مثال - دفعة كاملة:**
```sql
INSERT INTO commission_payments (
    tenant_id, entity_id, commission_invoice_id,
    payment_number, payment_method, amount_paid, paid_at
) VALUES (
    1, 123, 456,
    'PAY-000002', 'bank_transfer', 5750.00, NOW()
);
-- النتيجة: invoice.status = 'paid', transactions.status = 'paid'
```

---

### 6️⃣ جدول إشعارات الخصم
**`commission_credit_notes`**

لمعالجة الاستردادات بعد إصدار الفاتورة.

```sql
CREATE TABLE commission_credit_notes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    credit_note_number VARCHAR(50) NOT NULL,
    
    invoice_id BIGINT UNSIGNED NOT NULL,
    related_transaction_id BIGINT UNSIGNED NOT NULL,
    
    credit_amount DECIMAL(15,2) NOT NULL,
    credit_commission DECIMAL(15,2) NOT NULL,
    credit_vat DECIMAL(15,2) NOT NULL,
    net_credit DECIMAL(15,2) NOT NULL,
    
    reason VARCHAR(255) NOT NULL,
    status ENUM('draft','issued','applied','cancelled') DEFAULT 'draft',
    
    issued_at DATETIME NULL,
    applied_at DATETIME NULL,
    
    notes TEXT NULL,
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_credit_note_number (tenant_id, credit_note_number),
    UNIQUE KEY unique_transaction_credit (related_transaction_id)
);
```

**السيناريو:**
```
1. تم إصدار فاتورة لشهر يناير
2. تم دفع الفاتورة
3. العميل يريد استرداد لطلب من يناير

❌ لا يمكن تعديل الفاتورة (مقفلة ومدفوعة)
✅ يتم إنشاء Credit Note يخصم من الفاتورة التالية
```

**مثال:**
```sql
CALL sp_create_credit_note(
    1,              -- tenant_id
    456,            -- invoice_id
    12345,          -- transaction_id
    'استرداد - عيب في المنتج',
    501,            -- created_by
    @credit_id,
    @message
);
```

**النتيجة:**
```sql
-- يتم تحديث الفاتورة تلقائياً
UPDATE commission_invoices
SET grand_total = grand_total - net_credit,
    total_commission = total_commission - credit_commission,
    total_vat = total_vat - credit_vat
WHERE id = invoice_id;
```

---

### 7️⃣ جدول الأرصدة المالية
**`entity_financial_balances`**

**🚀 جدول الأداء العالي - لتجنب الاستعلامات البطيئة**

```sql
CREATE TABLE entity_financial_balances (
    entity_id BIGINT UNSIGNED PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    
    -- إحصائيات المعاملات
    total_transactions INT UNSIGNED DEFAULT 0,
    total_sales_count INT UNSIGNED DEFAULT 0,
    total_refunds_count INT UNSIGNED DEFAULT 0,
    
    -- المبالغ التراكمية
    total_sales_amount DECIMAL(18,2) DEFAULT 0.00,
    total_refunds_amount DECIMAL(18,2) DEFAULT 0.00,
    net_sales DECIMAL(18,2) DEFAULT 0.00,
    
    -- العمولات
    total_commission DECIMAL(18,2) DEFAULT 0.00,
    total_vat DECIMAL(18,2) DEFAULT 0.00,
    total_net_commission DECIMAL(18,2) DEFAULT 0.00,
    
    -- الفواتير والمدفوعات
    total_invoiced DECIMAL(18,2) DEFAULT 0.00,
    total_paid DECIMAL(18,2) DEFAULT 0.00,
    
    -- الأرصدة
    pending_balance DECIMAL(18,2) DEFAULT 0.00,
    invoiced_balance DECIMAL(18,2) DEFAULT 0.00,
    total_balance DECIMAL(18,2) DEFAULT 0.00,
    
    -- إحصائيات إضافية
    total_invoices INT UNSIGNED DEFAULT 0,
    total_payments INT UNSIGNED DEFAULT 0,
    total_credit_notes INT UNSIGNED DEFAULT 0,
    
    last_transaction_date DATETIME NULL,
    last_invoice_date DATETIME NULL,
    last_payment_date DATETIME NULL,
    
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**لماذا هذا الجدول؟**

❌ **بدون Balances Table:**
```sql
-- استعلام بطيء جداً (يفحص ملايين الصفوف)
SELECT 
    SUM(net_commission)
FROM commission_transactions
WHERE entity_id = 123;
```

✅ **مع Balances Table:**
```sql
-- استعلام فوري (صف واحد)
SELECT 
    total_net_commission,
    pending_balance,
    total_paid
FROM entity_financial_balances
WHERE entity_id = 123;
```

**التحديث التلقائي:**
```
كل معاملة جديدة → Trigger يحدث الأرصدة
كل فاتورة جديدة → Trigger يحدث الأرصدة
كل دفعة → Trigger يحدث الأرصدة
```

---

### 8️⃣ سجل التدقيق
**`commission_audit_log`**

**🔒 سجل غير قابل للتعديل (Immutable)**

```sql
CREATE TABLE commission_audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    
    event_type ENUM(
        'transaction_created', 'transaction_updated', 'transaction_cancelled',
        'invoice_created', 'invoice_issued', 'invoice_paid', 'invoice_cancelled',
        'payment_created', 'payment_verified', 'payment_cancelled',
        'credit_note_created', 'credit_note_applied',
        'balance_updated'
    ) NOT NULL,
    
    entity_type ENUM('transaction','invoice','payment','credit_note','balance') NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    related_entity_id BIGINT UNSIGNED NULL,
    
    performed_by INT UNSIGNED NULL,
    performed_by_name VARCHAR(150) NULL,
    performed_by_role VARCHAR(50) NULL,
    
    action_description TEXT NOT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    request_id VARCHAR(100) NULL,
    
    metadata JSON NULL,
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

**الغرض:**
- 🔍 تتبع من قام بكل عملية
- 📊 مراجعة التغييرات (قبل وبعد)
- 🛡️ اجتياز التدقيق المالي
- 🚨 كشف التلاعب أو الأخطاء

**مثال - إنشاء فاتورة:**
```json
{
    "event_type": "invoice_created",
    "entity_type": "invoice",
    "entity_id": 456,
    "performed_by": 501,
    "performed_by_name": "أحمد محمد",
    "action_description": "إنشاء فاتورة عمولات - رقم: COM-000123-202602-001",
    "new_values": {
        "invoice_number": "COM-000123-202602-001",
        "period_start": "2026-02-01",
        "period_end": "2026-02-28",
        "grand_total": 10750.00,
        "status": "issued"
    },
    "ip_address": "192.168.1.100",
    "created_at": "2026-02-14 15:30:00"
}
```

**مثال - تعديل دفعة:**
```json
{
    "event_type": "payment_cancelled",
    "entity_type": "payment",
    "entity_id": 789,
    "related_entity_id": 456,
    "performed_by": 502,
    "action_description": "إلغاء الدفعة رقم: PAY-000001",
    "old_values": {
        "is_cancelled": 0,
        "amount": 5000.00
    },
    "new_values": {
        "is_cancelled": 1,
        "cancellation_reason": "خطأ في رقم الحساب",
        "cancelled_at": "2026-02-14 16:00:00"
    }
}
```

---

## 🔐 الحماية المحاسبية

### 1. منع تكرار الفواتير

```sql
UNIQUE KEY uniq_invoice_period (
    tenant_id, entity_id, period_start, period_end
)
```

**يمنع:**
```sql
-- ❌ خطأ: Duplicate entry
INSERT INTO commission_invoices (period_start, period_end) 
VALUES ('2026-02-01', '2026-02-28');
INSERT INTO commission_invoices (period_start, period_end) 
VALUES ('2026-02-01', '2026-02-28');
```

### 2. منع الدفع الزائد

```sql
CREATE TRIGGER after_commission_payment_insert
AFTER INSERT ON commission_payments
FOR EACH ROW
BEGIN
    IF (v_previous_paid + NEW.amount_paid) > v_grand_total THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'المبلغ المدفوع يتجاوز قيمة الفاتورة';
    END IF;
END;
```

**يمنع:**
```sql
-- الفاتورة: 10,000 ريال
-- مدفوع: 8,000 ريال
-- ❌ خطأ: لا يمكن دفع 5,000 إضافية (المجموع = 13,000)
INSERT INTO commission_payments (amount_paid) VALUES (5000.00);
```

### 3. قفل السجلات بعد الفوترة

```sql
CREATE TRIGGER before_commission_transaction_update
BEFORE UPDATE ON commission_transactions
FOR EACH ROW
BEGIN
    IF OLD.is_locked = 1 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'لا يمكن تعديل معاملة مقفلة محاسبياً';
    END IF;
END;
```

**يمنع:**
```sql
-- ❌ خطأ: المعاملة مقفلة (تم فوترتها)
UPDATE commission_transactions
SET commission_amount = 100.00
WHERE id = 12345 AND is_locked = 1;
```

### 4. قفل المدفوعات بعد التوثيق

```sql
CREATE TRIGGER before_payment_update_lock
BEFORE UPDATE ON commission_payments
FOR EACH ROW
BEGIN
    IF OLD.verified_at IS NOT NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'لا يمكن تعديل دفعة موثقة';
    END IF;
END;
```

### 5. حماية من Race Conditions

```sql
CREATE PROCEDURE sp_generate_commission_invoice(...)
BEGIN
    START TRANSACTION;
    
    -- 🔒 قفل المعاملات المراد فوترتها
    SELECT id FROM commission_transactions
    WHERE ... FOR UPDATE;
    
    -- إنشاء الفاتورة
    INSERT INTO commission_invoices ...
    
    COMMIT;
END;
```

**يمنع:**
```
Thread A: يقرأ معاملات يناير
Thread B: يقرأ نفس معاملات يناير
Thread A: ينشئ فاتورة
Thread B: ❌ لا يستطيع (المعاملات مقفلة)
```

### 6. حماية البيانات المالية من الحذف

```sql
-- الأرصدة
FOREIGN KEY (entity_id) REFERENCES entities(id)
ON DELETE RESTRICT;

-- المدفوعات
FOREIGN KEY (commission_invoice_id) REFERENCES commission_invoices(id)
ON DELETE RESTRICT;
```

**يمنع:**
```sql
-- ❌ خطأ: لا يمكن حذف الفاتورة (لها مدفوعات)
DELETE FROM commission_invoices WHERE id = 456;
```

---

## 📊 الإجراءات المخزنة (Stored Procedures)

### 1. إنشاء فاتورة

```sql
CALL sp_generate_commission_invoice(
    p_tenant_id INT,
    p_entity_id BIGINT,
    p_period_start DATE,
    p_period_end DATE,
    p_invoice_type ENUM,
    p_created_by INT,
    OUT p_invoice_id BIGINT,
    OUT p_message VARCHAR
);
```

**مثال:**
```sql
CALL sp_generate_commission_invoice(
    1,                    -- tenant_id
    123,                  -- entity_id
    '2026-02-01',        -- period_start
    '2026-02-28',        -- period_end
    'monthly',           -- invoice_type
    501,                 -- created_by
    @invoice_id,
    @message
);

SELECT @invoice_id as invoice_id, @message as message;
```

**ما يحدث داخلياً:**
```
1. التحقق من عدم وجود فاتورة للفترة
2. قفل المعاملات المعلقة (FOR UPDATE)
3. حساب الإجماليات
4. توليد رقم فاتورة فريد
5. إنشاء الفاتورة
6. نسخ المعاملات إلى invoice_items (Snapshot)
7. قفل المعاملات (is_locked = 1)
8. تحديث الأرصدة
9. تسجيل في Audit Log
```

### 2. إنشاء إشعار خصم

```sql
CALL sp_create_credit_note(
    p_tenant_id INT,
    p_invoice_id BIGINT,
    p_transaction_id BIGINT,
    p_reason VARCHAR,
    p_created_by INT,
    OUT p_credit_note_id BIGINT,
    OUT p_message VARCHAR
);
```

**مثال:**
```sql
CALL sp_create_credit_note(
    1,
    456,
    12345,
    'استرداد - عيب في المنتج',
    501,
    @credit_id,
    @message
);
```

**ما يحدث:**
```
1. التحقق من الفاتورة والمعاملة
2. توليد رقم Credit Note
3. إنشاء السجل
4. تخفيض قيمة الفاتورة
5. تحديث الأرصدة
6. تسجيل في Audit Log
```

---

## 📈 Views (طرق العرض)

### 1. ملخص العمولات لكل متجر

```sql
CREATE OR REPLACE VIEW v_entity_commission_summary AS
SELECT 
    e.id AS entity_id,
    e.store_name,
    COUNT(DISTINCT ct.id) AS total_transactions,
    SUM(ct.order_amount) AS net_sales,
    SUM(ct.net_commission) AS total_net_commission,
    SUM(CASE WHEN ct.status = 'pending' THEN ct.net_commission ELSE 0 END) AS pending_amount,
    SUM(CASE WHEN ct.status = 'paid' THEN ct.net_commission ELSE 0 END) AS paid_amount
FROM entities e
LEFT JOIN commission_transactions ct ON e.id = ct.entity_id
GROUP BY e.id;
```

**الاستخدام:**
```sql
SELECT * FROM v_entity_commission_summary WHERE entity_id = 123;
```

### 2. حالة الفواتير التفصيلية

```sql
SELECT 
    invoice_number,
    store_name,
    period_start,
    period_end,
    grand_total,
    amount_paid,
    amount_remaining,
    status,
    days_overdue
FROM v_invoice_detailed_status
WHERE status IN ('issued', 'partially_paid')
ORDER BY days_overdue DESC;
```

### 3. المعاملات المعلقة (جاهزة للفوترة)

```sql
SELECT 
    entity_id,
    store_name,
    COUNT(*) as pending_count,
    SUM(net_commission) as total_pending
FROM v_pending_commission_transactions
GROUP BY entity_id, store_name;
```

### 4. لوحة التحكم المالية

```sql
SELECT * FROM v_financial_dashboard
WHERE total_balance > 0
ORDER BY total_balance DESC
LIMIT 10;
```

### 5. الفواتير المتأخرة

```sql
SELECT * FROM v_overdue_invoices
WHERE days_overdue > 30;
```

### 6. سجل التدقيق

```sql
SELECT 
    event_type,
    entity_reference,
    action_description,
    performed_by_name,
    created_at
FROM v_audit_trail
WHERE entity_type = 'invoice'
AND DATE(created_at) = CURDATE()
ORDER BY created_at DESC;
```

---

## 🔄 دورة الحياة الكاملة

### السيناريو: من الطلب حتى السداد

```
1. طلب جديد → إنشاء معاملة عمولة
   ↓
   status: 'pending'
   is_locked: 0

2. نهاية الشهر → إنشاء فاتورة
   ↓
   - جمع كل المعاملات المعلقة
   - نسخها إلى invoice_items
   - تحديث status: 'invoiced'
   - تحديث is_locked: 1

3. المتجر يدفع جزء من الفاتورة
   ↓
   - إضافة payment
   - تحديث invoice.status: 'partially_paid'
   - تحديث invoice.amount_paid

4. المتجر يدفع الباقي
   ↓
   - إضافة payment آخر
   - تحديث invoice.status: 'paid'
   - تحديث transactions.status: 'paid'

5. استرداد لطلب من الشهر السابق
   ↓
   - إنشاء Credit Note
   - تخفيض الفاتورة
   - تحديث الأرصدة
```

---

## 💡 أمثلة عملية

### مثال 1: حساب عمولة طلب جديد

```sql
-- الطلب
ORDER: 1000 ريال
ENTITY: متجر الإلكترونيات (#123)
SETTING: 5% عمولة + 15% ضريبة

-- الحساب
commission = 1000 × 5% = 50 ريال
vat = 50 × 15% = 7.50 ريال
net_commission = 50 + 7.50 = 57.50 ريال

-- SQL
INSERT INTO commission_transactions (
    tenant_id, entity_id, order_id,
    order_amount, commission_amount, vat_amount, net_commission
) VALUES (
    1, 123, 5001,
    1000.00, 50.00, 7.50, 57.50
);
```

### مثال 2: فوترة شهر كامل

```sql
-- يناير 2026
-- 50 طلب
-- إجمالي المبيعات: 50,000 ريال
-- إجمالي العمولات: 2,500 ريال
-- ضريبة: 375 ريال
-- الإجمالي: 2,875 ريال

CALL sp_generate_commission_invoice(
    1, 123,
    '2026-01-01', '2026-01-31',
    'monthly', 501,
    @invoice_id, @message
);

-- النتيجة
SELECT 
    invoice_number,    -- COM-000123-202601-001
    total_orders,      -- 50
    grand_total,       -- 2,875.00
    status             -- issued
FROM commission_invoices
WHERE id = @invoice_id;
```

### مثال 3: دفع بالتقسيط

```sql
-- الفاتورة: 10,000 ريال

-- الدفعة الأولى: 6,000 ريال
INSERT INTO commission_payments (
    tenant_id, entity_id, commission_invoice_id,
    payment_number, amount_paid, paid_at
) VALUES (
    1, 123, 456,
    'PAY-000001', 6000.00, NOW()
);
-- النتيجة: status = 'partially_paid'

-- الدفعة الثانية: 4,000 ريال
INSERT INTO commission_payments (
    tenant_id, entity_id, commission_invoice_id,
    payment_number, amount_paid, paid_at
) VALUES (
    1, 123, 456,
    'PAY-000002', 4000.00, NOW()
);
-- النتيجة: status = 'paid'
```

### مثال 4: استرداد بعد الفوترة

```sql
-- السيناريو
-- فاتورة يناير تم دفعها بالكامل
-- العميل يريد استرداد طلب 500 ريال

-- إنشاء Credit Note
CALL sp_create_credit_note(
    1, 456, 12345,
    'استرداد - منتج معيب',
    501,
    @credit_id, @message
);

-- النتيجة
-- credit_amount = 500.00
-- credit_commission = 25.00
-- credit_vat = 3.75
-- net_credit = 28.75

-- يتم تخفيض الفاتورة تلقائياً
UPDATE commission_invoices
SET grand_total = grand_total - 28.75  -- من 2,875 إلى 2,846.25
WHERE id = 456;
```

---

## 🎯 أفضل الممارسات

### 1. تشغيل الفوترة بشكل دوري

```sql
-- كل نهاية شهر
CALL sp_generate_commission_invoice(
    1, entity_id,
    DATE_FORMAT(NOW() - INTERVAL 1 MONTH, '%Y-%m-01'),
    LAST_DAY(NOW() - INTERVAL 1 MONTH),
    'monthly',
    system_user_id,
    @invoice_id,
    @message
);
```

### 2. مراقبة الفواتير المتأخرة

```sql
-- يومياً
SELECT 
    entity_id,
    store_name,
    invoice_number,
    days_overdue,
    amount_remaining
FROM v_overdue_invoices
WHERE days_overdue > 7;

-- إرسال تنبيهات تلقائية
```

### 3. مراجعة Audit Log

```sql
-- أسبوعياً
SELECT 
    event_type,
    COUNT(*) as count
FROM commission_audit_log
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY event_type;
```

### 4. تحديث الأرصدة (للتحقق)

```sql
-- شهرياً - التحقق من تطابق الأرصدة
SELECT 
    efb.entity_id,
    efb.total_net_commission as balance_total,
    SUM(ct.net_commission) as transactions_total,
    efb.total_net_commission - SUM(ct.net_commission) as difference
FROM entity_financial_balances efb
JOIN commission_transactions ct ON efb.entity_id = ct.entity_id
GROUP BY efb.entity_id
HAVING ABS(difference) > 0.01;
```

### 5. أرشفة Audit Log القديمة

```sql
-- كل 6 أشهر
CALL sp_archive_old_audit_logs(180, @archived_count);
SELECT @archived_count as records_archived;
```

---

## 🔍 استعلامات مفيدة

### 1. إحصائيات شهرية

```sql
SELECT 
    DATE_FORMAT(order_date, '%Y-%m') as month,
    COUNT(*) as total_orders,
    SUM(order_amount) as total_sales,
    SUM(commission_amount) as total_commission,
    AVG(commission_amount) as avg_commission
FROM commission_transactions
WHERE entity_id = 123
AND YEAR(order_date) = 2026
GROUP BY DATE_FORMAT(order_date, '%Y-%m')
ORDER BY month;
```

### 2. أفضل 10 متاجر

```sql
SELECT 
    entity_id,
    store_name,
    total_sales_amount,
    total_net_commission,
    total_paid
FROM v_financial_dashboard
ORDER BY total_net_commission DESC
LIMIT 10;
```

### 3. المتاجر المتأخرة في الدفع

```sql
SELECT 
    e.id,
    e.store_name,
    e.phone,
    e.email,
    COUNT(ci.id) as overdue_invoices,
    SUM(ci.amount_remaining) as total_overdue
FROM entities e
JOIN commission_invoices ci ON e.id = ci.entity_id
WHERE ci.status IN ('issued', 'partially_paid')
AND ci.due_date < CURDATE()
GROUP BY e.id
ORDER BY total_overdue DESC;
```

### 4. معدل الدفع

```sql
SELECT 
    entity_id,
    store_name,
    total_invoiced,
    total_paid,
    ROUND((total_paid / NULLIF(total_invoiced, 0)) * 100, 2) as payment_rate,
    days_since_last_payment
FROM v_financial_dashboard
ORDER BY payment_rate ASC;
```

### 5. نشاط المستخدمين

```sql
SELECT 
    performed_by_name,
    event_type,
    COUNT(*) as actions,
    MAX(created_at) as last_action
FROM commission_audit_log
WHERE DATE(created_at) = CURDATE()
GROUP BY performed_by_name, event_type
ORDER BY actions DESC;
```

---

## ⚠️ التعامل مع الأخطاء

### خطأ: فاتورة مكررة

```
Error: Duplicate entry for key 'uniq_invoice_period'
الحل: الفاتورة لهذه الفترة موجودة مسبقاً
```

### خطأ: دفع زائد

```
Error: المبلغ المدفوع يتجاوز قيمة الفاتورة
الحل: راجع المبلغ المتبقي قبل الدفع
```

### خطأ: معاملة مقفلة

```
Error: لا يمكن تعديل معاملة مقفلة محاسبياً
الحل: المعاملة تم فوترتها، استخدم Credit Note بدلاً من التعديل
```

### خطأ: دفعة موثقة

```
Error: لا يمكن تعديل دفعة موثقة
الحل: الدفعات الموثقة نهائية، أنشئ دفعة جديدة أو Credit Note
```

---

## 📊 مؤشرات الأداء (KPIs)

```sql
-- Dashboard المالي الشامل
SELECT 
    -- إحصائيات عامة
    COUNT(DISTINCT entity_id) as total_stores,
    SUM(total_transactions) as total_transactions,
    
    -- المبيعات
    SUM(total_sales_amount) as total_sales,
    SUM(total_refunds_amount) as total_refunds,
    SUM(net_sales) as net_sales,
    
    -- العمولات
    SUM(total_net_commission) as total_commissions,
    
    -- الفواتير
    SUM(total_invoiced) as total_invoiced,
    SUM(total_paid) as total_paid,
    SUM(pending_balance) as pending_not_invoiced,
    SUM(invoiced_balance) as invoiced_not_paid,
    
    -- معدلات
    ROUND(AVG(total_net_commission / NULLIF(net_sales, 0)) * 100, 2) as avg_commission_rate,
    ROUND((SUM(total_paid) / NULLIF(SUM(total_invoiced), 0)) * 100, 2) as collection_rate
    
FROM entity_financial_balances
WHERE total_transactions > 0;
```

---

## 🚀 نصائح للأداء

### 1. استخدام Indexes بشكل صحيح

```sql
-- للبحث المتكرر
CREATE INDEX idx_pending_transactions 
ON commission_transactions(entity_id, status, order_date)
WHERE status = 'pending' AND is_locked = 0;

-- للتقارير
CREATE INDEX idx_transactions_by_month 
ON commission_transactions(entity_id, order_date, status);
```

### 2. الاعتماد على Balances Table

```sql
-- ❌ بطيء
SELECT SUM(net_commission)
FROM commission_transactions
WHERE entity_id = 123;

-- ✅ سريع
SELECT total_net_commission
FROM entity_financial_balances
WHERE entity_id = 123;
```

### 3. استخدام Views للاستعلامات المعقدة

```sql
-- بدلاً من JOIN متعدد في كل مرة
SELECT * FROM v_financial_dashboard
WHERE entity_id = 123;
```

---

## 📝 الخلاصة

هذا النظام يوفر:

✅ **محاسبة دقيقة**: Snapshot Ledger + Audit Trail  
✅ **أداء عالي**: Balance Table للاستعلامات الفورية  
✅ **حماية كاملة**: Triggers تمنع الأخطاء المحاسبية  
✅ **قابلية التوسع**: يتحمل ملايين المعاملات  
✅ **التدقيق**: سجل كامل لكل عملية  
✅ **المرونة**: يدعم جميع سيناريوهات العمولات  

---

## 📞 الدعم

للأسئلة أو المشاكل:
- راجع Views للتقارير السريعة
- راجع Audit Log لتتبع الأخطاء
- تأكد من تحديث Balances Table دورياً

---

**تم إنشاء هذا النظام وفقاً لأفضل الممارسات المحاسبية العالمية**  
**آخر تحديث: فبراير 2026**

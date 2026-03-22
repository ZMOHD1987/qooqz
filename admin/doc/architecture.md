# المخطط الهندسي الكامل لمشروع Qooqz

> **تاريخ الإصدار:** 2026-03-21  
> **النسخة:** 1.0  
> **الوصف:** مخطط معماري شامل يغطي بنية المشروع، العزل، التوسع، والأمان.

---

## 1. نظرة عامة على المشروع

**Qooqz** منصة SaaS متعددة المستأجرين (Multi-Tenant) مبنية بـ PHP تعمل كسوق إلكتروني شامل يدعم:
- إدارة المتاجر والبائعين (Vendors/Tenants)
- الطلبات، المنتجات، الشحن، المدفوعات
- إدارة الإعلانات، الاشتراكات، والعمولات
- نظام AI مدمج (RAG Chatbot + Vision)
- بوابات admin/frontend/API مستقلة

---

## 2. مخطط بنية الطبقات (Layer Architecture)

```
┌──────────────────────────────────────────────────────────────────┐
│                        CLIENTS (المستخدمون)                       │
│  ┌─────────────┐  ┌─────────────┐  ┌──────────────────────────┐  │
│  │  Web Admin  │  │  Frontend   │  │  Mobile / External API   │  │
│  │  (PHP/HTML) │  │  (PHP/HTML) │  │  (REST JSON)             │  │
│  └──────┬──────┘  └──────┬──────┘  └───────────┬──────────────┘  │
└─────────┼────────────────┼────────────────────┼──────────────────┘
          │                │                    │
┌─────────▼────────────────▼────────────────────▼──────────────────┐
│                      WEB SERVER (Nginx / Apache)                   │
│              TLS Termination · Static Files · Routing              │
└──────────────────────────────┬───────────────────────────────────┘
                               │
         ┌─────────────────────┼──────────────────────┐
         │                     │                      │
┌────────▼──────┐   ┌──────────▼──────────┐  ┌───────▼──────────┐
│  Admin Panel  │   │   REST API (PHP)     │  │  AI Engine (Py)  │
│  /admin/*     │   │   /api/v1/*          │  │  FastAPI :8000   │
│  PHP 8.x      │   │   PHP 8.x            │  │  Python 3.x      │
└────────┬──────┘   └──────────┬──────────┘  └───────┬──────────┘
         │                     │                      │
         └─────────────────────┼──────────────────────┘
                               │
┌──────────────────────────────▼───────────────────────────────────┐
│                       SHARED CORE / MIDDLEWARE                     │
│  Auth · RBAC · CSRF · Rate-Limit · Tenant-Scope · Cache · Queue   │
└──────────────────────────────┬───────────────────────────────────┘
                               │
         ┌─────────────────────┼──────────────────────┐
         │                     │                      │
┌────────▼──────┐   ┌──────────▼──────────┐  ┌───────▼──────────┐
│  MySQL / PDO  │   │  File Storage        │  │  Queue / Cache   │
│  (Main DB)    │   │  /uploads            │  │  (PHP Queue Mgr) │
└───────────────┘   └─────────────────────┘  └──────────────────┘
```

---

## 3. هيكل المجلدات الكامل

```
qooqz/
├── admin/                          ← لوحة تحكم الإدارة
│   ├── assets/
│   │   ├── css/pages/              ← ملفات CSS لكل صفحة
│   │   ├── js/pages/               ← ملفات JS لكل صفحة
│   │   ├── images/ · img/
│   │   └── templates/
│   ├── fragments/                  ← مكونات HTML قابلة للتضمين (50+ module)
│   ├── includes/                   ← Bootstrap، Auth checks
│   └── pages/                      ← صفحات PHP (admin_ai.php)
│
├── api/                            ← REST API بـ PHP
│   ├── v1/
│   │   ├── auth/                   ← تسجيل دخول، JWT، Controllers
│   │   ├── routes/                 ← 100+ ملف route (كل موديول منفصل)
│   │   └── models/                 ← 50+ نموذج بياناتي
│   │       ├── {module}/
│   │       │   ├── controllers/
│   │       │   ├── repositories/   ← PDO queries
│   │       │   ├── services/
│   │       │   └── validators/
│   ├── shared/
│   │   ├── core/                   ← BaseModel، DB، Cache، Queue، Logger
│   │   ├── security/               ← AuthGuard، Persistence
│   │   ├── middleware/             ← Auth، RateLimit، CSRF، Tenant، Security
│   │   ├── services/               ← Order، Payment، Shipping، Tax، RBAC
│   │   ├── infrastructure/
│   │   ├── controllers/
│   │   ├── domain/
│   │   └── helpers/
│   ├── middleware/                 ← Timezone، role.php، tenant.php
│   ├── services/                   ← HomeService، OrderService، PaymentService
│   ├── repositories/
│   ├── storage/sessions/
│   ├── logs/
│   └── tests/
│
├── ai-engine/                      ← Python FastAPI — نظام الذكاء الاصطناعي
│   ├── app/
│   │   ├── main.py                 ← Entry point FastAPI
│   │   ├── api/v1/router.py       ← API Router
│   │   ├── services/               ← chat، rag، embedding، memory، vision، file
│   │   ├── models/                 ← DB ORM models
│   │   ├── repositories/
│   │   ├── schemas/                ← Pydantic schemas
│   │   ├── core/                   ← logging_config
│   │   ├── db/                     ← Connection pool
│   │   ├── background/             ← Async tasks
│   │   └── utils/
│   ├── alembic/                    ← DB migrations for AI
│   ├── tests/
│   ├── uploads/ · logs/ · tmp/
│
├── frontend/                       ← الواجهة الأمامية
│   ├── pages/                      ← admin_ai.php، test_api.php
│   ├── templates/
│   ├── partials/
│   ├── assets/css/ · js/
│   ├── includes/
│   ├── languages/
│   ├── config/
│   └── public/                     ← ملفات عامة (CSS/JS compiled)
│
├── languages/                      ← ملفات الترجمة (عربي + إنجليزي)
│   ├── Addresses · Ads · Banners · Brands · Categories
│   ├── Certificates* · Commissions · Delivery* · Discounts
│   ├── Entities · FlashSales · Jobs* · Media_studio · POS
│   ├── Permissions · Product · Returns · SeoMeta · Settings
│   ├── Subscriptions · TenantUsers · Users · Queues
│   ├── tickets/ (Contracts, controllers, repositories, services, validators)
│   ├── escrow/ · ticket_categories/ · role_permissions/
│   ├── common/ · admin/ · frontend/
│
├── database/
│   └── migrations/                 ← 14 ملف SQL لـ schema changes
│
├── uploads/                        ← ملفات المرفوعة (صور المنتجات، المستخدمين)
│   ├── images/products/
│   ├── images/users/
│   └── add_visitor_interactions/
│
└── doc/                            ← التوثيق
    ├── ad.md · escrow.md · themes.md · jobs*.md · Tickets.md
    ├── image_types.md · security_comprehensive_test.md
    └── architecture.md  ← (هذا الملف)
```

---

## 4. الموديولات الوظيفية (Business Modules)

### 4.1 إدارة المستأجرين (Multi-Tenancy)
| الملف | الوصف |
|---|---|
| `api/v1/routes/tenants.php` | CRUD المستأجرين |
| `api/middleware/tenant.php` | عزل البيانات بـ `tenant_id` |
| `admin/fragments/tenant.php` | واجهة الإدارة |

### 4.2 المنتجات والكتالوج
- المنتجات، الأصناف، الخصائص، القيم، الترجمات
- الباقات (Bundles)، المقارنة، المراجعات، الأسئلة
- تسعير، مخزون، تنبيهات المخزون، حركات المخزون

### 4.3 الطلبات والمبيعات
- الطلبات، عناصر الطلب، حالات الطلب
- السلة، الكوبونات، الخصومات، الفلاش سيل
- الإرجاعات، عناصر الإرجاع، حالات الإرجاع

### 4.4 الشحن والتوصيل
- مناطق التوصيل، مزودي التوصيل، طلبات التوصيل
- السائق المستقل (IndependentDriver)
- الشحن، ضرائب الدول

### 4.5 المدفوعات والمالية
- طرق الدفع، المدفوعات، الضرائب
- العمولات (transactions, invoices, invoice_items, payments, credit_notes)
- الاشتراكات، خطط الاشتراك، فواتير الاشتراك
- المحفظة (Wallet)

### 4.6 الكيانات والموردون (Entities / Vendors)
- الكيانات، أنواع الكيانات، فئات الكيانات
- ساعات العمل، مدفوعات الكيانات

### 4.7 الإعلانات (Ads Module)
- حملات الإعلانات، وحدات الإعلانات، ترجمات الإعلانات
- مواضع الإعلانات، عناصر المواضع، مدفوعات الإعلانات
- **6 API endpoints:** `/api/ads`, `/api/ad_campaigns`, `/api/ad_translations`, `/api/ad_placements`, `/api/ad_placement_items`, `/api/ad_payments`

### 4.8 نظام الضمان (Escrow Module)
- المعاملات، سجل الحالات، النزاعات، أدلة النزاعات، دفتر الأستاذ
- **5 كيانات:** EscrowTransactions, EscrowStatusHistory, EscrowDisputes, EscrowDisputeEvidence, EscrowLedger

### 4.9 الشهادات (Certificates)
- الشهادات، الإصدارات، الملاحظات، المنتجات، الطلبات، الدفعات
- القوالب، تخصيص الاستلام، قواعد الرسوم، السجلات

### 4.10 المزادات (Auctions)
- المزادات، العطاءات، المراقبون، سجل النشاط
- إعدادات المزايدة التلقائية، ترجمات المزادات

### 4.11 الوظائف والتوظيف (Jobs)
- فئات الوظائف، الوظائف، المهارات، المقابلات
- طلبات التوظيف، أسئلة وأجوبة الطلبات

### 4.12 التذاكر والدعم (Tickets / Support)
- التذاكر، فئات التذاكر، رسائل التذاكر، سجل الحالات
- العقود، الخدمات، مراجعات الطلبات

### 4.13 الإشعارات
- أنواع الإشعارات، قنوات التسليم، العدادات، تاريخ التسليم

### 4.14 استوديو الوسائط (Media Studio)
- أنواع الصور مع icon/color
- رفع الملفات، إدارة الصور

### 4.15 نظام الـ POS
- جلسات الـ POS، أنماط البطاقات

### 4.16 المظاهر والتصميم (Themes)
- القوالب، ألوان الزر، أنماط البطاقات، إعدادات الخط
- إعدادات التصميم، الأقسام الرئيسية، ترجمات المظاهر

### 4.17 إدارة المستخدمين والصلاحيات
- المستخدمون، الأدوار، الصلاحيات، صلاحيات الموارد
- مستخدمو المستأجر، حسابات المستخدمين

### 4.18 التصنيفات والكتالوج
- الفئات، الفئات للمستأجرين، خصائص الفئات
- الماركات، أنواع المنتجات، الوحدات

### 4.19 التسويق
- البانرات، الكوبونات، الخصومات، الفلاش سيل
- الـ SEO Meta، تحسين محركات البحث

### 4.20 الذكاء الاصطناعي (AI Engine)
- **RAG (Retrieval Augmented Generation):** بحث دلالي + توليد إجابات
- **Memory Service:** ذاكرة محادثة مستمرة
- **Vision Service:** تحليل الصور
- **Embedding Service:** تحويل النصوص لمتجهات
- **File Service:** رفع وفهرسة الملفات

---

## 5. مخطط تدفق الطلب (Request Flow)

```
HTTP Request
    │
    ▼
Web Server (Nginx)
    │
    ├─── /admin/*  ──────► Admin PHP Panel
    │                           │
    │                           ▼
    │                      fragments/*.php
    │                      API calls → /api/v1/*
    │
    ├─── /api/v1/* ──────► Kernel::dispatch()
    │                           │
    │                      MiddlewarePipeline:
    │                      1. Security Headers
    │                      2. Rate Limit
    │                      3. CSRF Check
    │                      4. Auth (JWT/Session)
    │                      5. Tenant Scope
    │                      6. Permission Check (RBAC)
    │                           │
    │                      Route → Controller
    │                           │
    │                      Service Layer
    │                           │
    │                      Repository (PDO)
    │                           │
    │                      MySQL Database
    │                           │
    │                      ResponseFormatter → JSON
    │
    └─── /ai/* ──────────► FastAPI (Python :8000)
                                │
                           Auth Middleware
                                │
                           Service (chat/rag/vision)
                                │
                           Vector DB + MySQL
```

---

## 6. نموذج العزل (Multi-Tenant Isolation)

```
┌─────────────────────────────────────────────────────┐
│                    Shared Database                   │
│                                                      │
│  ┌─────────────────┐   ┌─────────────────────────┐  │
│  │  Tenant A Data  │   │    Tenant B Data         │  │
│  │  tenant_id = 1  │   │    tenant_id = 2         │  │
│  └─────────────────┘   └─────────────────────────┘  │
│                                                      │
│   كل جدول يحتوي على tenant_id                       │
│   Middleware يحقن tenant_id تلقائياً في كل query    │
└─────────────────────────────────────────────────────┘

آلية العزل:
1. api/middleware/tenant.php → يستخرج tenant_id من JWT
2. BaseModel.php → يضيف WHERE tenant_id = :tid لكل query
3. Repository PDO → Prepared Statements تمنع SQL Injection
```

---

## 7. نموذج الأمان (Security Architecture)

### 7.1 طبقات الحماية
```
Layer 1: Network
├── HTTPS/TLS (web server)
├── Security Headers (X-Frame-Options, HSTS, CSP)
└── Rate Limiting (api/middleware/rate_limit.php)

Layer 2: Authentication
├── JWT Tokens (auth/login.php)
├── Session Management (api/storage/sessions)
└── CSRF Tokens (ملفات fragment PHP)

Layer 3: Authorization
├── RBAC (roles + permissions)
├── Resource Permissions (resource_permissions)
└── Tenant Scoping (middleware/tenant.php)

Layer 4: Data
├── PDO Prepared Statements (منع SQL Injection)
├── Input Validation (validators/ في كل موديول)
└── Output Encoding (منع XSS)
```

### 7.2 ملفات الأمان الأساسية
| الملف | الوظيفة |
|---|---|
| `api/shared/security/AuthGuard.php` | التحقق من صحة الرمز |
| `api/middleware/auth.php` | حماية المسارات |
| `api/middleware/rate_limit.php` | تحديد معدل الطلبات |
| `api/middleware/security_headers.php` | ترويسات الأمان |
| `api/middleware/validator.php` | التحقق من المدخلات |
| `api/shared/core/CryptoConfig.php` | إعدادات التشفير |
| `api/v1/auth/check_session_api.php` | التحقق من الجلسة |

---

## 8. مخطط قاعدة البيانات (Database Architecture)

### 8.1 المجموعات الكبرى للجداول

```
┌─────────────────────────────────────────────────────────────────┐
│                    CORE ENTITIES                                 │
│  tenants · users · roles · permissions · resource_permissions   │
│  languages · currencies · countries · cities · timezones        │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                    CATALOG                                       │
│  categories · brands · products · product_variants              │
│  product_attributes · product_translations · product_pricing     │
│  product_bundles · units · attribute_types                       │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                    COMMERCE                                      │
│  carts · cart_items · orders · order_items · order_status_history│
│  payments · payment_methods · coupons · discounts · flash_sales  │
│  returns · return_items · stock_movements                        │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                    LOGISTICS                                     │
│  delivery_zones · delivery_providers · delivery_orders           │
│  shipping · country_taxes · addresses · provider_zones           │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                    FINANCIAL                                     │
│  subscriptions · subscription_plans · subscription_invoices      │
│  commissions (transactions, invoices, payments, credit_notes)    │
│  wallet · ad_payments · escrow_ledger                            │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                    ADVERTISING                                   │
│  ads · ad_campaigns · ad_translations · ad_placements            │
│  ad_placement_items · ad_payments                                │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                    ESCROW                                        │
│  escrow_transactions · escrow_status_history                     │
│  escrow_disputes · escrow_dispute_evidence · escrow_ledger       │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                    CERTIFICATES                                  │
│  certificates · certificate_editions · certificates_issued       │
│  certificates_products · certificates_requests · certificates_logs│
│  certificates_payments · certificates_fee_rules · templates       │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                    JOBS                                          │
│  jobs · job_categories · job_skills · job_interviews             │
│  job_applications · job_application_questions · job_answers      │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                    SUPPORT / TICKETS                             │
│  support_tickets · ticket_categories · ticket_messages           │
│  ticket_status_history · notifications · notification_types      │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                    THEMING / UI                                  │
│  themes · button_styles · card_styles · font_settings            │
│  color_settings · design_settings · homepage_sections            │
│  banners · seo_meta · image_types                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## 9. مخطط الـ API (API Architecture)

### 9.1 نمط كل Endpoint
```
GET    /api/v1/{resource}         → all()     → قائمة مع pagination + tenant filter
GET    /api/v1/{resource}/{id}    → find()    → سجل واحد
POST   /api/v1/{resource}         → create()  → إنشاء
PUT    /api/v1/{resource}/{id}    → update()  → تعديل
DELETE /api/v1/{resource}/{id}    → delete()  → حذف
```

### 9.2 الـ Endpoints المتخصصة (أمثلة)
```
POST /api/v1/auth/login           → تسجيل دخول + JWT
POST /api/v1/orders/{id}/status   → تغيير حالة الطلب
POST /api/v1/payments/process     → معالجة الدفع
POST /api/v1/subscriptions/renew  → تجديد الاشتراك
GET  /api/v1/public/products      → المنتجات العامة
GET  /api/v1/mobile/*             → Mobile endpoints
POST /api/v1/media                → رفع الملفات
GET  /api/v1/verify_certificate   → التحقق من الشهادة
```

### 9.3 AI Engine Endpoints
```
POST /api/v1/chat                 → محادثة AI مع RAG
POST /api/v1/upload               → رفع ملف للفهرسة
GET  /api/v1/health               → فحص الصحة
```

---

## 10. الـ Middleware Pipeline

```
Request
   │
   ▼
[1] security_headers.php     → X-Frame-Options, HSTS, CSP, X-XSS-Protection
   │
   ▼
[2] rate_limit.php            → حد 100 طلب/دقيقة لكل IP
   │
   ▼
[3] CSRF validator            → للعمليات التعديلية (POST/PUT/DELETE)
   │
   ▼
[4] auth.php                  → التحقق من JWT / Session
   │
   ▼
[5] tenant.php                → استخراج وحقن tenant_id
   │
   ▼
[6] TimezoneMiddleware.php    → ضبط المنطقة الزمنية
   │
   ▼
[7] role.php / validator.php  → RBAC + Input Validation
   │
   ▼
[8] Route Controller
```

---

## 11. نظام الـ AI Engine (Python FastAPI)

```
FastAPI Application (port 8000)
│
├── /api/v1/chat              ← RAGService + ChatService + MemoryService
├── /api/v1/upload            ← FileService + EmbeddingService
├── /api/v1/vision            ← VisionService (تحليل صور)
├── /api/v1/health            ← Health check
│
Services:
├── ChatService               ← توليد الردود (LLM)
├── RAGService                ← Retrieval + Generation
├── EmbeddingService          ← Text→Vector (embedding models)
├── MemoryService             ← حفظ سياق المحادثة في DB
├── VisionService             ← تحليل الصور بـ Vision LLM
├── FileService               ← فهرسة الملفات (PDF, DOCX...)
└── UsageService              ← إحصائيات الاستخدام
│
Database: MySQL (via connection pool) + Alembic migrations
Background Tasks: async processing
```

---

## 12. نظام اللغات والترجمة

### الوحدات المدعومة (35+ وحدة)
كل وحدة تحتوي على: `en.json` و `ar.json` كحد أدنى

| الوحدة | الملف |
|---|---|
| Addresses, Ads, Banners, Brands | languages/{Module}/{lang}.json |
| Categories, Certificates*, Commissions | |
| Delivery, Discounts, Entities, FlashSales | |
| Jobs, Media_studio, POS, Permissions | |
| Product, Returns, SeoMeta, Settings | |
| Subscriptions, TenantUsers, Users | |
| Escrow, Tickets, ticket_categories | |
| AdminUiTheme, common, frontend | |

---

## 13. مخطط التوسع (Scalability Architecture)

### 13.1 الوضع الحالي (Monolith Single-Server)
```
┌──────────────────────────────┐
│         Single Server        │
│  Web Server + PHP + MySQL    │
│  + Python AI Engine          │
└──────────────────────────────┘
```

### 13.2 النموذج المقترح للتوسع (1M+ مستخدم)
```
                          ┌─────────────────┐
                          │   CDN (CloudFront│
                          │   /Cloudflare)   │
                          └────────┬────────┘
                                   │
                          ┌────────▼────────┐
                          │  Load Balancer   │
                          │  (Nginx/HAProxy) │
                          └────────┬────────┘
                         ┌─────────┼─────────┐
                         │         │         │
                  ┌──────▼──┐ ┌────▼───┐ ┌──▼──────┐
                  │ API Pod │ │API Pod │ │API Pod  │
                  │ (PHP-FPM│ │(PHP-FPM│ │(PHP-FPM │
                  └──────┬──┘ └────┬───┘ └──┬──────┘
                         └────────┼─────────┘
                                  │
                   ┌──────────────┼──────────────┐
                   │              │              │
           ┌───────▼──┐   ┌───────▼──┐   ┌──────▼──────┐
           │MySQL     │   │Redis     │   │Elasticsearch │
           │Primary   │   │Cache +   │   │(Search/Logs) │
           │+ Replicas│   │Sessions  │   └─────────────┘
           └──────────┘   └──────────┘
                   │
           ┌───────▼──────────────────┐
           │  AI Engine Cluster       │
           │  (Python FastAPI pods)   │
           │  + Vector DB (pgvector/  │
           │    Qdrant/Weaviate)       │
           └──────────────────────────┘
                   │
           ┌───────▼──────────────────┐
           │  Object Storage (S3/     │
           │  MinIO) for uploads      │
           └──────────────────────────┘
```

---

## 14. تقييم جاهزية التوسع لمليون مستخدم

### 14.1 نقاط القوة الحالية ✅

| المعيار | الوضع |
|---|---|
| **Multi-Tenancy** | مدمج عبر tenant_id في كل جدول |
| **عزل البيانات** | Middleware يحقن tenant_id تلقائياً |
| **Prepared Statements** | حماية من SQL Injection في كل Repository |
| **نظام Queue** | QueueManager.php موجود |
| **نظام Cache** | CacheManager.php موجود |
| **نظام Logging** | Logger.php + ملفات logs/ |
| **Rate Limiting** | rate_limit.php موجود |
| **RBAC متقدم** | roles + permissions + resource_permissions |
| **API موحد** | JSON REST API منظم |
| **AI مدمج** | FastAPI Python service مستقل |
| **توثيق كامل** | doc/ + README لكل وحدة |

### 14.2 نقاط تحتاج تحسين للتوسع الكبير ⚠️

| المشكلة | الحل المقترح |
|---|---|
| **قاعدة بيانات واحدة** | MySQL Read Replicas + Connection Pooling (PgBouncer) |
| **Sessions محلية** | نقل Sessions إلى Redis |
| **ملفات محلية** | نقل uploads إلى S3/MinIO |
| **Cache في PHP** | Redis Cluster بدلاً من in-process cache |
| **AI Engine مثيل واحد** | Auto-scaling pods (Kubernetes) |
| **لا يوجد Message Queue** | RabbitMQ/Kafka للعمليات الثقيلة |
| **تحميل ثابت للـ Config** | نقل إلى Config Service / env vars |
| **لا يوجد API Gateway** | Kong/Traefik للـ rate limiting الموزع |
| **Database Schema هيكل واحد** | Database Sharding عند النمو الكبير |

### 14.3 تقدير الطاقة الاستيعابية الحالية

| المستوى | عدد المستخدمين المتزامنين | المتطلبات |
|---|---|---|
| **الوضع الحالي** | ~500-1,000 | خادم واحد 8GB RAM |
| **توسع بسيط** | ~10,000 | + Redis + Read Replica |
| **توسع متوسط** | ~100,000 | Load Balancer + 3 API Pods + Redis Cluster |
| **توسع كبير** | ~1,000,000+ | Kubernetes + Sharding + CDN + Kafka |

---

## 15. مخطط التواصل بين الخدمات (Service Communication)

```
Admin Panel (PHP)
    │ HTTP/AJAX
    ▼
REST API (/api/v1)           ←→  MySQL (PDO)
    │                        ←→  Cache (CacheManager)
    │                        ←→  Queue (QueueManager)
    │
    ├── HTTP → AI Engine (FastAPI :8000)
    │              │
    │              ├── MySQL (memories, embeddings)
    │              └── External LLM API (OpenAI/etc.)
    │
    └── Storage (uploads/ → local filesystem)

Frontend (PHP)
    │ HTTP/AJAX
    ▼
REST API (/api/v1/public/*)
```

---

## 16. ملخص تنفيذي

### الهوية المعمارية
**Qooqz** منصة **SaaS Monolith متقدمة** مع فصل واضح بين الطبقات:
- **Presentation:** Admin PHP Panel + Frontend PHP Pages
- **API:** REST JSON (PHP 8.x) + مسارات منفصلة لكل وحدة
- **Business Logic:** Service Layer (PaymentService, OrderService, etc.)
- **Data Access:** Repository Pattern مع PDO
- **AI:** خدمة Python مستقلة (FastAPI)
- **Storage:** MySQL + Local Filesystem

### نقاط التميز
1. **عزل المستأجرين** بدون قواعد بيانات منفصلة (فعال للتكلفة)
2. **100+ Route** منظم في ملفات منفصلة لسهولة الصيانة
3. **نظام RBAC متقدم** مع صلاحيات الموارد
4. **AI Engine مدمج** (RAG + Memory + Vision) نادر في المنصات المماثلة
5. **توثيق وافر** في مجلد `doc/`

### التوصية
المشروع **جاهز للإنتاج بشكل جيد** في مرحلة النمو الأولى (حتى 50K مستخدم).  
للوصول إلى **مليون مستخدم**، يجب تطبيق:
1. Redis للـ Cache والـ Sessions
2. MySQL Read Replicas
3. Object Storage (S3) للملفات
4. Load Balancer مع عدة Pod للـ API
5. Message Queue للعمليات الثقيلة

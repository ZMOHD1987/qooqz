<?php
// htdocs/api/shared/config/constants.php
// Returns array for ConfigLoader compatibility

return [
    // User Status
    'USER_STATUS_PENDING' => 'pending',
    'USER_STATUS_ACTIVE' => 'active',
    'USER_STATUS_INACTIVE' => 'inactive',
    'USER_STATUS_SUSPENDED' => 'suspended',
    'USER_STATUS_BANNED' => 'banned',
    'USER_STATUS_DELETED' => 'deleted',

    // User Types
    'USER_TYPE_CUSTOMER' => 'customer',
    'USER_TYPE_VENDOR' => 'vendor',
    'USER_TYPE_ADMIN' => 'admin',
    'USER_TYPE_SUPER_ADMIN' => 'super_admin',
    'USER_TYPE_SUPPORT' => 'support',
    'USER_TYPE_MODERATOR' => 'moderator',
    'USER_TYPE_DRIVER' => 'driver',
    'USER_TYPE_SUPPLIER' => 'supplier',

    // Vendor Status
    'VENDOR_STATUS_PENDING' => 'pending',
    'VENDOR_STATUS_ACTIVE' => 'active',
    'VENDOR_STATUS_SUSPENDED' => 'suspended',
    'VENDOR_STATUS_REJECTED' => 'rejected',
    'VENDOR_STATUS_INACTIVE' => 'inactive',

    // Vendor Types
    'VENDOR_TYPE_PRODUCT_SELLER' => 'product_seller',
    'VENDOR_TYPE_SERVICE_PROVIDER' => 'service_provider',
    'VENDOR_TYPE_BOTH' => 'both',

    // Business Types
    'BUSINESS_TYPE_INDIVIDUAL' => 'individual',
    'BUSINESS_TYPE_COMPANY' => 'company',

    // Order Status
    'ORDER_STATUS_PENDING' => 'pending',
    'ORDER_STATUS_CONFIRMED' => 'confirmed',
    'ORDER_STATUS_PROCESSING' => 'processing',
    'ORDER_STATUS_PACKED' => 'packed',
    'ORDER_STATUS_SHIPPED' => 'shipped',
    'ORDER_STATUS_OUT_FOR_DELIVERY' => 'out_for_delivery',
    'ORDER_STATUS_DELIVERED' => 'delivered',
    'ORDER_STATUS_COMPLETED' => 'completed',
    'ORDER_STATUS_CANCELLED' => 'cancelled',
    'ORDER_STATUS_REFUNDED' => 'refunded',
    'ORDER_STATUS_FAILED' => 'failed',

    // Payment Status
    'PAYMENT_STATUS_PENDING' => 'pending',
    'PAYMENT_STATUS_PROCESSING' => 'processing',
    'PAYMENT_STATUS_PAID' => 'paid',
    'PAYMENT_STATUS_FAILED' => 'failed',
    'PAYMENT_STATUS_REFUNDED' => 'refunded',
    'PAYMENT_STATUS_PARTIALLY_REFUNDED' => 'partially_refunded',
    'PAYMENT_STATUS_CANCELLED' => 'cancelled',

    // Payment Methods
    'PAYMENT_METHOD_CREDIT_CARD' => 'credit_card',
    'PAYMENT_METHOD_MADA' => 'mada',
    'PAYMENT_METHOD_APPLE_PAY' => 'apple_pay',
    'PAYMENT_METHOD_STC_PAY' => 'stcpay',
    'PAYMENT_METHOD_CASH_ON_DELIVERY' => 'cash_on_delivery',
    'PAYMENT_METHOD_BANK_TRANSFER' => 'bank_transfer',
    'PAYMENT_METHOD_WALLET' => 'wallet',

    // Shipment Status
    'SHIPMENT_STATUS_PENDING' => 'pending',
    'SHIPMENT_STATUS_PICKED_UP' => 'picked_up',
    'SHIPMENT_STATUS_IN_TRANSIT' => 'in_transit',
    'SHIPMENT_STATUS_OUT_FOR_DELIVERY' => 'out_for_delivery',
    'SHIPMENT_STATUS_DELIVERED' => 'delivered',
    'SHIPMENT_STATUS_FAILED' => 'failed',
    'SHIPMENT_STATUS_RETURNED' => 'returned',

    // Product Types
    'PRODUCT_TYPE_SIMPLE' => 'simple',
    'PRODUCT_TYPE_VARIABLE' => 'variable',
    'PRODUCT_TYPE_DIGITAL' => 'digital',
    'PRODUCT_TYPE_BUNDLE' => 'bundle',

    // Stock Status
    'STOCK_STATUS_IN_STOCK' => 'in_stock',
    'STOCK_STATUS_OUT_OF_STOCK' => 'out_of_stock',
    'STOCK_STATUS_ON_BACKORDER' => 'on_backorder',

    // Discount Types
    'DISCOUNT_TYPE_PERCENTAGE' => 'percentage',
    'DISCOUNT_TYPE_FIXED' => 'fixed',

    // Coupon Status
    'COUPON_STATUS_ACTIVE' => 'active',
    'COUPON_STATUS_INACTIVE' => 'inactive',
    'COUPON_STATUS_EXPIRED' => 'expired',
    'COUPON_STATUS_USED_UP' => 'used_up',

    // Return Status
    'RETURN_STATUS_PENDING' => 'pending',
    'RETURN_STATUS_APPROVED' => 'approved',
    'RETURN_STATUS_REJECTED' => 'rejected',
    'RETURN_STATUS_RECEIVED' => 'received',
    'RETURN_STATUS_COMPLETED' => 'completed',
    'RETURN_STATUS_CANCELLED' => 'cancelled',

    // Return Reasons
    'RETURN_REASON_DEFECTIVE' => 'defective',
    'RETURN_REASON_WRONG_ITEM' => 'wrong_item',
    'RETURN_REASON_NOT_AS_DESCRIBED' => 'not_as_described',
    'RETURN_REASON_DAMAGED' => 'damaged',
    'RETURN_REASON_CHANGED_MIND' => 'changed_mind',
    'RETURN_REASON_SIZE_ISSUE' => 'size_issue',
    'RETURN_REASON_QUALITY_ISSUE' => 'quality_issue',
    'RETURN_REASON_OTHER' => 'other',

    // Refund Methods
    'REFUND_METHOD_ORIGINAL_PAYMENT' => 'original_payment',
    'REFUND_METHOD_WALLET' => 'wallet',
    'REFUND_METHOD_BANK_TRANSFER' => 'bank_transfer',

    // Address Types
    'ADDRESS_TYPE_SHIPPING' => 'shipping',
    'ADDRESS_TYPE_BILLING' => 'billing',
    'ADDRESS_TYPE_BOTH' => 'both',

    // Notification Types
    'NOTIFICATION_TYPE_ORDER' => 'order',
    'NOTIFICATION_TYPE_PAYMENT' => 'payment',
    'NOTIFICATION_TYPE_SHIPMENT' => 'shipment',
    'NOTIFICATION_TYPE_RETURN' => 'return',
    'NOTIFICATION_TYPE_REVIEW' => 'review',
    'NOTIFICATION_TYPE_PROMOTION' => 'promotion',
    'NOTIFICATION_TYPE_SYSTEM' => 'system',
    'NOTIFICATION_TYPE_ACCOUNT' => 'account',
    'NOTIFICATION_TYPE_SUPPORT' => 'support',

    // Ticket Status
    'TICKET_STATUS_OPEN' => 'open',
    'TICKET_STATUS_IN_PROGRESS' => 'in_progress',
    'TICKET_STATUS_WAITING' => 'waiting',
    'TICKET_STATUS_RESOLVED' => 'resolved',
    'TICKET_STATUS_CLOSED' => 'closed',
    'TICKET_STATUS_REOPENED' => 'reopened',

    // Ticket Priority
    'TICKET_PRIORITY_LOW' => 'low',
    'TICKET_PRIORITY_NORMAL' => 'normal',
    'TICKET_PRIORITY_HIGH' => 'high',
    'TICKET_PRIORITY_URGENT' => 'urgent',

    // Service Types
    'SERVICE_TYPE_ONE_TIME' => 'one_time',
    'SERVICE_TYPE_RECURRING' => 'recurring',
    'SERVICE_TYPE_SUBSCRIPTION' => 'subscription',
    'SERVICE_TYPE_EMERGENCY' => 'emergency',

    // Pricing Types
    'PRICING_TYPE_FIXED' => 'fixed',
    'PRICING_TYPE_HOURLY' => 'hourly',
    'PRICING_TYPE_QUOTE_BASED' => 'quote_based',

    // Booking Status
    'BOOKING_STATUS_PENDING' => 'pending',
    'BOOKING_STATUS_CONFIRMED' => 'confirmed',
    'BOOKING_STATUS_IN_PROGRESS' => 'in_progress',
    'BOOKING_STATUS_COMPLETED' => 'completed',
    'BOOKING_STATUS_CANCELLED' => 'cancelled',
    'BOOKING_STATUS_NO_SHOW' => 'no_show',
    'BOOKING_STATUS_REFUNDED' => 'refunded',

    // Booking Types
    'BOOKING_TYPE_INSTANT' => 'instant',
    'BOOKING_TYPE_SCHEDULED' => 'scheduled',
    'BOOKING_TYPE_EMERGENCY' => 'emergency',

    // Wallet Transaction Types
    'WALLET_TRANSACTION_CREDIT' => 'credit',
    'WALLET_TRANSACTION_DEBIT' => 'debit',
    'WALLET_TRANSACTION_REFUND' => 'refund',
    'WALLET_TRANSACTION_BONUS' => 'bonus',
    'WALLET_TRANSACTION_COMMISSION' => 'commission',

    // Document Types
    'DOCUMENT_TYPE_COMMERCIAL_REGISTER' => 'commercial_register',
    'DOCUMENT_TYPE_LICENSE' => 'license',
    'DOCUMENT_TYPE_ID_CARD' => 'id_card',
    'DOCUMENT_TYPE_TAX_CERTIFICATE' => 'tax_certificate',
    'DOCUMENT_TYPE_BANK_ACCOUNT' => 'bank_account',
    'DOCUMENT_TYPE_OTHER' => 'other',

    // Document Status
    'DOCUMENT_STATUS_PENDING' => 'pending',
    'DOCUMENT_STATUS_APPROVED' => 'approved',
    'DOCUMENT_STATUS_REJECTED' => 'rejected',
    'DOCUMENT_STATUS_EXPIRED' => 'expired',

    // Banner Positions
    'BANNER_POSITION_HOMEPAGE_MAIN' => 'homepage_main',
    'BANNER_POSITION_HOMEPAGE_SECONDARY' => 'homepage_secondary',
    'BANNER_POSITION_CATEGORY' => 'category',
    'BANNER_POSITION_PRODUCT' => 'product',
    'BANNER_POSITION_CART' => 'cart',
    'BANNER_POSITION_CHECKOUT' => 'checkout',
    'BANNER_POSITION_SIDEBAR' => 'sidebar',

    // Days of Week
    'DAY_SUNDAY' => 0,
    'DAY_MONDAY' => 1,
    'DAY_TUESDAY' => 2,
    'DAY_WEDNESDAY' => 3,
    'DAY_THURSDAY' => 4,
    'DAY_FRIDAY' => 5,
    'DAY_SATURDAY' => 6,

    // Tax Types
    'TAX_TYPE_VAT' => 'vat',
    'TAX_TYPE_GST' => 'gst',
    'TAX_TYPE_SALES_TAX' => 'sales_tax',
    'TAX_TYPE_CUSTOMS' => 'customs',
    'TAX_TYPE_EXCISE' => 'excise',

    // Auction Status
    'AUCTION_STATUS_DRAFT' => 'draft',
    'AUCTION_STATUS_ACTIVE' => 'active',
    'AUCTION_STATUS_PAUSED' => 'paused',
    'AUCTION_STATUS_ENDED' => 'ended',
    'AUCTION_STATUS_CANCELLED' => 'cancelled',

    // Auction Types
    'AUCTION_TYPE_ENGLISH' => 'english',
    'AUCTION_TYPE_DUTCH' => 'dutch',
    'AUCTION_TYPE_SEALED_BID' => 'sealed_bid',

    // Job Status
    'JOB_STATUS_OPEN' => 'open',
    'JOB_STATUS_CLOSED' => 'closed',
    'JOB_STATUS_PAUSED' => 'paused',
    'JOB_STATUS_FILLED' => 'filled',

    // Job Types
    'JOB_TYPE_FULL_TIME' => 'full_time',
    'JOB_TYPE_PART_TIME' => 'part_time',
    'JOB_TYPE_CONTRACT' => 'contract',
    'JOB_TYPE_FREELANCE' => 'freelance',

    // Supplier Status
    'SUPPLIER_STATUS_ACTIVE' => 'active',
    'SUPPLIER_STATUS_INACTIVE' => 'inactive',
    'SUPPLIER_STATUS_BLACKLISTED' => 'blacklisted',

    // Error Codes
    'ERROR_CODE_VALIDATION' => 1001,
    'ERROR_CODE_AUTHENTICATION' => 1002,
    'ERROR_CODE_AUTHORIZATION' => 1003,
    'ERROR_CODE_NOT_FOUND' => 1004,
    'ERROR_CODE_DATABASE' => 1005,
    'ERROR_CODE_SERVER' => 1006,
    'ERROR_CODE_PAYMENT' => 1007,
    'ERROR_CODE_INSUFFICIENT_STOCK' => 1008,
    'ERROR_CODE_INVALID_COUPON' => 1009,
    'ERROR_CODE_FILE_UPLOAD' => 1010,
    'ERROR_CODE_AUCTION_ENDED' => 1011,
    'ERROR_CODE_JOB_NOT_AVAILABLE' => 1012,

    // HTTP Status Codes
    'HTTP_OK' => 200,
    'HTTP_CREATED' => 201,
    'HTTP_NO_CONTENT' => 204,
    'HTTP_BAD_REQUEST' => 400,
    'HTTP_UNAUTHORIZED' => 401,
    'HTTP_FORBIDDEN' => 403,
    'HTTP_NOT_FOUND' => 404,
    'HTTP_METHOD_NOT_ALLOWED' => 405,
    'HTTP_CONFLICT' => 409,
    'HTTP_UNPROCESSABLE_ENTITY' => 422,
    'HTTP_TOO_MANY_REQUESTS' => 429,
    'HTTP_INTERNAL_SERVER_ERROR' => 500,
    'HTTP_SERVICE_UNAVAILABLE' => 503,

    // Success Messages
    'MSG_SUCCESS_CREATED' => getenv('MSG_SUCCESS_CREATED') ?: 'تم الإنشاء بنجاح',
    'MSG_SUCCESS_UPDATED' => getenv('MSG_SUCCESS_UPDATED') ?: 'تم التحديث بنجاح',
    'MSG_SUCCESS_DELETED' => getenv('MSG_SUCCESS_DELETED') ?: 'تم الحذف بنجاح',
    'MSG_SUCCESS_LOGIN' => getenv('MSG_SUCCESS_LOGIN') ?: 'تم تسجيل الدخول بنجاح',
    'MSG_SUCCESS_LOGOUT' => getenv('MSG_SUCCESS_LOGOUT') ?: 'تم تسجيل الخروج بنجاح',
    'MSG_SUCCESS_REGISTERED' => getenv('MSG_SUCCESS_REGISTERED') ?: 'تم التسجيل بنجاح',
    'MSG_SUCCESS_VERIFIED' => getenv('MSG_SUCCESS_VERIFIED') ?: 'تم التحقق بنجاح',

    // Error Messages
    'MSG_ERROR_INVALID_CREDENTIALS' => getenv('MSG_ERROR_INVALID_CREDENTIALS') ?: 'بيانات الدخول غير صحيحة',
    'MSG_ERROR_UNAUTHORIZED' => getenv('MSG_ERROR_UNAUTHORIZED') ?: 'غير مصرح لك بالوصول',
    'MSG_ERROR_NOT_FOUND' => getenv('MSG_ERROR_NOT_FOUND') ?: 'العنصر غير موجود',
    'MSG_ERROR_SERVER' => getenv('MSG_ERROR_SERVER') ?: 'حدث خطأ في السيرفر',
    'MSG_ERROR_VALIDATION' => getenv('MSG_ERROR_VALIDATION') ?: 'خطأ في البيانات المدخلة',
    'MSG_ERROR_DATABASE' => getenv('MSG_ERROR_DATABASE') ?: 'خطأ في قاعدة البيانات',
    'MSG_ERROR_EMAIL_EXISTS' => getenv('MSG_ERROR_EMAIL_EXISTS') ?: 'البريد الإلكتروني مستخدم مسبقاً',
    'MSG_ERROR_PHONE_EXISTS' => getenv('MSG_ERROR_PHONE_EXISTS') ?: 'رقم الجوال مستخدم مسبقاً',

    // Regex Patterns
    'REGEX_EMAIL' => '/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/',
    'REGEX_PHONE_INTERNATIONAL' => '/^\+?[1-9]\d{1,14}$/',
    'REGEX_PASSWORD_STRONG' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/',
    'REGEX_SLUG' => '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
    'REGEX_USERNAME' => '/^[a-zA-Z0-9_-]{3,20}$/',
    'REGEX_POSTAL_CODE' => '/^[0-9]{5}$/',
];
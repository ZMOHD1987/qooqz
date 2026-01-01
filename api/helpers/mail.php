<?php
// htdocs/api/helpers/mail.php
// ملف دوال إرسال البريد الإلكتروني (Email Helper)
// يدعم SMTP والقوالب

// ===========================================
// تحميل الملفات المطلوبة
// ===========================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';

// ===========================================
// Mail Class
// ===========================================

class Mail {
    
    // ===========================================
    // 1️⃣ إرسال بريد إلكتروني (Send Email)
    // ===========================================
    
    /**
     * إرسال بريد إلكتروني
     * 
     * @param string $to البريد المستلم
     * @param string $subject العنوان
     * @param string $body محتوى الرسالة (HTML)
     * @param string|null $fromName اسم المرسل (اختياري)
     * @param string|null $replyTo بريد الرد (اختياري)
     * @return bool
     */
    public static function send($to, $subject, $body, $fromName = null, $replyTo = null) {
        // التحقق من تفعيل البريد
        if (!MAIL_ENABLED) {
            self::logMail('disabled', $to, $subject);
            return true; // نرجع true في بيئة التطوير
        }
        
        try {
            // استخدام PHPMailer إذا كان متاحاً، وإلا mail() العادية
            if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                return self::sendWithPHPMailer($to, $subject, $body, $fromName, $replyTo);
            } else {
                return self::sendWithMailFunction($to, $subject, $body, $fromName, $replyTo);
            }
            
        } catch (Exception $e) {
            self::logError('Email send failed: ' . $e->getMessage());
            return false;
        }
    }
    
    // ===========================================
    // 2️⃣ إرسال باستخدام PHPMailer (SMTP)
    // ===========================================
    
    /**
     * إرسال بريد باستخدام PHPMailer و SMTP
     * 
     * @param string $to
     * @param string $subject
     * @param string $body
     * @param string|null $fromName
     * @param string|null $replyTo
     * @return bool
     */
    private static function sendWithPHPMailer($to, $subject, $body, $fromName, $replyTo) {
        require_once __DIR__ . '/../../vendor/autoload.php'; // إذا كنت تستخدم Composer
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            // إعدادات SMTP
            $mail->isSMTP();
            $mail->Host = MAIL_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = MAIL_USERNAME;
            $mail->Password = MAIL_PASSWORD;
            $mail->SMTPSecure = MAIL_ENCRYPTION; // tls or ssl
            $mail->Port = MAIL_PORT;
            $mail->CharSet = 'UTF-8';
            
            // المرسل
            $mail->setFrom(
                MAIL_FROM_ADDRESS,
                $fromName ??  MAIL_FROM_NAME
            );
            
            // المستلم
            $mail->addAddress($to);
            
            // بريد الرد
            if ($replyTo) {
                $mail->addReplyTo($replyTo);
            }
            
            // المحتوى
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body); // نسخة نصية
            
            // إرسال
            $sent = $mail->send();
            
            if ($sent) {
                self:: logMail('sent', $to, $subject);
            }
            
            return $sent;
            
        } catch (Exception $e) {
            self::logError('PHPMailer Error: ' . $mail->ErrorInfo);
            return false;
        }
    }
    
    // ===========================================
    // 3️⃣ إرسال باستخدام mail() العادية
    // ===========================================
    
    /**
     * إرسال بريد باستخدام دالة mail() العادية
     * 
     * @param string $to
     * @param string $subject
     * @param string $body
     * @param string|null $fromName
     * @param string|null $replyTo
     * @return bool
     */
    private static function sendWithMailFunction($to, $subject, $body, $fromName, $replyTo) {
        $from = $fromName ??  MAIL_FROM_NAME;
        
        $headers = [
            'MIME-Version:  1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from .  ' <' . MAIL_FROM_ADDRESS . '>',
        ];
        
        if ($replyTo) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        
        $headers[] = 'X-Mailer: PHP/' . phpversion();
        
        $sent = mail($to, $subject, $body, implode("\r\n", $headers));
        
        if ($sent) {
            self::logMail('sent', $to, $subject);
        } else {
            self::logError('mail() function failed for: ' . $to);
        }
        
        return $sent;
    }
    
    // ===========================================
    // 4️⃣ إرسال بريد ترحيبي (Welcome Email)
    // ===========================================
    
    /**
     * إرسال بريد ترحيبي للمستخدم الجديد
     * 
     * @param string $email
     * @param string $name
     * @param string $username
     * @return bool
     */
    public static function sendWelcomeEmail($email, $name, $username) {
        $subject = 'مرحباً بك في ' . APP_NAME .  ' - Welcome to ' . APP_NAME;
        
        $body = self::getTemplate('welcome', [
            'name' => $name,
            'username' => $username,
            'app_name' => APP_NAME,
            'app_url' => APP_URL
        ]);
        
        return self::send($email, $subject, $body);
    }
    
    // ===========================================
    // 5️⃣ إرسال رمز التحقق OTP
    // ===========================================
    
    /**
     * إرسال رمز التحقق OTP
     * 
     * @param string $email
     * @param string $name
     * @param string $otp
     * @return bool
     */
    public static function sendOTP($email, $name, $otp) {
        $subject = 'رمز التحقق - Verification Code';
        
        $body = self::getTemplate('otp', [
            'name' => $name,
            'otp' => $otp,
            'expiry' => OTP_EXPIRY / 60, // دقائق
            'app_name' => APP_NAME
        ]);
        
        return self::send($email, $subject, $body);
    }
    
    // ===========================================
    // 6️⃣ إرسال بريد إعادة تعيين كلمة المرور
    // ===========================================
    
    /**
     * إرسال رابط إعادة تعيين كلمة المرور
     * 
     * @param string $email
     * @param string $name
     * @param string $resetToken
     * @return bool
     */
    public static function sendPasswordReset($email, $name, $resetToken) {
        $subject = 'إعادة تعيين كلمة المرور - Reset Password';
        
        $resetLink = APP_URL . '/reset-password? token=' . $resetToken;
        
        $body = self:: getTemplate('password_reset', [
            'name' => $name,
            'reset_link' => $resetLink,
            'expiry' => 60, // دقيقة
            'app_name' => APP_NAME
        ]);
        
        return self:: send($email, $subject, $body);
    }
    
    // ===========================================
    // 7️⃣ إرسال تأكيد طلب (Order Confirmation)
    // ===========================================
    
    /**
     * إرسال تأكيد الطلب
     * 
     * @param string $email
     * @param string $name
     * @param array $order بيانات الطلب
     * @return bool
     */
    public static function sendOrderConfirmation($email, $name, $order) {
        $subject = 'تأكيد الطلب #' . $order['order_number'] . ' - Order Confirmation';
        
        $body = self::getTemplate('order_confirmation', [
            'name' => $name,
            'order_number' => $order['order_number'],
            'order_date' => $order['created_at'],
            'total' => $order['grand_total'],
            'currency' => DEFAULT_CURRENCY_SYMBOL,
            'order_url' => APP_URL . '/orders/' . $order['id'],
            'app_name' => APP_NAME
        ]);
        
        return self::send($email, $subject, $body);
    }
    
    // ===========================================
    // 8️⃣ إرسال تحديث حالة الطلب
    // ===========================================
    
    /**
     * إرسال تحديث حالة الطلب
     * 
     * @param string $email
     * @param string $name
     * @param string $orderNumber
     * @param string $status
     * @param string|null $trackingNumber
     * @return bool
     */
    public static function sendOrderStatusUpdate($email, $name, $orderNumber, $status, $trackingNumber = null) {
        $statusTexts = [
            'confirmed' => 'تم تأكيد طلبك - Order Confirmed',
            'processing' => 'جاري تجهيز طلبك - Order Processing',
            'shipped' => 'تم شحن طلبك - Order Shipped',
            'delivered' => 'تم توصيل طلبك - Order Delivered',
            'cancelled' => 'تم إلغاء طلبك - Order Cancelled'
        ];
        
        $subject = $statusTexts[$status] ?? 'تحديث الطلب - Order Update';
        
        $body = self::getTemplate('order_status', [
            'name' => $name,
            'order_number' => $orderNumber,
            'status' => $status,
            'tracking_number' => $trackingNumber,
            'app_name' => APP_NAME
        ]);
        
        return self::send($email, $subject, $body);
    }
    
    // ===========================================
    // 9️⃣ إرسال بريد موافقة التاجر
    // ===========================================
    
    /**
     * إرسال إشعار موافقة التاجر
     * 
     * @param string $email
     * @param string $storeName
     * @return bool
     */
    public static function sendVendorApproval($email, $storeName) {
        $subject = 'تم الموافقة على متجرك - Store Approved';
        
        $body = self::getTemplate('vendor_approval', [
            'store_name' => $storeName,
            'dashboard_url' => APP_URL .  '/vendor/dashboard',
            'app_name' => APP_NAME
        ]);
        
        return self:: send($email, $subject, $body);
    }
    
    // ===========================================
    // 🔟 إرسال بريد رفض التاجر
    // ===========================================
    
    /**
     * إرسال إشعار رفض التاجر
     * 
     * @param string $email
     * @param string $storeName
     * @param string $reason
     * @return bool
     */
    public static function sendVendorRejection($email, $storeName, $reason) {
        $subject = 'طلب المتجر - Store Application';
        
        $body = self::getTemplate('vendor_rejection', [
            'store_name' => $storeName,
            'reason' => $reason,
            'support_email' => MAIL_FROM_ADDRESS,
            'app_name' => APP_NAME
        ]);
        
        return self::send($email, $subject, $body);
    }
    
    // ===========================================
    // 1️⃣1️⃣ إرسال فاتورة (Invoice)
    // ===========================================
    
    /**
     * إرسال الفاتورة
     * 
     * @param string $email
     * @param string $name
     * @param string $invoiceNumber
     * @param string $pdfPath مسار ملف PDF
     * @return bool
     */
    public static function sendInvoice($email, $name, $invoiceNumber, $pdfPath) {
        $subject = 'فاتورة #' . $invoiceNumber .  ' - Invoice';
        
        $body = self::getTemplate('invoice', [
            'name' => $name,
            'invoice_number' => $invoiceNumber,
            'app_name' => APP_NAME
        ]);
        
        // TODO: إضافة attachment للـ PDF
        // يحتاج PHPMailer
        
        return self::send($email, $subject, $body);
    }
    
    // ===========================================
    // 1️⃣2️⃣ إرسال إشعار دعم فني
    // ===========================================
    
    /**
     * إرسال إشعار بتذكرة دعم جديدة
     * 
     * @param string $email
     * @param string $name
     * @param string $ticketNumber
     * @return bool
     */
    public static function sendSupportTicketNotification($email, $name, $ticketNumber) {
        $subject = 'تذكرة دعم #' . $ticketNumber . ' - Support Ticket';
        
        $body = self::getTemplate('support_ticket', [
            'name' => $name,
            'ticket_number' => $ticketNumber,
            'ticket_url' => APP_URL .  '/support/tickets/' . $ticketNumber,
            'app_name' => APP_NAME
        ]);
        
        return self::send($email, $subject, $body);
    }
    
    // ===========================================
    // 🔧 دوال القوالب (Template Functions)
    // ===========================================
    
    /**
     * الحصول على قالب بريد إلكتروني
     * 
     * @param string $templateName اسم القالب
     * @param array $variables المتغيرات
     * @return string
     */
    private static function getTemplate($templateName, $variables = []) {
        // محاولة تحميل قالب مخصص
        $templatePath = __DIR__ . '/../templates/emails/' . $templateName . '. php';
        
        if (file_exists($templatePath)) {
            // استخراج المتغيرات
            extract($variables);
            
            // بدء output buffering
            ob_start();
            include $templatePath;
            $content = ob_get_clean();
            
            // تطبيق القالب الأساسي
            return self::applyLayout($content, $variables);
        }
        
        // إذا لم يوجد قالب، استخدم قالب افتراضي
        return self::getDefaultTemplate($templateName, $variables);
    }
    
    /**
     * تطبيق القالب الأساسي (Layout)
     * 
     * @param string $content
     * @param array $variables
     * @return string
     */
    private static function applyLayout($content, $variables) {
        $appName = APP_NAME;
        $appUrl = APP_URL;
        $year = date('Y');
        
        return <<<HTML
<! DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$appName}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding:  0;
            direction: rtl;
        }
        . container {
            max-width:  600px;
            margin:  20px auto;
            background-color: #ffffff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            background:  linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            text-align:  center;
            color: white;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
        }
        .content {
            padding: 30px;
            color: #333;
            line-height: 1.6;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background-color: #667eea;
            color: white ! important;
            text-decoration:  none;
            border-radius:  5px;
            margin:  20px 0;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #eee;
        }
        .otp-code {
            font-size: 32px;
            font-weight:  bold;
            color: #667eea;
            letter-spacing: 5px;
            padding: 20px;
            background-color: #f0f0f0;
            border-radius: 5px;
            text-align: center;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{$appName}</h1>
        </div>
        <div class="content">
            {$content}
        </div>
        <div class="footer">
            <p>&copy; {$year} {$appName}. جميع الحقوق محفوظة - All rights reserved.</p>
            <p>
                <a href="{$appUrl}" style="color: #667eea; text-decoration: none;">زيارة الموقع</a> | 
                <a href="{$appUrl}/support" style="color: #667eea; text-decoration: none;">الدعم الفني</a>
            </p>
        </div>
    </div>
</body>
</html>
HTML;
    }
    
    /**
     * الحصول على قالب افتراضي
     * 
     * @param string $templateName
     * @param array $variables
     * @return string
     */
    private static function getDefaultTemplate($templateName, $variables) {
        extract($variables);
        
        switch ($templateName) {
            case 'welcome':
                $content = <<<HTML
                <h2>مرحباً {$name}!</h2>
                <p>نشكرك على التسجيل في {$app_name}. </p>
                <p>اسم المستخدم: <strong>{$username}</strong></p>
                <p>يمكنك الآن تسجيل الدخول والبدء في التسوق.</p>
                <a href="{$app_url}" class="button">تسوق الآن</a>
HTML;
                break;
                
            case 'otp': 
                $content = <<<HTML
                <h2>رمز التحقق</h2>
                <p>مرحباً {$name},</p>
                <p>رمز التحقق الخاص بك: </p>
                <div class="otp-code">{$otp}</div>
                <p>هذا الرمز صالح لمدة {$expiry} دقائق.</p>
                <p><strong>تحذير:</strong> لا تشارك هذا الرمز مع أي شخص. </p>
HTML;
                break;
                
            case 'password_reset': 
                $content = <<<HTML
                <h2>إعادة تعيين كلمة المرور</h2>
                <p>مرحباً {$name},</p>
                <p>تلقينا طلباً لإعادة تعيين كلمة المرور الخاصة بك.</p>
                <p>انقر على الزر أدناه لإعادة تعيينها: </p>
                <a href="{$reset_link}" class="button">إعادة تعيين كلمة المرور</a>
                <p>هذا الرابط صالح لمدة {$expiry} دقيقة.</p>
                <p>إذا لم تطلب ذلك، يرجى تجاهل هذه الرسالة.</p>
HTML;
                break;
                
            case 'order_confirmation':
                $content = <<<HTML
                <h2>تأكيد الطلب</h2>
                <p>مرحباً {$name},</p>
                <p>شكراً لك!  تم استلام طلبك بنجاح.</p>
                <p><strong>رقم الطلب:</strong> {$order_number}</p>
                <p><strong>التاريخ:</strong> {$order_date}</p>
                <p><strong>المبلغ الإجمالي:</strong> {$total} {$currency}</p>
                <a href="{$order_url}" class="button">عرض تفاصيل الطلب</a>
HTML;
                break;
                
            default:
                $content = '<p>محتوى البريد الإلكتروني. </p>';
        }
        
        return self::applyLayout($content, $variables);
    }
    
    // ===========================================
    // 🔧 دوال مساعدة (Helper Functions)
    // ===========================================
    
    /**
     * التحقق من صحة البريد الإلكتروني
     * 
     * @param string $email
     * @return bool
     */
    public static function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * تسجيل عملية إرسال بريد
     * 
     * @param string $status
     * @param string $to
     * @param string $subject
     */
    private static function logMail($status, $to, $subject) {
        if (LOG_ENABLED) {
            $message = sprintf(
                "[%s] Email %s: To=%s, Subject=%s\n",
                date('Y-m-d H:i:s'),
                $status,
                $to,
                $subject
            );
            
            error_log($message, 3, LOG_FILE_API);
        }
    }
    
    /**
     * تسجيل خطأ
     * 
     * @param string $message
     */
    private static function logError($message) {
        if (LOG_ENABLED) {
            error_log("[Mail Error] " . $message, 3, LOG_FILE_ERROR);
        }
        
        if (DEBUG_MODE) {
            error_log("[Mail Debug] " . $message);
        }
    }
}

// ===========================================
// ✅ تم تحميل Mail Helper بنجاح
// ===========================================

?>
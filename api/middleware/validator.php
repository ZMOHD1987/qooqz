<?php
// htdocs/api/middleware/validator.php
// ملف Middleware للتحقق من صحة البيانات (Input Validation)
// يدعم قواعد متقدمة ورسائل خطأ مخصصة، مع دعم PDO

// ===========================================
// تحميل الملفات المطلوبة
// ===========================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/security.php';

// ===========================================
// Validator Class
// ===========================================

class Validator {
    
    private $data = [];
    private $rules = [];
    private $errors = [];
    private $customMessages = [];
    private static ?PDO $pdo = null;
    private $lang = 'ar'; // اللغة الافتراضية
    
    /**
     * تعيين PDO instance
     * 
     * @param PDO $pdo
     */
    public static function setPDO(PDO $pdo) {
        self::$pdo = $pdo;
    }
    
    // ===========================================
    // 1️⃣ إنشاء Validator جديد
    // ===========================================
    
    /**
     * إنشاء validator
     * 
     * @param array $data البيانات المراد التحقق منها
     * @param array $rules القواعد
     * @param array $customMessages رسائل مخصصة
     * @param string $lang اللغة
     */
    public function __construct($data, $rules, $customMessages = [], $lang = 'ar') {
        $this->data = $data;
        $this->rules = $rules;
        $this->customMessages = $customMessages;
        $this->lang = $lang;
    }
    
    /**
     * إنشاء validator (Static)
     * 
     * @param array $data
     * @param array $rules
     * @param array $customMessages
     * @param string $lang
     * @return Validator
     */
    public static function make($data, $rules, $customMessages = [], $lang = 'ar') {
        return new self($data, $rules, $customMessages, $lang);
    }
    
    // ===========================================
    // 2️⃣ التحقق من البيانات
    // ===========================================
    
    /**
     * تشغيل التحقق
     * 
     * @return bool
     */
    public function validate() {
        $this->errors = [];
        
        foreach ($this->rules as $field => $ruleString) {
            $rules = $this->parseRules($ruleString);
            $value = $this->getValue($field);
            
            foreach ($rules as $rule) {
                $ruleName = $rule['name'];
                $params = $rule['params'];
                
                // تخطي القواعد الأخرى إذا كان الحقل اختياري وفارغ
                if ($ruleName === 'optional' && $this->isEmpty($value)) {
                    break;
                }
                
                // تنفيذ القاعدة
                $method = 'validate' . ucfirst($ruleName);
                
                if (method_exists($this, $method)) {
                    $valid = $this->$method($field, $value, $params);
                    
                    if (!$valid) {
                        $this->addError($field, $ruleName, $params);
                    }
                } else {
                    // قاعدة غير معروفة
                    $this->addError($field, 'invalid_rule', [$ruleName]);
                }
                
                // إذا فشل التحقق ولدينا bail، توقف
                if (!empty($this->errors[$field]) && in_array('bail', array_column($rules, 'name'))) {
                    break;
                }
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * التحقق وإرجاع الأخطاء أو Response
     * 
     * @return array|null البيانات الصحيحة أو null
     */
    public function validated() {
        if (!$this->validate()) {
            Response::validationError($this->errors);
        }
        
        // إرجاع البيانات المُحققة فقط
        $validated = [];
        foreach (array_keys($this->rules) as $field) {
            if (isset($this->data[$field])) {
                $validated[$field] = $this->data[$field];
            }
        }
        
        return $validated;
    }
    
    /**
     * التحقق من أن البيانات صحيحة
     * 
     * @return bool
     */
    public function passes() {
        return $this->validate();
    }
    
    /**
     * التحقق من أن البيانات فاشلة
     * 
     * @return bool
     */
    public function fails() {
        return !$this->validate();
    }
    
    // ===========================================
    // 3️⃣ قواعد التحقق (Validation Rules)
    // ===========================================
    
    /**
     * مطلوب (Required)
     */
    protected function validateRequired($field, $value, $params) {
        return ! $this->isEmpty($value);
    }
    
    /**
     * اختياري (Optional)
     */
    protected function validateOptional($field, $value, $params) {
        return true; // دائماً صحيح، يُستخدم للتحكم في التدفق
    }
    
    /**
     * بريد إلكتروني (Email)
     */
    protected function validateEmail($field, $value, $params) {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * رقم (Numeric)
     */
    protected function validateNumeric($field, $value, $params) {
        return is_numeric($value);
    }
    
    /**
     * رقم صحيح (Integer)
     */
    protected function validateInteger($field, $value, $params) {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }
    
    /**
     * نص (String)
     */
    protected function validateString($field, $value, $params) {
        return is_string($value);
    }
    
    /**
     * مصفوفة (Array)
     */
    protected function validateArray($field, $value, $params) {
        return is_array($value);
    }
    
    /**
     * منطقي (Boolean)
     */
    protected function validateBoolean($field, $value, $params) {
        return in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true);
    }
    
    /**
     * الحد الأدنى للطول (Min)
     */
    protected function validateMin($field, $value, $params) {
        $min = $params[0];
        
        if (is_numeric($value)) {
            return $value >= $min;
        }
        
        if (is_string($value)) {
            return mb_strlen($value, 'UTF-8') >= $min;
        }
        
        if (is_array($value)) {
            return count($value) >= $min;
        }
        
        return false;
    }
    
    /**
     * الحد الأقصى للطول (Max)
     */
    protected function validateMax($field, $value, $params) {
        $max = $params[0];
        
        if (is_numeric($value)) {
            return $value <= $max;
        }
        
        if (is_string($value)) {
            return mb_strlen($value, 'UTF-8') <= $max;
        }
        
        if (is_array($value)) {
            return count($value) <= $max;
        }
        
        return false;
    }
    
    /**
     * بين قيمتين (Between)
     */
    protected function validateBetween($field, $value, $params) {
        $min = $params[0];
        $max = $params[1];
        
        if (is_numeric($value)) {
            return $value >= $min && $value <= $max;
        }
        
        $length = is_string($value) ? mb_strlen($value, 'UTF-8') : count($value);
        return $length >= $min && $length <= $max;
    }
    
    /**
     * طول محدد (Length)
     */
    protected function validateLength($field, $value, $params) {
        $length = $params[0];
        
        if (is_string($value)) {
            return mb_strlen($value, 'UTF-8') === (int)$length;
        }
        
        if (is_array($value)) {
            return count($value) === (int)$length;
        }
        
        return false;
    }
    
    /**
     * في قائمة محددة (In)
     */
    protected function validateIn($field, $value, $params) {
        return in_array($value, $params, true);
    }
    
    /**
     * ليس في قائمة (Not In)
     */
    protected function validateNotIn($field, $value, $params) {
        return !in_array($value, $params, true);
    }
    
    /**
     * مطابقة حقل آخر (Same)
     */
    protected function validateSame($field, $value, $params) {
        $otherField = $params[0];
        $otherValue = $this->getValue($otherField);
        
        return $value === $otherValue;
    }
    
    /**
     * مختلف عن حقل آخر (Different)
     */
    protected function validateDifferent($field, $value, $params) {
        $otherField = $params[0];
        $otherValue = $this->getValue($otherField);
        
        return $value !== $otherValue;
    }
    
    /**
     * تاريخ (Date)
     */
    protected function validateDate($field, $value, $params) {
        return strtotime($value) !== false;
    }
    
    /**
     * تاريخ بعد (After)
     */
    protected function validateAfter($field, $value, $params) {
        $compareDate = $params[0];
        
        $valueTime = strtotime($value);
        $compareTime = strtotime($compareDate);
        
        return $valueTime !== false && $compareTime !== false && $valueTime > $compareTime;
    }
    
    /**
     * تاريخ قبل (Before)
     */
    protected function validateBefore($field, $value, $params) {
        $compareDate = $params[0];
        
        $valueTime = strtotime($value);
        $compareTime = strtotime($compareDate);
        
        return $valueTime !== false && $compareTime !== false && $valueTime < $compareTime;
    }
    
    /**
     * نمط Regex (Regex)
     */
    protected function validateRegex($field, $value, $params) {
        $pattern = $params[0];
        return preg_match($pattern, $value) === 1;
    }
    
    /**
     * URL (URL)
     */
    protected function validateUrl($field, $value, $params) {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * عنوان IP (IP)
     */
    protected function validateIp($field, $value, $params) {
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }
    
    /**
     * JSON (JSON)
     */
    protected function validateJson($field, $value, $params) {
        if (! is_string($value)) {
            return false;
        }
        
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }
    
    /**
     * أبجدي (Alpha)
     */
    protected function validateAlpha($field, $value, $params) {
        return preg_match('/^[\pL\pM]+$/u', $value) === 1;
    }
    
    /**
     * أبجدي رقمي (Alpha Numeric)
     */
    protected function validateAlphaNum($field, $value, $params) {
        return preg_match('/^[\pL\pM\pN]+$/u', $value) === 1;
    }
    
    /**
     * أبجدي مع شرطات (Alpha Dash)
     */
    protected function validateAlphaDash($field, $value, $params) {
        return preg_match('/^[\pL\pM\pN_-]+$/u', $value) === 1;
    }
    
    /**
     * فريد في قاعدة البيانات (Unique) - مع PDO
     */
    protected function validateUnique($field, $value, $params) {
        if (!self::$pdo) return false;
        
        $table = $params[0];
        $column = $params[1] ?? $field;
        $except = $params[2] ?? null;
        
        try {
            $sql = "SELECT COUNT(*) as count FROM {$table} WHERE {$column} = ?";
            
            if ($except) {
                $sql .= " AND id != ?";
            }
            
            $stmt = self::$pdo->prepare($sql);
            
            if ($except) {
                $stmt->execute([$value, $except]);
            } else {
                $stmt->execute([$value]);
            }
            
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row['count'] == 0;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * موجود في قاعدة البيانات (Exists) - مع PDO
     */
    protected function validateExists($field, $value, $params) {
        if (!self::$pdo) return false;
        
        $table = $params[0];
        $column = $params[1] ?? 'id';
        
        try {
            $stmt = self::$pdo->prepare("SELECT COUNT(*) as count FROM {$table} WHERE {$column} = ?");
            $stmt->execute([$value]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row['count'] > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * رقم جوال سعودي (Saudi Phone)
     */
    protected function validateSaudiPhone($field, $value, $params) {
        return Security::validateSaudiPhone($value);
    }
    
    /**
     * رقم جوال دولي (Phone)
     */
    protected function validatePhone($field, $value, $params) {
        return preg_match('/^\+?[1-9]\d{1,14}$/', $value) === 1;
    }
    
    /**
     * كلمة مرور قوية (Strong Password)
     */
    protected function validateStrongPassword($field, $value, $params) {
        $result = Security::validatePasswordStrength($value);
        return $result['valid'];
    }
    
    /**
     * ملف (File)
     */
    protected function validateFile($field, $value, $params) {
        return isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK;
    }
    
    /**
     * صورة (Image)
     */
    protected function validateImage($field, $value, $params) {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $_FILES[$field]['tmp_name']);
        finfo_close($finfo);
        
        return in_array($mimeType, $allowedTypes);
    }
    
    /**
     * حجم ملف أقصى (Max File Size)
     */
    protected function validateMaxFileSize($field, $value, $params) {
        if (!isset($_FILES[$field])) {
            return false;
        }
        
        $maxSize = $params[0] * 1024; // تحويل KB إلى bytes
        return $_FILES[$field]['size'] <= $maxSize;
    }
    
    /**
     * امتداد ملف (File Extension)
     */
    protected function validateMimes($field, $value, $params) {
        if (!isset($_FILES[$field])) {
            return false;
        }
        
        $extension = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
        return in_array($extension, $params);
    }
    
    // ===========================================
    // 4️⃣ دوال مساعدة (Helper Functions)
    // ===========================================
    
    /**
     * تحليل قواعد الحقل
     * 
     * @param string|array $ruleString
     * @return array
     */
    private function parseRules($ruleString) {
        if (is_array($ruleString)) {
            $ruleString = implode('|', $ruleString);
        }
        
        $rules = [];
        $ruleArray = explode('|', $ruleString);
        
        foreach ($ruleArray as $rule) {
            $ruleParts = explode(':', $rule, 2);
            $ruleName = $ruleParts[0];
            $params = isset($ruleParts[1]) ? explode(',', $ruleParts[1]) : [];
            
            $rules[] = [
                'name' => $ruleName,
                'params' => $params
            ];
        }
        
        return $rules;
    }
    
    /**
     * الحصول على قيمة حقل
     * 
     * @param string $field
     * @return mixed
     */
    private function getValue($field) {
        // دعم dot notation (مثل: user.name)
        if (strpos($field, '.') !== false) {
            $keys = explode('.', $field);
            $value = $this->data;
            
            foreach ($keys as $key) {
                if (! isset($value[$key])) {
                    return null;
                }
                $value = $value[$key];
            }
            
            return $value;
        }
        
        return $this->data[$field] ?? null;
    }
    
    /**
     * التحقق من قيمة فارغة
     * 
     * @param mixed $value
     * @return bool
     */
    private function isEmpty($value) {
        return $value === null || $value === '' || $value === [];
    }
    
    /**
     * إضافة خطأ
     * 
     * @param string $field
     * @param string $rule
     * @param array $params
     */
    private function addError($field, $rule, $params = []) {
        $message = $this->getMessage($field, $rule, $params);
        
        if (! isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        
        $this->errors[$field][] = $message;
    }
    
    /**
     * الحصول على رسالة الخطأ
     * 
     * @param string $field
     * @param string $rule
     * @param array $params
     * @return string
     */
    private function getMessage($field, $rule, $params) {
        $customKey = "{$field}.{$rule}";
        
        if (isset($this->customMessages[$customKey])) {
            return $this->customMessages[$customKey];
        }
        
        $messages = [
            'ar' => [
                'required' => 'حقل :field مطلوب',
                'email' => 'يجب أن يكون :field عنوان بريد إلكتروني صحيح',
                'numeric' => 'يجب أن يكون :field رقم',
                'integer' => 'يجب أن يكون :field عدد صحيح',
                'string' => 'يجب أن يكون :field نص',
                'array' => 'يجب أن يكون :field مصفوفة',
                'boolean' => 'يجب أن يكون :field صحيح أو خاطئ',
                'min' => 'يجب ألا يقل :field عن :param0',
                'max' => 'يجب ألا يزيد :field عن :param0',
                'between' => 'يجب أن يكون :field بين :param0 و :param1',
                'length' => 'يجب أن يكون :field :param0 حرف بالضبط',
                'in' => 'القيمة المحددة في :field غير صحيحة',
                'not_in' => 'القيمة المحددة في :field غير صحيحة',
                'same' => 'يجب أن يتطابق :field مع :param0',
                'different' => 'يجب أن يكون :field مختلف عن :param0',
                'date' => 'يجب أن يكون :field تاريخ صحيح',
                'after' => 'يجب أن يكون :field بعد :param0',
                'before' => 'يجب أن يكون :field قبل :param0',
                'regex' => 'تنسيق :field غير صحيح',
                'url' => 'يجب أن يكون :field رابط صحيح',
                'ip' => 'يجب أن يكون :field عنوان IP صحيح',
                'json' => 'يجب أن يكون :field JSON صحيح',
                'alpha' => 'يجب أن يحتوي :field على أحرف فقط',
                'alpha_num' => 'يجب أن يحتوي :field على أحرف وأرقام فقط',
                'alpha_dash' => 'يجب أن يحتوي :field على أحرف وأرقام وشرطات وشرطات سفلية فقط',
                'unique' => 'قيمة :field موجودة مسبقاً',
                'exists' => 'القيمة المحددة في :field غير موجودة',
                'saudi_phone' => 'يجب أن يكون :field رقم جوال سعودي صحيح',
                'phone' => 'يجب أن يكون :field رقم جوال صحيح',
                'strong_password' => 'يجب أن تحتوي :field على حرف كبير وحرف صغير ورقم ورمز خاص',
                'file' => 'يجب أن يكون :field ملف',
                'image' => 'يجب أن يكون :field صورة',
                'max_file_size' => 'يجب ألا يزيد حجم :field عن :param0 كيلوبايت',
                'mimes' => 'يجب أن يكون :field ملف من نوع: :params'
            ],
            'en' => [
                'required' => 'The :field field is required',
                'email' => 'The :field must be a valid email address',
                'numeric' => 'The :field must be a number',
                'integer' => 'The :field must be an integer',
                'string' => 'The :field must be a string',
                'array' => 'The :field must be an array',
                'boolean' => 'The :field must be true or false',
                'min' => 'The :field must be at least :param0',
                'max' => 'The :field must not exceed :param0',
                'between' => 'The :field must be between :param0 and :param1',
                'length' => 'The :field must be exactly :param0 characters',
                'in' => 'The selected :field is invalid',
                'not_in' => 'The selected :field is invalid',
                'same' => 'The :field must match :param0',
                'different' => 'The :field must be different from :param0',
                'date' => 'The :field must be a valid date',
                'after' => 'The :field must be after :param0',
                'before' => 'The :field must be before :param0',
                'regex' => 'The :field format is invalid',
                'url' => 'The :field must be a valid URL',
                'ip' => 'The :field must be a valid IP address',
                'json' => 'The :field must be valid JSON',
                'alpha' => 'The :field may only contain letters',
                'alpha_num' => 'The :field may only contain letters and numbers',
                'alpha_dash' => 'The :field may only contain letters, numbers, dashes and underscores',
                'unique' => 'The :field has already been taken',
                'exists' => 'The selected :field is invalid',
                'saudi_phone' => 'The :field must be a valid Saudi phone number',
                'phone' => 'The :field must be a valid phone number',
                'strong_password' => 'The :field must contain uppercase, lowercase, number and special character',
                'file' => 'The :field must be a file',
                'image' => 'The :field must be an image',
                'max_file_size' => 'The :field must not exceed :param0 KB',
                'mimes' => 'The :field must be a file of type: :params'
            ]
        ];
        
        $message = $messages[$this->lang][$rule] ?? $messages['en'][$rule] ?? 'The :field is invalid';
        
        // استبدال :field
        $message = str_replace(':field', $this->formatFieldName($field), $message);
        
        // استبدال المعاملات
        foreach ($params as $index => $param) {
            $message = str_replace(":param{$index}", $param, $message);
        }
        
        $message = str_replace(':params', implode(', ', $params), $message);
        
        return $message;
    }
    
    /**
     * تنسيق اسم الحقل
     * 
     * @param string $field
     * @return string
     */
    private function formatFieldName($field) {
        return ucfirst(str_replace(['_', '.'], ' ', $field));
    }
    
    // ===========================================
    // 5️⃣ الحصول على الأخطاء
    // ===========================================
    
    /**
     * الحصول على جميع الأخطاء
     * 
     * @return array
     */
    public function errors() {
        return $this->errors;
    }
    
    /**
     * الحصول على أول خطأ
     * 
     * @return string|null
     */
    public function firstError() {
        foreach ($this->errors as $fieldErrors) {
            return $fieldErrors[0] ?? null;
        }
        return null;
    }
    
    /**
     * الحصول على أخطاء حقل معين
     * 
     * @param string $field
     * @return array
     */
    public function getFieldErrors($field) {
        return $this->errors[$field] ?? [];
    }
    
    /**
     * التحقق من وجود أخطاء لحقل معين
     * 
     * @param string $field
     * @return bool
     */
    public function hasError($field) {
        return isset($this->errors[$field]);
    }
}

// ===========================================
// دوال مساعدة عامة (Global Helper Functions)
// ===========================================

/**
 * التحقق من البيانات بسرعة
 * 
 * @param array $data
 * @param array $rules
 * @param array $messages
 * @param string $lang
 * @return array
 */
function validate($data, $rules, $messages = [], $lang = 'ar') {
    $validator = new Validator($data, $rules, $messages, $lang);
    return $validator->validated();
}

// ===========================================
// ✅ تم تحميل Validator Middleware بنجاح
// ===========================================

?>
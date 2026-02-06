<?php
// htdocs/api/helpers/i18n.php
// Global I18n helper with per-scope translations, caching, and PDO support for global languages.
// Usage:
//   $pdo = new PDO(...); // Initialize PDO
//   $i18n = new I18n($pdo, 'merchants'); // scope can be 'merchants', 'products', etc. (optional)
//   echo $i18n->t('page.title');

if (!class_exists('I18n')) {
    class I18n
    {
        private string $locale;
        private array $translations = [];
        private string $direction = 'ltr';
        private string $scope; // scope مثل 'merchants', 'products', etc. (يمكن أن يكون فارغًا)
        private bool $useApcu = false;
        private int $cacheTtl = 300; // seconds
        private PDO $pdo; // PDO instance
        private array $supportedLanguages = []; // اللغات المدعومة من DB

        // RTL languages (قائمة موسعة لدعم المزيد)
        private static array $rtl = ['ar', 'he', 'fa', 'ur', 'yi', 'ji'];

        /**
         * Constructor
         * @param PDO $pdo
         * @param string $scope (اختياري، مثل 'merchants' أو فارغ للبحث في جميع المجلدات)
         * @param string|null $locale
         */
        public function __construct(PDO $pdo, string $scope = '', ?string $locale = null)
        {
            $this->pdo = $pdo;
            if (session_status() === PHP_SESSION_NONE) @session_start();

            $this->useApcu = function_exists('apcu_fetch') && ini_get('apc.enabled') !== '0';
            $this->scope = $this->normalizeScope($scope);
            $this->loadSupportedLanguages(); // جلب اللغات المدعومة من DB
            $this->locale = $this->determineLocale($locale);
            $this->direction = $this->isRtlCode($this->locale) ? 'rtl' : 'ltr';

            $this->loadTranslations();
        }

        /**
         * Normalize language code
         */
        private function normalizeCode(string $code): string
        {
            $code = strtolower(trim($code));
            $code = preg_replace('/[^a-z0-9]/', '', $code);
            return $code;
        }

        /**
         * Normalize scope name (يمكن أن يكون فارغًا)
         */
        private function normalizeScope(string $scope): string
        {
            $scope = strtolower(trim($scope));
            $scope = preg_replace('/[^a-z0-9_\-]/', '', $scope);
            return $scope;
        }

        /**
         * جلب اللغات المدعومة من جدول languages (افتراض حقول: code, is_active)
         */
        private function loadSupportedLanguages(): void
        {
            try {
                $stmt = $this->pdo->prepare("SELECT code FROM languages WHERE is_active = 1");
                $stmt->execute();
                $this->supportedLanguages = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'code');
            } catch (PDOException $e) {
                // في حالة فشل، استخدم لغات افتراضية
                $this->supportedLanguages = ['en', 'ar'];
                error_log('Failed to load supported languages: ' . $e->getMessage());
            }
        }

        /**
         * Determine locale: explicit > session > default 'en', مع التحقق من المدعومة
         */
        private function determineLocale(?string $locale): string
        {
            $candidates = [];
            if (!empty($locale)) $candidates[] = $this->normalizeCode($locale);
            if (!empty($_SESSION['preferred_language'])) $candidates[] = $this->normalizeCode($_SESSION['preferred_language']);
            $candidates[] = 'en'; // default

            foreach ($candidates as $code) {
                if (in_array($code, $this->supportedLanguages)) {
                    return $code;
                }
            }
            return 'en'; // fallback
        }

        private function isRtlCode(string $code): bool
        {
            return in_array($code, self::$rtl, true);
        }

        /**
         * Change scope and reload translations
         */
        public function setScope(string $scope): void
        {
            $this->scope = $this->normalizeScope($scope);
            $this->loadTranslations(true);
        }

        /**
         * Get all loaded translations
         */
        public function all(): array
        {
            return $this->translations;
        }

        public function getLocale(): string
        {
            return $this->locale;
        }

        public function getDirection(): string
        {
            return $this->direction;
        }

        /**
         * Get supported languages
         */
        public function getSupportedLanguages(): array
        {
            return $this->supportedLanguages;
        }

        /**
         * Lookup translation by dot notation
         */
        public function t(string $key, $default = ''): string
        {
            if ($key === '') return (string)$default;
            $parts = explode('.', $key);
            $v = $this->translations;
            foreach ($parts as $p) {
                if (!is_array($v) || !array_key_exists($p, $v)) {
                    return (string)($default !== '' ? $default : $key);
                }
                $v = $v[$p];
            }
            if (is_array($v)) return (string)($default !== '' ? $default : json_encode($v, JSON_UNESCAPED_UNICODE));
            return (string)$v;
        }

        /**
         * Load translations from JSON files in all subdirectories of /languages
         */
        private function loadTranslations(bool $forceReload = false): void
        {
            $this->translations = [];
            $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') : null;
            if (!$docRoot) return;

            $languagesDir = $docRoot . '/languages';
            if (!is_dir($languagesDir)) return;

            // جمع جميع المجلدات الفرعية داخل /languages
            $allDirs = array_diff(scandir($languagesDir), ['.', '..']);
            $bases = [];
            foreach ($allDirs as $dir) {
                $fullDir = $languagesDir . '/' . $dir;
                if (is_dir($fullDir)) {
                    $bases[] = $fullDir;
                }
            }

            // ترتيب الأولوية: common أولاً، ثم scope إذا كان محددًا، ثم الباقي أبجديًا
            usort($bases, function ($a, $b) use ($languagesDir) {
                $aName = basename($a);
                $bName = basename($b);
                if ($aName === 'common') return -1;
                if ($bName === 'common') return 1;
                if ($this->scope !== '' && $aName === $this->scope) return -1;
                if ($this->scope !== '' && $bName === $this->scope) return 1;
                return strcmp($aName, $bName);
            });

            // اللغات: locale أولاً، ثم en إذا لم تكن en
            $codes = [$this->locale];
            if ($this->locale !== 'en') $codes[] = 'en';

            $merged = [];
            foreach ($bases as $base) {
                foreach ($codes as $code) {
                    $file = $base . '/' . $code . '.json';
                    if (!is_readable($file)) continue;

                    $cacheKey = 'i18n:' . md5($file);
                    $json = null;

                    // APCu cache
                    if ($this->useApcu && !$forceReload) {
                        $cached = apcu_fetch($cacheKey, $found);
                        if ($found && isset($cached['mtime'], $cached['data'])) {
                            $mtime = @filemtime($file);
                            if ($mtime !== false && $mtime == $cached['mtime']) {
                                $json = $cached['data'];
                            }
                        }
                    }

                    if ($json === null) {
                        $contents = @file_get_contents($file);
                        if ($contents === false) continue;
                        $decoded = @json_decode($contents, true);
                        if (!is_array($decoded)) continue;
                        $json = $decoded;

                        if ($this->useApcu) {
                            $mtime = @filemtime($file);
                            apcu_store($cacheKey, ['mtime' => $mtime, 'data' => $json], $this->cacheTtl);
                        }
                    }

                    if (is_array($json)) {
                        $merged = array_replace_recursive($merged, $json);
                    }
                }
            }

            $this->translations = $merged;
        }
    }
}
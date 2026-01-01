<?php
// htdocs/api/helpers/i18n.php
// Global I18n helper with per-scope translations and caching.
// Usage:
//   $i18n = new I18n(null, 'IndependentDriver');
//   echo $i18n->t('page.title');

if (!class_exists('I18n')) {
    class I18n
    {
        private string $locale;
        private array $translations = [];
        private string $direction = 'ltr';
        private string $scope = 'admin'; // default scope
        private bool $useApcu = false;
        private int $cacheTtl = 300; // seconds

        // RTL languages
        private static array $rtl = ['ar','he','fa','ur'];

        /**
         * Constructor
         * @param string|null $locale
         * @param string $scope
         */
        public function __construct(?string $locale = null, string $scope = 'admin')
        {
            if (session_status() === PHP_SESSION_NONE) @session_start();

            $this->useApcu = function_exists('apcu_fetch') && ini_get('apc.enabled') !== '0';
            $this->scope   = $this->normalizeScope($scope);
            $this->locale  = $this->determineLocale($locale);
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
         * Normalize scope name
         */
        private function normalizeScope(string $scope): string
        {
            $scope = strtolower(trim($scope));
            $scope = preg_replace('/[^a-z0-9_\-]/', '', $scope);
            return $scope === '' ? 'admin' : $scope;
        }

        /**
         * Determine locale: explicit > session > default 'en'
         */
        private function determineLocale(?string $locale): string
        {
            if (!empty($locale)) return $this->normalizeCode($locale);
            if (!empty($_SESSION['preferred_language'])) return $this->normalizeCode($_SESSION['preferred_language']);
            return 'en';
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
         * Load translations from JSON files
         */
        private function loadTranslations(bool $forceReload = false): void
        {
            $this->translations = [];
            $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') : null;
            if (!$docRoot) return;

            // Files to check
            $bases = [
                $docRoot . '/languages/' . $this->scope, // page-specific
                $docRoot . '/languages/common'          // shared
            ];

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

<?php
declare(strict_types=1);

final class ImagesValidator
{
    private array $allowedVisibilities = ['private', 'public'];
    private int $maxFileSize = 10 * 1024 * 1024; // 10MB

    private array $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
    ];

    private array $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

    /**
     * Validate image data
     */
    public function validate(array $data, string $mode = 'create'): array
    {
        $errors = [];

        /* =========================
         * Required / Conditional
         * ========================= */

        // owner_id is always required
        if (empty($data['owner_id']) || !is_numeric($data['owner_id']) || (int)$data['owner_id'] <= 0) {
            $errors['owner_id'] = 'owner_id is required and must be a positive integer';
        }

        // image_type_id required only on CREATE
        if ($mode === 'create') {
            if (empty($data['image_type_id']) || !is_numeric($data['image_type_id']) || (int)$data['image_type_id'] <= 0) {
                $errors['image_type_id'] = 'image_type_id is required and must be a positive integer';
            }
        } else {
            // On UPDATE: validate only if provided
            if (array_key_exists('image_type_id', $data) && $data['image_type_id'] !== '' && 
                (!is_numeric($data['image_type_id']) || (int)$data['image_type_id'] <= 0)) {
                $errors['image_type_id'] = 'image_type_id must be a positive integer';
            }
        }

        /* =========================
         * URL validation
         * ========================= */

        if (!empty($data['url'])) {
            if (!$this->isValidUrlOrPath($data['url'])) {
                $errors['url'] = 'url must be a valid URL or start with /';
            }
        }

        if (!empty($data['thumb_url'])) {
            if (!$this->isValidUrlOrPath($data['thumb_url'])) {
                $errors['thumb_url'] = 'thumb_url must be a valid URL or start with /';
            }
        }

        /* =========================
         * Visibility
         * ========================= */

        if (isset($data['visibility']) && !in_array($data['visibility'], $this->allowedVisibilities, true)) {
            $errors['visibility'] = 'visibility must be one of: ' . implode(', ', $this->allowedVisibilities);
        }

        /* =========================
         * Boolean / Numeric guards
         * ========================= */

        if (isset($data['is_main']) && !in_array((string)$data['is_main'], ['0', '1'], true)) {
            $errors['is_main'] = 'is_main must be 0 or 1';
        }

        if (isset($data['sort_order']) && !is_numeric($data['sort_order'])) {
            $errors['sort_order'] = 'sort_order must be numeric';
        }

        // File size validation
        if (isset($data['size']) && (!is_numeric($data['size']) || $data['size'] <= 0)) {
            $errors['size'] = 'size must be a positive number';
        }

        return $errors;
    }

    /**
     * Validate uploaded image files
     */
    public function validateFiles(array $files): array
    {
        $errors = [];

        if (empty($files['tmp_name'][0])) {
            return ['general' => 'No files uploaded'];
        }

        foreach ($files['tmp_name'] as $key => $tmpName) {
            if (empty($tmpName)) {
                continue;
            }

            $error = $files['error'][$key] ?? UPLOAD_ERR_NO_FILE;
            if ($error !== UPLOAD_ERR_OK) {
                $errors[$key] = $this->getUploadErrorMessage($error);
                continue;
            }

            $size = (int)($files['size'][$key] ?? 0);
            if ($size > $this->maxFileSize) {
                $errors[$key] = 'File exceeds max size of ' . $this->formatBytes($this->maxFileSize);
            }

            // MIME type validation
            $mime = $files['type'][$key] ?? '';
            if (!in_array($mime, $this->allowedMimeTypes, true)) {
                $errors[$key] = "File type '{$mime}' is not allowed";
            }

            // Extension validation
            $filename = $files['name'][$key] ?? '';
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!in_array($extension, $this->allowedExtensions, true)) {
                $errors[$key] = "File extension '{$extension}' is not allowed";
            }

            // Additional security check
            if (!$this->isValidImage($tmpName)) {
                $errors[$key] = 'File is not a valid image or may be corrupted';
            }
        }

        return $errors;
    }

    /**
     * Validate image dimensions and aspect ratio
     */
    public function validateImageDimensions(string $filePath, ?int $minWidth = null, ?int $minHeight = null, ?float $aspectRatio = null): array
    {
        $errors = [];
        
        if (!file_exists($filePath)) {
            return ['file' => 'File does not exist'];
        }

        $imageInfo = @getimagesize($filePath);
        if (!$imageInfo) {
            return ['image' => 'Cannot read image dimensions'];
        }

        list($width, $height) = $imageInfo;

        if ($minWidth !== null && $width < $minWidth) {
            $errors['width'] = "Minimum width required: {$minWidth}px (actual: {$width}px)";
        }

        if ($minHeight !== null && $height < $minHeight) {
            $errors['height'] = "Minimum height required: {$minHeight}px (actual: {$height}px)";
        }

        if ($aspectRatio !== null && $width > 0 && $height > 0) {
            $currentRatio = $width / $height;
            $tolerance = 0.1; // 10% tolerance
            if (abs($currentRatio - $aspectRatio) > $tolerance) {
                $errors['aspect_ratio'] = "Aspect ratio must be approximately {$aspectRatio} (actual: {$currentRatio})";
            }
        }

        return $errors;
    }

    /* =========================
     * Helpers
     * ========================= */

    private function isValidUrlOrPath(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false || str_starts_with($value, '/');
    }

    private function getUploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE     => 'File exceeds upload limit',
            UPLOAD_ERR_FORM_SIZE   => 'File exceeds form limit',
            UPLOAD_ERR_PARTIAL     => 'File partially uploaded',
            UPLOAD_ERR_NO_FILE     => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR  => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE  => 'Failed to write file',
            UPLOAD_ERR_EXTENSION   => 'PHP extension stopped upload',
            default                => 'Unknown upload error',
        };
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    private function isValidImage(string $filePath): bool
{
    $imageInfo = @getimagesize($filePath);
    if ($imageInfo === false) {
        return false;
    }

    $imageType = $imageInfo[2];
    $supportedTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
    
    // إضافة دعم لـ SVG دون الاعتماد على الثابت غير الموجود
    $mime = $imageInfo['mime'] ?? '';
    if ($mime === 'image/svg+xml' || $mime === 'image/svg') {
        return true;
    }

    return in_array($imageType, $supportedTypes, true);
}

    /**
     * Get maximum file size
     */
    public function getMaxFileSize(): int
    {
        return $this->maxFileSize;
    }

    /**
     * Get allowed MIME types
     */
    public function getAllowedMimeTypes(): array
    {
        return $this->allowedMimeTypes;
    }

    /**
     * Get allowed extensions
     */
    public function getAllowedExtensions(): array
    {
        return $this->allowedExtensions;
    }
}
?>
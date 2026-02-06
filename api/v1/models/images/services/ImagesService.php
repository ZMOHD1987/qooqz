<?php
declare(strict_types=1);

final class ImagesService
{
    private PdoImagesRepository $repo;
    private ImagesValidator $validator;
    private ImageProcessor $imageProcessor;
    private PDO $pdo;

    public function __construct(PdoImagesRepository $repo, ImagesValidator $validator, PDO $pdo)
    {
        $this->repo = $repo;
        $this->validator = $validator;
        $this->imageProcessor = new ImageProcessor();
        $this->pdo = $pdo;
    }

    /* ===================== LIST / GET ===================== */
    public function list(int $tenantId, ?string $filename = null, ?int $ownerId = null, 
                         ?int $imageTypeId = null, ?string $visibility = null, 
                         ?int $userId = null, int $page = 1, int $limit = 25): array
    {
        return $this->repo->all($tenantId, $ownerId, $imageTypeId, $visibility, $filename, $userId, $page, $limit);
    }

    public function get(int $tenantId, int $id): array
    {
        $row = $this->repo->find($tenantId, $id);
        if (!$row) {
            throw new RuntimeException('Image not found');
        }
        return $row;
    }

    public function getByOwner(int $tenantId, int $ownerId, int $imageTypeId): array
    {
        return $this->repo->getByOwner($tenantId, $ownerId, $imageTypeId);
    }

    public function getMainImage(int $tenantId, int $ownerId, int $imageTypeId): ?array
    {
        return $this->repo->getMainImage($tenantId, $ownerId, $imageTypeId);
    }

    /* ===================== CREATE / SAVE ===================== */
    public function save(int $tenantId, array $data, ?int $userId = null): int
    {
        // إزالة الحقول غير المرغوب فيها
        $unwantedFields = ['csrf_token', 'entity', '_method', 'image_type_display'];
        foreach ($unwantedFields as $f) { 
            unset($data[$f]); 
        }

        $errors = $this->validator->validate($data, !empty($data['id']) ? 'update' : 'create');
        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        return $this->repo->save($tenantId, $data, $userId);
    }

    /* ===================== UPLOAD (FIXED VERSION) ===================== */
    public function upload(int $tenantId, array $data, array $files, ?int $userId = null): array
    {
        if (empty($files['tmp_name'][0])) {
            throw new InvalidArgumentException('No files uploaded');
        }
        
        $fileErrors = $this->validator->validateFiles($files);
        if (!empty($fileErrors)) {
            throw new InvalidArgumentException('File validation failed: ' . json_encode($fileErrors));
        }

        // Get image type settings
        $imageTypeId = (int)($data['image_type_id'] ?? 0);
        if ($imageTypeId <= 0) {
            throw new InvalidArgumentException('Image type ID is required');
        }

        $imageType = $this->repo->getImageType($imageTypeId);
        if (!$imageType) {
            throw new RuntimeException('Image type not found');
        }

        $uploadedImages = [];
        $entity = $data['entity'] ?? 'general';
        $ownerId = (int)($data['owner_id'] ?? 0);
        
        if ($ownerId <= 0) {
            throw new InvalidArgumentException('Owner ID is required');
        }

        // ✅ التحقق إذا كان المالك لديه صورة رئيسية بالفعل
        $hasMainImage = $this->repo->getMainImage($tenantId, $ownerId, $imageTypeId) !== null;

        // Create upload directory
        $uploadDir = "/uploads/images/{$entity}/" . date('Y/m/d') . "/";
        $serverBase = $_SERVER['DOCUMENT_ROOT'] . '/admin';
        $fullDir = $serverBase . $uploadDir;
        
        if (!is_dir($fullDir)) {
            mkdir($fullDir, 0755, true);
        }

        foreach ($files['tmp_name'] as $key => $tmpName) {
            if (empty($tmpName)) continue;
            
            $originalName = $files['name'][$key];
            $mimeType = $files['type'][$key];
            $size = $files['size'][$key];
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            
            // ✅ توليد اسم فريد حقاً باستخدام مزيج من الوقت والعشوائية
            $microtime = microtime(true);
            $microtimeStr = str_replace('.', '', (string)$microtime);
            $randomStr = bin2hex(random_bytes(4));
            $uniqueId = 'img_' . $microtimeStr . '_' . $randomStr;
            
            $baseFilename = $uniqueId;
            $filename = $baseFilename . '.' . $extension;
            $serverPath = $fullDir . $filename;
            
            // Move uploaded file
            if (!move_uploaded_file($tmpName, $serverPath)) {
                throw new RuntimeException("Failed to move uploaded file: {$originalName}");
            }

            try {
                // Process image according to image type settings
                $processedInfo = $this->processImageFile($serverPath, $imageType, $baseFilename);
                
                // Update paths if processing was successful
                if ($processedInfo && $processedInfo['processed_path'] !== $serverPath) {
                    // Delete original if different format/size
                    if (file_exists($serverPath)) {
                        unlink($serverPath);
                    }
                    $serverPath = $processedInfo['processed_path'];
                    $filename = basename($processedInfo['processed_path']);
                    $mimeType = $processedInfo['mime_type'];
                    $size = filesize($serverPath);
                }

                // Web path
                $webPath = $uploadDir . $filename;

                // Create thumbnail if needed
                $thumbPath = null;
                if ($imageType['is_thumbnail'] == 0) {
                    // Create thumbnail version
                    $thumbInfo = $this->createThumbnail($serverPath, $baseFilename);
                    if ($thumbInfo) {
                        $thumbPath = $uploadDir . $thumbInfo['filename'];
                    }
                }

                // ✅ Prepare image data with is_main = 0 ALWAYS during upload
                $imageData = [
                    'owner_id' => $ownerId,
                    'image_type_id' => $imageTypeId,
                    'tenant_id' => $tenantId,
                    'user_id' => $userId ?? (int)($data['user_id'] ?? 0),
                    'filename' => $originalName,
                    'url' => '/admin' . $webPath,
                    'thumb_url' => $thumbPath ? ('/admin' . $thumbPath) : ('/admin' . $webPath),
                    'mime_type' => $mimeType,
                    'size' => $size,
                    'visibility' => $data['visibility'] ?? 'private',
                    'is_main' => 0, // ✅ ALWAYS 0 during upload
                    'sort_order' => (int)($data['sort_order'] ?? 0),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                // ✅ إدراج مباشر باستخدام insertDirect
                $id = $this->repo->insertDirect($imageData);
                $savedImage = $this->repo->find($tenantId, $id);
                
                if ($savedImage) {
                    $uploadedImages[] = $savedImage;
                }
                
            } catch (Throwable $e) {
                // Clean up files on error
                if (file_exists($serverPath)) {
                    unlink($serverPath);
                }
                if (isset($thumbPath) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/admin' . $thumbPath)) {
                    unlink($_SERVER['DOCUMENT_ROOT'] . '/admin' . $thumbPath);
                }
                throw new RuntimeException("Failed to process image {$originalName}: " . $e->getMessage());
            }
        }

        // ✅ بعد تحميل جميع الصور، إذا لم يكن هناك صورة رئيسية، اجعل أول صورة رئيسية
        if (!$hasMainImage && !empty($uploadedImages)) {
            $firstImage = reset($uploadedImages);
            $this->repo->setMain($tenantId, $ownerId, $imageTypeId, $firstImage['id'], $userId);
            
            // تحديث حالة is_main في المصفوفة المعادة
            $firstImage['is_main'] = 1;
        }

        return $uploadedImages;
    }

    /* ===================== IMAGE PROCESSING ===================== */
    private function processImageFile(string $sourcePath, array $imageType, string $baseFilename): array
    {
        if (!extension_loaded('gd')) {
            // Return original if GD not available
            $size = getimagesize($sourcePath);
            return [
                'processed_path' => $sourcePath,
                'mime_type' => $size['mime'] ?? mime_content_type($sourcePath)
            ];
        }

        $targetWidth = max(1, (int)$imageType['width']);
        $targetHeight = max(1, (int)$imageType['height']);
        $quality = max(1, min(100, (int)$imageType['quality']));
        $format = strtolower($imageType['format'] ?? 'webp');
        $cropMode = $imageType['crop'] ?? 'cover';

        // If dimensions are 0, use original dimensions
        if ($targetWidth <= 0 || $targetHeight <= 0) {
            $size = getimagesize($sourcePath);
            return [
                'processed_path' => $sourcePath,
                'mime_type' => $size['mime'] ?? mime_content_type($sourcePath)
            ];
        }

        // Process image
        $processedPath = $this->imageProcessor->process(
            $sourcePath,
            $targetWidth,
            $targetHeight,
            $cropMode,
            $quality,
            $format,
            dirname($sourcePath),
            $baseFilename
        );

        // Get new mime type
        $size = getimagesize($processedPath);

        return [
            'processed_path' => $processedPath,
            'mime_type' => $size['mime']
        ];
    }

    private function createThumbnail(string $sourcePath, string $baseFilename): ?array
    {
        // Default thumbnail settings
        $thumbSettings = [
            'width' => 300,
            'height' => 300,
            'quality' => 80,
            'format' => 'webp',
            'crop' => 'cover'
        ];

        try {
            $thumbPath = $this->imageProcessor->process(
                $sourcePath,
                $thumbSettings['width'],
                $thumbSettings['height'],
                $thumbSettings['crop'],
                $thumbSettings['quality'],
                $thumbSettings['format'],
                dirname($sourcePath),
                $baseFilename . '_thumb'
            );

            return [
                'filename' => basename($thumbPath),
                'path' => $thumbPath
            ];
        } catch (Throwable $e) {
            // Log error but don't fail the upload
            error_log("Thumbnail creation failed: " . $e->getMessage());
            return null;
        }
    }

    /* ===================== DELETE ===================== */
    public function delete(int $tenantId, int $id, ?int $userId = null): void
    {
        $image = $this->repo->find($tenantId, $id);
        if (!$image) {
            throw new RuntimeException('Image not found');
        }

        // Delete physical files
        $this->deleteImageFiles($image);

        // Delete from database
        if (!$this->repo->delete($tenantId, $id, $userId)) {
            throw new RuntimeException('Failed to delete image from database');
        }

        // If this was the main image, set another image as main
        if ($image['is_main'] == 1) {
            $this->setNewMainImage($tenantId, $image['owner_id'], $image['image_type_id']);
        }
    }

    public function deleteMultiple(int $tenantId, array $ids, ?int $userId = null): void
    {
        $imagesToDelete = [];
        
        // First, collect all images to delete their files
        foreach ($ids as $id) {
            try {
                $image = $this->repo->find($tenantId, (int)$id);
                if ($image) {
                    $imagesToDelete[] = $image;
                    $this->deleteImageFiles($image);
                }
            } catch (Throwable $e) {
                // Continue with other images even if one fails
                error_log("Failed to delete image {$id}: " . $e->getMessage());
            }
        }
        
        // Delete from database
        if (!$this->repo->deleteMultiple($tenantId, $ids)) {
            throw new RuntimeException('Failed to delete images from database');
        }
        
        // Check if we need to set new main images
        $ownersNeedingMainImage = [];
        foreach ($imagesToDelete as $image) {
            if ($image['is_main'] == 1) {
                $key = $image['tenant_id'] . '-' . $image['owner_id'] . '-' . $image['image_type_id'];
                if (!isset($ownersNeedingMainImage[$key])) {
                    $ownersNeedingMainImage[$key] = [
                        'tenant_id' => $image['tenant_id'],
                        'owner_id' => $image['owner_id'],
                        'image_type_id' => $image['image_type_id']
                    ];
                }
            }
        }
        
        // Set new main images for owners who lost their main image
        foreach ($ownersNeedingMainImage as $ownerData) {
            $this->setNewMainImage($ownerData['tenant_id'], $ownerData['owner_id'], $ownerData['image_type_id']);
        }
    }

    public function deleteByOwner(int $tenantId, int $ownerId, int $imageTypeId, ?int $userId = null): void
    {
        $images = $this->repo->getByOwner($tenantId, $ownerId, $imageTypeId);
        
        // Delete physical files
        foreach ($images as $image) {
            $this->deleteImageFiles($image);
        }
        
        // Delete from database
        $this->repo->deleteByOwner($tenantId, $ownerId, $imageTypeId, $userId);
    }

    private function deleteImageFiles(array $image): void
    {
        $basePath = $_SERVER['DOCUMENT_ROOT'];
        
        // Delete main image
        if (!empty($image['url']) && str_starts_with($image['url'], '/')) {
            $filePath = $basePath . $image['url'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        
        // Delete thumbnail
        if (!empty($image['thumb_url']) && $image['thumb_url'] !== $image['url'] && str_starts_with($image['thumb_url'], '/')) {
            $thumbPath = $basePath . $image['thumb_url'];
            if (file_exists($thumbPath)) {
                unlink($thumbPath);
            }
        }
    }

    private function setNewMainImage(int $tenantId, int $ownerId, int $imageTypeId): void
    {
        $images = $this->repo->getByOwner($tenantId, $ownerId, $imageTypeId);
        if (!empty($images)) {
            // Set first image as main
            $firstImage = reset($images);
            $this->repo->setMain($tenantId, $ownerId, $imageTypeId, $firstImage['id']);
        }
    }

    /* ===================== MAIN IMAGE ===================== */
    public function setMain(int $tenantId, int $ownerId, int $imageTypeId, int $imageId, ?int $userId = null): void
    {
        // Verify the image exists and belongs to the specified owner
        $image = $this->repo->find($tenantId, $imageId);
        if (!$image) {
            throw new RuntimeException('Image not found');
        }
        
        if ($image['owner_id'] != $ownerId || $image['image_type_id'] != $imageTypeId) {
            throw new RuntimeException('Image does not belong to specified owner and type');
        }
        
        $this->repo->setMain($tenantId, $ownerId, $imageTypeId, $imageId, $userId);
    }

    /* ===================== IMAGE TYPES ===================== */
    public function getImageTypes(): array
    {
        return $this->repo->getAllImageTypes();
    }

    public function getImageType(int $imageTypeId): array
    {
        $type = $this->repo->getImageType($imageTypeId);
        if (!$type) {
            throw new RuntimeException('Image type not found');
        }
        return $type;
    }
}
?>

<?php
final class ImageProcessor
{
    public function process(
        string $sourcePath,
        int $targetWidth,
        int $targetHeight,
        string $cropMode = 'cover',
        int $quality = 85,
        string $format = 'webp',
        string $outputDir = null,
        string $baseFilename = null
    ): string {
        if (!file_exists($sourcePath)) {
            throw new RuntimeException("Source file not found: {$sourcePath}");
        }

        // Get original image info
        $imageInfo = @getimagesize($sourcePath);
        if (!$imageInfo) {
            throw new RuntimeException("Invalid image file: {$sourcePath}");
        }

        list($originalWidth, $originalHeight, $imageType) = $imageInfo;

        // Create image resource
        $sourceImage = $this->createImageResource($sourcePath, $imageType);
        if (!$sourceImage) {
            throw new RuntimeException("Failed to create image resource");
        }

        // Calculate dimensions
        list($newWidth, $newHeight, $srcX, $srcY, $dstX, $dstY) = 
            $this->calculateDimensions($originalWidth, $originalHeight, $targetWidth, $targetHeight, $cropMode);

        // Create new image
        $newImage = imagecreatetruecolor($targetWidth, $targetHeight);
        
        // Handle transparency
        $this->handleTransparency($newImage, $imageType);

        // Resize and crop
        imagecopyresampled(
            $newImage, $sourceImage,
            $dstX, $dstY, $srcX, $srcY,
            $newWidth, $newHeight, $originalWidth, $originalHeight
        );

        // Generate output filename
        if (!$outputDir) {
            $outputDir = dirname($sourcePath);
        }
        
        if (!$baseFilename) {
            $baseFilename = pathinfo($sourcePath, PATHINFO_FILENAME);
        }
        
        $outputPath = $outputDir . '/' . $baseFilename . '_' . $targetWidth . 'x' . $targetHeight . '.' . $format;

        // Save image
        $this->saveImage($newImage, $outputPath, $format, $quality);

        // Clean up
        imagedestroy($sourceImage);
        imagedestroy($newImage);

        return $outputPath;
    }

    private function createImageResource(string $path, int $imageType)
    {
        return match ($imageType) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => imagecreatefrompng($path),
            IMAGETYPE_GIF  => imagecreatefromgif($path),
            IMAGETYPE_WEBP => imagecreatefromwebp($path),
            default => false
        };
    }

    private function calculateDimensions(int $origW, int $origH, int $targW, int $targH, string $mode): array
    {
        $srcX = $srcY = $dstX = $dstY = 0;
        $newW = $targW;
        $newH = $targH;

        switch ($mode) {
            case 'cover':
                $ratio = max($targW / $origW, $targH / $origH);
                $newW = $origW * $ratio;
                $newH = $origH * $ratio;
                $srcX = ($newW - $targW) / 2;
                $srcY = ($newH - $targH) / 2;
                break;
                
            case 'contain':
                $ratio = min($targW / $origW, $targH / $origH);
                $newW = $origW * $ratio;
                $newH = $origH * $ratio;
                $dstX = ($targW - $newW) / 2;
                $dstY = ($targH - $newH) / 2;
                break;
                
            case 'fill':
                // Stretch to fill
                break;
                
            case 'crop':
                $srcRatio = $origW / $origH;
                $dstRatio = $targW / $targH;
                
                if ($srcRatio > $dstRatio) {
                    // Original is wider
                    $cropW = $origH * $dstRatio;
                    $srcX = ($origW - $cropW) / 2;
                    $origW = $cropW;
                } else {
                    // Original is taller
                    $cropH = $origW / $dstRatio;
                    $srcY = ($origH - $cropH) / 2;
                    $origH = $cropH;
                }
                $newW = $targW;
                $newH = $targH;
                break;
                
            case 'scale':
            default:
                $newW = $targW;
                $newH = $targH;
        }
        
        // 🔴 تحويل جميع القيم إلى أعداد صحيحة
        return [
            (int)round($newW),   // newWidth
            (int)round($newH),   // newHeight
            (int)round($srcX),   // srcX
            (int)round($srcY),   // srcY
            (int)round($dstX),   // dstX
            (int)round($dstY)    // dstY
        ];
    }

    private function handleTransparency($image, int $imageType): void
    {
        if ($imageType === IMAGETYPE_PNG || $imageType === IMAGETYPE_GIF) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
            imagefill($image, 0, 0, $transparent);
        } else {
            $white = imagecolorallocate($image, 255, 255, 255);
            imagefill($image, 0, 0, $white);
        }
    }

    private function saveImage($image, string $path, string $format, int $quality): void
    {
        switch (strtolower($format)) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($image, $path, $quality);
                break;
            case 'png':
                imagepng($image, $path, round(9 * $quality / 100));
                break;
            case 'gif':
                imagegif($image, $path);
                break;
            case 'webp':
                imagewebp($image, $path, $quality);
                break;
            default:
                throw new RuntimeException("Unsupported format: {$format}");
        }
    }
}
?>
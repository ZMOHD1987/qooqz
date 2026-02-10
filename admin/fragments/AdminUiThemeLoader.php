<?php
declare(strict_types=1);

/**
 * مكون تحميل النسق - نسخة مبسطة تعمل
 */
final class AdminUiThemeLoader
{
    private PDO $pdo;
    private int $tenantId;

    public function __construct(PDO $pdo, int $tenantId = null)
    {
        $this->pdo = $pdo;
        $this->tenantId = $tenantId ?? ($_SESSION['tenant_id'] ?? 1);
    }

    /**
     * تحميل وتطبيق النسق الحالي
     */
    public function load(): void
    {
        try {
            $themeId = $this->getActiveThemeId();
            if (!$themeId) {
                $this->loadDefault();
                return;
            }

            $theme = $this->getTheme($themeId);
            if (!$theme) {
                $this->loadDefault();
                return;
            }

            $this->applyTheme($themeId);
            
        } catch (Throwable $e) {
            error_log("ThemeLoader Error: " . $e->getMessage());
            $this->loadDefault();
        }
    }

    /**
     * الحصول على معرف النسق النشط
     */
    private function getActiveThemeId(): ?int
    {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM themes 
             WHERE tenant_id = ? AND is_active = 1 
             LIMIT 1"
        );
        $stmt->execute([$this->tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? (int)$row['id'] : null;
    }

    /**
     * الحصول على بيانات النسق
     */
    private function getTheme(int $themeId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM themes 
             WHERE id = ? AND tenant_id = ?
             LIMIT 1"
        );
        $stmt->execute([$themeId, $this->tenantId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * تطبيق النسق
     */
    private function applyTheme(int $themeId): void
    {
        // توليد CSS
        $css = $this->generateCss($themeId);
        
        // تطبيق CSS
        echo '<style id="admin-ui-theme">' . htmlspecialchars($css) . '</style>';
        
        // حقن بيانات النسق لاستخدامها في JavaScript
        $themeData = $this->getFullThemeData($themeId);
        echo '<script id="theme-data">';
        echo 'window.THEME_DATA = ' . json_encode($themeData, JSON_UNESCAPED_UNICODE) . ';';
        echo '</script>';
    }

    /**
     * توليد CSS من إعدادات النسق
     */
    private function generateCss(int $themeId): string
    {
        $data = $this->getFullThemeData($themeId);
        
        $css = ":root {\n";
        
        // الألوان
        foreach ($data['color_settings'] ?? [] as $color) {
            if (!empty($color['setting_key']) && !empty($color['color_value'])) {
                $varName = $this->normalizeVarName($color['setting_key']);
                $css .= "  --{$varName}: {$color['color_value']};\n";
            }
        }
        
        // الخطوط
        foreach ($data['font_settings'] ?? [] as $font) {
            if (!empty($font['setting_key']) && !empty($font['font_family'])) {
                $varName = $this->normalizeVarName($font['setting_key']);
                $css .= "  --font-{$varName}: {$font['font_family']};\n";
                
                if (!empty($font['font_size'])) {
                    $css .= "  --font-{$varName}-size: {$font['font_size']};\n";
                }
                if (!empty($font['font_weight'])) {
                    $css .= "  --font-{$varName}-weight: {$font['font_weight']};\n";
                }
            }
        }
        
        $css .= "}\n";
        
        // الأزرار
        foreach ($data['button_styles'] ?? [] as $button) {
            if (!empty($button['slug'])) {
                $css .= ".btn-{$button['slug']} {\n";
                if (!empty($button['background_color'])) {
                    $css .= "  background-color: {$button['background_color']};\n";
                }
                if (!empty($button['text_color'])) {
                    $css .= "  color: {$button['text_color']};\n";
                }
                if (!empty($button['border_color']) && !empty($button['border_width'])) {
                    $css .= "  border: {$button['border_width']}px solid {$button['border_color']};\n";
                }
                if (!empty($button['border_radius'])) {
                    $css .= "  border-radius: {$button['border_radius']}px;\n";
                }
                if (!empty($button['padding'])) {
                    $css .= "  padding: {$button['padding']};\n";
                }
                if (!empty($button['font_size'])) {
                    $css .= "  font-size: {$button['font_size']};\n";
                }
                if (!empty($button['font_weight'])) {
                    $css .= "  font-weight: {$button['font_weight']};\n";
                }
                $css .= "}\n";
            }
        }
        
        // البطاقات
        foreach ($data['card_styles'] ?? [] as $card) {
            if (!empty($card['slug'])) {
                $css .= ".card-{$card['slug']} {\n";
                if (!empty($card['background_color'])) {
                    $css .= "  background-color: {$card['background_color']};\n";
                }
                if (!empty($card['border_color']) && !empty($card['border_width'])) {
                    $css .= "  border: {$card['border_width']}px solid {$card['border_color']};\n";
                }
                if (!empty($card['border_radius'])) {
                    $css .= "  border-radius: {$card['border_radius']}px;\n";
                }
                if (!empty($card['shadow_style']) && $card['shadow_style'] !== 'none') {
                    $css .= "  box-shadow: {$card['shadow_style']};\n";
                }
                if (!empty($card['padding'])) {
                    $css .= "  padding: {$card['padding']};\n";
                }
                if (!empty($card['text_align'])) {
                    $css .= "  text-align: {$card['text_align']};\n";
                }
                $css .= "}\n";
            }
        }
        
        return $css;
    }

    /**
     * الحصول على كافة بيانات النسق
     */
    private function getFullThemeData(int $themeId): array
    {
        $data = ['theme_id' => $themeId];
        
        // الألوان
        $stmt = $this->pdo->prepare(
            "SELECT * FROM color_settings 
             WHERE theme_id = ? AND tenant_id = ?
             ORDER BY category, sort_order"
        );
        $stmt->execute([$themeId, $this->tenantId]);
        $data['color_settings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // الخطوط
        $stmt = $this->pdo->prepare(
            "SELECT * FROM font_settings 
             WHERE theme_id = ? AND tenant_id = ?
             ORDER BY category, sort_order"
        );
        $stmt->execute([$themeId, $this->tenantId]);
        $data['font_settings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // الأزرار
        $stmt = $this->pdo->prepare(
            "SELECT * FROM button_styles 
             WHERE theme_id = ? AND tenant_id = ?
             ORDER BY button_type, name"
        );
        $stmt->execute([$themeId, $this->tenantId]);
        $data['button_styles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // البطاقات
        $stmt = $this->pdo->prepare(
            "SELECT * FROM card_styles 
             WHERE theme_id = ? AND tenant_id = ?
             ORDER BY card_type, name"
        );
        $stmt->execute([$themeId, $this->tenantId]);
        $data['card_styles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $data;
    }

    /**
     * تطبيع اسم المتغير
     */
    private function normalizeVarName(string $name): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $name));
    }

    /**
     * تحميل النسق الافتراضي
     */
    private function loadDefault(): void
    {
        echo '<style id="admin-ui-default-theme">';
        echo ':root {';
        echo '  --primary-color: #3b82f6;';
        echo '  --secondary-color: #64748b;';
        echo '  --success-color: #10b981;';
        echo '  --danger-color: #ef4444;';
        echo '  --warning-color: #f59e0b;';
        echo '  --info-color: #0ea5e9;';
        echo '  --background-color: #0f172a;';
        echo '  --surface-color: #1e293b;';
        echo '  --text-primary: #f8fafc;';
        echo '  --text-secondary: #94a3b8;';
        echo '  --border-color: #334155;';
        echo '  --border-radius: 8px;';
        echo '  --font-family: "Inter", "Segoe UI", system-ui, sans-serif;';
        echo '}';
        
        echo '.btn-primary {';
        echo '  background-color: var(--primary-color);';
        echo '  color: white;';
        echo '  border-radius: var(--border-radius);';
        echo '  padding: 0.5rem 1rem;';
        echo '  border: none;';
        echo '  cursor: pointer;';
        echo '  font-weight: 500;';
        echo '}';
        
        echo '.btn-secondary {';
        echo '  background-color: var(--secondary-color);';
        echo '  color: white;';
        echo '  border-radius: var(--border-radius);';
        echo '  padding: 0.5rem 1rem;';
        echo '  border: none;';
        echo '  cursor: pointer;';
        echo '  font-weight: 500;';
        echo '}';
        
        echo '.card {';
        echo '  background-color: var(--surface-color);';
        echo '  border: 1px solid var(--border-color);';
        echo '  border-radius: 12px;';
        echo '  padding: 1.5rem;';
        echo '  margin-bottom: 1rem;';
        echo '}';
        
        echo '</style>';
    }

    /**
     * عرض واجهة إدارة النسق
     */
    public function renderManager(): string
    {
        ob_start();
        
        // الحصول على جميع النسق
        $themes = $this->getAllThemes();
        $activeThemeId = $this->getActiveThemeId();
        
        ?>
        <div class="theme-manager" id="themeManager">
            <div class="theme-manager-header">
                <h2>إدارة النسق</h2>
                <button class="btn btn-primary" onclick="ThemeManager.openCreateModal()">
                    <i class="fas fa-plus"></i> نسق جديد
                </button>
            </div>
            
            <div class="theme-grid">
                <?php foreach ($themes as $theme): ?>
                <div class="theme-card <?= $theme['id'] == $activeThemeId ? 'active' : '' ?>" data-theme-id="<?= $theme['id'] ?>">
                    <div class="theme-preview" style="background: <?= $theme['primary_color'] ?? '#3b82f6' ?>"></div>
                    <div class="theme-info">
                        <h4><?= htmlspecialchars($theme['name']) ?></h4>
                        <p><?= htmlspecialchars($theme['description'] ?? '') ?></p>
                        <div class="theme-meta">
                            <span class="badge <?= $theme['is_active'] ? 'badge-success' : 'badge-secondary' ?>">
                                <?= $theme['is_active'] ? 'نشط' : 'غير نشط' ?>
                            </span>
                            <span class="version">v<?= htmlspecialchars($theme['version'] ?? '1.0.0') ?></span>
                        </div>
                        <div class="theme-actions">
                            <button class="btn btn-sm btn-outline" onclick="ThemeManager.editTheme(<?= $theme['id'] ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if (!$theme['is_active']): ?>
                            <button class="btn btn-sm btn-success" onclick="ThemeManager.activateTheme(<?= $theme['id'] ?>)">
                                <i class="fas fa-check"></i>
                            </button>
                            <?php endif; ?>
                            <?php if (!$theme['is_default']): ?>
                            <button class="btn btn-sm btn-danger" onclick="ThemeManager.deleteTheme(<?= $theme['id'] ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($themes)): ?>
                <div class="empty-state">
                    <i class="fas fa-palette"></i>
                    <h3>لا توجد نسق</h3>
                    <p>ابدأ بإنشاء أول نسق لك</p>
                    <button class="btn btn-primary" onclick="ThemeManager.openCreateModal()">
                        <i class="fas fa-plus"></i> إنشاء نسق
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Modal إنشاء/تعديل نسق -->
        <div class="modal fade" id="themeModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">إنشاء نسق جديد</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                    </div>
                    <div class="modal-body">
                        <form id="themeForm">
                            <input type="hidden" id="themeId" name="id">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="themeName" class="form-label">اسم النسق</label>
                                        <input type="text" class="form-control" id="themeName" name="name" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="themeSlug" class="form-label">المعرف (Slug)</label>
                                        <input type="text" class="form-control" id="themeSlug" name="slug" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="themeDescription" class="form-label">الوصف</label>
                                <textarea class="form-control" id="themeDescription" name="description" rows="3"></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="themeVersion" class="form-label">الإصدار</label>
                                        <input type="text" class="form-control" id="themeVersion" name="version" value="1.0.0">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="themeAuthor" class="form-label">المؤلف</label>
                                        <input type="text" class="form-control" id="themeAuthor" name="author">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="themeIsActive" name="is_active" value="1">
                                    <label class="form-check-label" for="themeIsActive">تفعيل هذا النسق</label>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="button" class="btn btn-primary" onclick="ThemeManager.saveTheme()">حفظ</button>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        // جعل ThemeManager متاحًا عالميًا
        window.ThemeManager = {
            openCreateModal: function() {
                document.getElementById('modalTitle').textContent = 'إنشاء نسق جديد';
                document.getElementById('themeForm').reset();
                document.getElementById('themeId').value = '';
                new bootstrap.Modal(document.getElementById('themeModal')).show();
            },
            
            editTheme: function(id) {
                fetch(`/api/admin_ui/themes/${id}`)
                    .then(response => response.json())
                    .then(theme => {
                        document.getElementById('modalTitle').textContent = 'تعديل النسق';
                        document.getElementById('themeId').value = theme.id;
                        document.getElementById('themeName').value = theme.name;
                        document.getElementById('themeSlug').value = theme.slug;
                        document.getElementById('themeDescription').value = theme.description || '';
                        document.getElementById('themeVersion').value = theme.version || '1.0.0';
                        document.getElementById('themeAuthor').value = theme.author || '';
                        document.getElementById('themeIsActive').checked = theme.is_active == 1;
                        
                        new bootstrap.Modal(document.getElementById('themeModal')).show();
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('خطأ في تحميل البيانات');
                    });
            },
            
            saveTheme: function() {
                const form = document.getElementById('themeForm');
                const formData = new FormData(form);
                const data = Object.fromEntries(formData);
                
                const method = data.id ? 'PUT' : 'POST';
                const url = data.id ? `/api/admin_ui/themes/${data.id}` : '/api/admin_ui/themes';
                
                fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': '<?= $_SESSION['csrf_token'] ?? '' ?>'
                    },
                    body: JSON.stringify(data)
                })
                .then(response => response.json())
                .then(result => {
                    if (result.error) {
                        alert(result.error);
                        return;
                    }
                    
                    alert('تم الحفظ بنجاح');
                    bootstrap.Modal.getInstance(document.getElementById('themeModal')).hide();
                    location.reload();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('خطأ في الحفظ');
                });
            },
            
            activateTheme: function(id) {
                if (!confirm('هل تريد تفعيل هذا النسق؟')) return;
                
                fetch(`/api/admin_ui/themes/${id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': '<?= $_SESSION['csrf_token'] ?? '' ?>'
                    },
                    body: JSON.stringify({ is_active: 1 })
                })
                .then(response => response.json())
                .then(result => {
                    if (result.error) {
                        alert(result.error);
                        return;
                    }
                    
                    alert('تم التفعيل بنجاح');
                    location.reload();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('خطأ في التفعيل');
                });
            },
            
            deleteTheme: function(id) {
                if (!confirm('هل أنت متأكد من حذف هذا النسق؟')) return;
                
                fetch(`/api/admin_ui/themes/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-Token': '<?= $_SESSION['csrf_token'] ?? '' ?>'
                    }
                })
                .then(response => response.json())
                .then(result => {
                    if (result.error) {
                        alert(result.error);
                        return;
                    }
                    
                    alert('تم الحذف بنجاح');
                    location.reload();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('خطأ في الحذف');
                });
            }
        };
        </script>
        <?php
        
        return ob_get_clean();
    }

    /**
     * الحصول على جميع النسق
     */
    private function getAllThemes(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM themes 
             WHERE tenant_id = ?
             ORDER BY is_active DESC, is_default DESC, name ASC"
        );
        $stmt->execute([$this->tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// الطريقة المبسطة للاستخدام:
// 1. في أعلى الصفحة الرئيسية:
//    $themeLoader = new AdminUiThemeLoader($pdo);
//    $themeLoader->load();
//
// 2. لعرض واجهة الإدارة في صفحة منفصلة:
//    echo $themeLoader->renderManager();
?>
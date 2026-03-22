<?php
declare(strict_types=1);

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;

if ($isFragment) {
    require_once __DIR__ . '/../includes/admin_context.php';
} else {
    require_once __DIR__ . '/../includes/header.php';
}

if (!is_admin_logged_in()) {
    if ($isFragment) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    } else {
        header('Location: /admin/login.php');
        exit;
    }
}

$user = admin_user();
$lang = admin_lang();
$dir = admin_dir();

// Helper: get computed style of an element (simulate via JS)
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" dir="<?= htmlspecialchars($dir) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Theme Test</title>
    <style>
        body {
            font-family: monospace;
            padding: 20px;
            background: var(--background-main, #0a0a0a);
            color: var(--text-primary, #fff);
        }
        .card {
            background: var(--card-bg, #1e293b);
            border: 1px solid var(--border-color, #334155);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .success { color: var(--success-color, #10b981); }
        .error { color: var(--danger-color, #ef4444); }
        .warning { color: var(--warning-color, #f59e0b); }
        pre {
            background: rgba(0,0,0,0.3);
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            text-align: left;
            padding: 8px;
            border-bottom: 1px solid var(--border-color, #334155);
        }
        .btn-sample {
            display: inline-block;
            padding: 8px 16px;
            margin: 5px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="card">
        <h2>🎨 Admin Theme Test</h2>
        <p>This tool checks if the dynamic theming system is working correctly on the current page.</p>
    </div>

    <div class="card">
        <h3>📦 CSS Variables (from DB)</h3>
        <div id="cssVars"></div>
        <button id="btnCopyVars" class="btn btn-secondary">Copy Variables to Clipboard</button>
    </div>

    <div class="card">
        <h3>🔘 Button Styles (Dynamic Generation)</h3>
        <div id="buttonStylesStatus"></div>
        <div class="btn-sample btn-primary">Primary Button (should use --primary-color)</div>
        <div class="btn-sample btn-secondary">Secondary Button</div>
        <div class="btn-sample btn-danger">Danger Button</div>
        <div class="btn-sample btn-success">Success Button</div>
        <div class="btn-sample btn-warning">Warning Button</div>
        <div class="btn-sample btn-outline">Outline Button</div>
        <div class="btn-sample btn-link">Link Button</div>
        <div id="buttonComputedStyles"></div>
    </div>

    <div class="card">
        <h3>⚠️ Missing Common Variables</h3>
        <div id="missingVars"></div>
    </div>

    <div class="card">
        <h3>📄 Page Information</h3>
        <table id="pageInfo">
            <tr><th>Page URL</th><td id="pageUrl"></td></tr>
            <tr><th>Admin Context Loaded</th><td id="adminContext"></td></tr>
            <tr><th>Language</th><td><?= htmlspecialchars($lang) ?></td></tr>
            <tr><th>Direction</th><td><?= htmlspecialchars($dir) ?></td></tr>
            <tr><th>Tenant ID</th><td><?= admin_tenant_id() ?></td></tr>
            <tr><th>Super Admin?</th><td><?= is_super_admin() ? 'Yes' : 'No' ?></td></tr>
        </table>
    </div>

    <script>
        (function() {
            // Get computed root styles
            const root = getComputedStyle(document.documentElement);
            const rootStyles = {};

            // List of essential variables to check
            const essentialVars = [
                '--primary-color', '--primary-hover', '--danger-color', '--danger-hover',
                '--success-color', '--warning-color', '--info-color',
                '--text-primary', '--text-secondary',
                '--background-main', '--background-secondary',
                '--border-color', '--card-bg',
                '--border-radius', '--body-font-family'
            ];

            // Collect all variables defined on :root
            // Unfortunately, we can't get all defined variables easily; we can only get computed values.
            // We'll instead iterate over essential ones and also show some sample computed values.

            const varsDiv = document.getElementById('cssVars');
            const missingDiv = document.getElementById('missingVars');
            const buttonStylesStatus = document.getElementById('buttonStylesStatus');
            const computedStylesDiv = document.getElementById('buttonComputedStyles');

            // Check if dynamic button styles are present
            const buttonStyleTag = document.querySelector('style:contains("Dynamically generated button styles")');
            const buttonStylesPresent = document.querySelector('style')?.innerHTML?.includes('Dynamically generated button styles');
            if (buttonStylesPresent) {
                buttonStylesStatus.innerHTML = '<span class="success">✓ Dynamic button styles are present.</span>';
            } else {
                buttonStylesStatus.innerHTML = '<span class="error">✗ Dynamic button styles NOT found. Check header.php and database connection.</span>';
            }

            // Display essential variables and their values
            let varsHtml = '<table><tr><th>Variable</th><th>Value</th></tr>';
            let missingHtml = '<ul>';
            essentialVars.forEach(varName => {
                const val = root.getPropertyValue(varName).trim();
                if (val) {
                    varsHtml += `<tr><td><code>${varName}</code></td><td><code>${val}</code></td></tr>`;
                } else {
                    missingHtml += `<li><code>${varName}</code> is not defined</li>`;
                }
            });
            varsHtml += '</table>';
            missingHtml += '</ul>';
            varsDiv.innerHTML = varsHtml;
            missingDiv.innerHTML = missingHtml || '<p class="success">All essential variables are defined.</p>';

            // Display computed styles of sample buttons
            const btnPrimary = document.querySelector('.btn-primary');
            if (btnPrimary) {
                const styles = getComputedStyle(btnPrimary);
                computedStylesDiv.innerHTML = `
                    <h4>Sample .btn-primary computed styles:</h4>
                    <ul>
                        <li>background: ${styles.backgroundColor}</li>
                        <li>color: ${styles.color}</li>
                        <li>border: ${styles.border}</li>
                        <li>border-radius: ${styles.borderRadius}</li>
                        <li>padding: ${styles.padding}</li>
                        <li>font-size: ${styles.fontSize}</li>
                    </ul>
                `;
            } else {
                computedStylesDiv.innerHTML = '<p class="warning">No .btn-primary element found on this page.</p>';
            }

            // Page info
            document.getElementById('pageUrl').textContent = window.location.href;
            const adminContext = window.ADMIN_UI ? 'Yes' : 'No';
            document.getElementById('adminContext').textContent = adminContext;
            if (!window.ADMIN_UI) {
                document.getElementById('adminContext').style.color = 'var(--danger-color)';
            }

            // Copy variables to clipboard
            document.getElementById('btnCopyVars').addEventListener('click', () => {
                let text = '';
                essentialVars.forEach(varName => {
                    const val = root.getPropertyValue(varName).trim();
                    if (val) text += `${varName}: ${val}\n`;
                });
                navigator.clipboard.writeText(text).then(() => {
                    alert('Copied!');
                });
            });
        })();
    </script>
</body>
</html>
<?php
/**
 * Admin Export Template
 * Export registrations in various formats
 */

if (!defined('ABSPATH')) {
    exit;
}

$site_url = get_site_url();
$api_key  = get_option('fuse_api_key', '');
?>

<div class="wrap">
    <h1>Export Registrations</h1>

    <div id="fuse-export-content">
        <!-- Export Methods -->
        <div class="fuse-export-section">
            <h2>Export All Registrations</h2>
            <div class="fuse-export-options">
                <div class="fuse-export-card">
                    <h3>CSV Export</h3>
                    <p>Download all registrations as a CSV file for use in spreadsheet applications.</p>
                    <button class="button button-primary" id="export-csv-all">Export All as CSV</button>
                </div>
                <div class="fuse-export-card">
                    <h3>JSON Export</h3>
                    <p>Download all registrations as JSON format for integration with other systems.</p>
                    <button class="button button-primary" id="export-json-all">Export All as JSON</button>
                </div>
            </div>
        </div>

        <!-- Conexsys Export -->
        <div class="fuse-export-section">
            <h2>Conexsys Integration</h2>
            <div class="fuse-export-info">
                <p>Export registrations in Conexsys format and use the REST API endpoint for integration.</p>
                <button class="button button-primary" id="export-conexsys">Export for Conexsys</button>
            </div>

            <div class="fuse-api-endpoint">
                <h3>REST API Endpoint</h3>
                <p>Use this endpoint to fetch registrations in Conexsys format programmatically:</p>
                <div class="fuse-endpoint-url">
                    <code><?php echo esc_html($site_url); ?>/wp-json/fuse/v1/conexsys</code>
                    <button class="button button-secondary" id="copy-endpoint-url" title="Copy to clipboard">Copy</button>
                </div>
                <?php if (!empty($api_key)) : ?>
                <p class="fuse-endpoint-note">Quick test — open this URL in your browser:</p>
                <div class="fuse-endpoint-url" style="margin-bottom:8px;">
                    <code><?php echo esc_html($site_url); ?>/wp-json/fuse/v1/conexsys?api_key=<?php echo esc_attr($api_key); ?></code>
                    <button class="button button-secondary copy-url-btn" data-url="<?php echo esc_attr($site_url . '/wp-json/fuse/v1/conexsys?api_key=' . $api_key); ?>" title="Copy to clipboard">Copy</button>
                </div>
                <?php else : ?>
                <p class="fuse-endpoint-note" style="color:#d32f2f;">⚠ No API key set. Go to <strong>Fuse 2026 &rsaquo; Settings</strong> and add a Conexsys API Key first (make up any long random string).</p>
                <?php endif; ?>
                <p class="fuse-endpoint-note">The endpoint returns a flat list — one row per attendee including guests — ready for badge printing. Send your key in the <code>X-Fuse-API-Key</code> header or as <code>?api_key=</code> in the URL.</p>

                <?php if (!empty($api_key)) : ?>
                <div style="margin-top:16px;">
                    <button class="button button-secondary" id="test-conexsys-api">
                        Test API Connection
                    </button>
                    <span id="test-conexsys-spinner" style="display:none;margin-left:10px;color:#666;font-size:13px;">Testing&hellip;</span>
                </div>
                <div id="test-conexsys-result" style="display:none;margin-top:14px;padding:14px 16px;border-radius:4px;font-size:13px;line-height:1.6;"></div>
                <?php endif; ?>
            </div>

            <h3>Sample API Response Format</h3>
            <div class="fuse-sample-response">
                <pre><code id="sample-response-code"></code></pre>
            </div>
        </div>

        <!-- Export Status -->
        <div id="export-status" class="fuse-notice" style="display: none;"></div>
    </div>
</div>

<style>
    .fuse-export-section {
        background: #fff;
        border: 1px solid #ccc;
        border-radius: 4px;
        padding: 30px;
        margin-bottom: 30px;
    }

    .fuse-export-options {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }

    .fuse-export-card {
        background: #f9f9f9;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 20px;
    }

    .fuse-export-card h3 {
        margin-top: 0;
        color: #0073aa;
    }

    .fuse-export-card p {
        color: #666;
        font-size: 14px;
        margin-bottom: 20px;
    }

    .fuse-export-card .button {
        width: 100%;
        text-align: center;
    }

    .fuse-export-info {
        background: #f0f6ff;
        border-left: 4px solid #0073aa;
        padding: 20px;
        border-radius: 4px;
        margin-bottom: 20px;
    }

    .fuse-export-info p {
        color: #333;
        margin: 0 0 15px 0;
    }

    .fuse-export-info .button {
        margin-top: 10px;
    }

    .fuse-api-endpoint {
        background: #f9f9f9;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 20px;
        margin-top: 20px;
    }

    .fuse-api-endpoint h3 {
        margin-top: 0;
    }

    .fuse-endpoint-url {
        display: flex;
        align-items: center;
        gap: 10px;
        background: #fff;
        border: 1px solid #ccc;
        border-radius: 4px;
        padding: 10px;
        margin: 15px 0;
    }

    .fuse-endpoint-url code {
        flex: 1;
        word-break: break-all;
        font-family: monospace;
        color: #333;
        font-size: 13px;
    }

    .fuse-endpoint-url button {
        flex-shrink: 0;
    }

    .fuse-endpoint-note {
        font-size: 13px;
        color: #666;
        margin-top: 10px;
    }

    .fuse-sample-response {
        background: #f5f5f5;
        border: 1px solid #ccc;
        border-radius: 4px;
        padding: 15px;
        margin-top: 15px;
        overflow-x: auto;
    }

    .fuse-sample-response pre {
        margin: 0;
        font-family: monospace;
        font-size: 12px;
        line-height: 1.5;
    }

    .fuse-notice {
        padding: 20px;
        border-radius: 4px;
        margin-top: 20px;
    }

    .fuse-notice.success {
        background: #f0f8f0;
        border: 1px solid #00a32a;
        color: #207a00;
    }

    .fuse-notice.error {
        background: #fef5f5;
        border: 1px solid #cc0000;
        color: #d32f2f;
    }

    .fuse-notice.info {
        background: #f0f6ff;
        border: 1px solid #0073aa;
        color: #0073aa;
    }

    .fuse-api-test-result {
        border-radius: 4px;
        padding: 12px 16px;
        font-size: 13px;
        line-height: 1.7;
    }

    .fuse-api-test-result.success {
        background: #f0f8f0;
        border: 1px solid #2e7d32;
        color: #1b5e20;
    }

    .fuse-api-test-result.error {
        background: #fef5f5;
        border: 1px solid #c62828;
        color: #b71c1c;
    }
</style>

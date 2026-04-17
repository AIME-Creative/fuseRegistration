<?php
/**
 * Admin Settings Template
 * Plugin configuration using WordPress Settings API
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>Fuse 2026 Registration Settings</h1>

    <?php
    // ── Setup checklist ──────────────────────────────────────────────────
    $webhook_secret = get_option('fuse_stripe_webhook_secret', '');
    $success_page   = get_option('fuse_success_page_id', '');

    // Check if a page with the success shortcode exists
    $success_pages = get_posts(['post_type'=>'page','post_status'=>'publish','s'=>'fuse_registration_success','numberposts'=>1]);
    $has_success_page = !empty($success_pages);
    ?>
    <div style="background:#fff;border:1px solid #c3c4c7;border-left:4px solid #2271b1;padding:16px 20px;margin-bottom:20px;border-radius:0 4px 4px 0;">
        <h3 style="margin:0 0 10px;">⚡ Quick Setup Checklist</h3>
        <ol style="margin:0;padding-left:20px;line-height:2;">
            <li>
                <?php if ($has_success_page): ?>
                    <span style="color:#16a34a;">✅</span>
                <?php else: ?>
                    <span style="color:#dc2626;">☐</span>
                <?php endif; ?>
                <strong>Success page:</strong> Create a WordPress page at <code>/fuse/registration-success/</code>
                and add the shortcode <code>[fuse_registration_success]</code> to it.
                This page processes paid registrations when Stripe redirects back.
            </li>
            <li>
                <?php if (!empty($webhook_secret)): ?>
                    <span style="color:#16a34a;">✅</span>
                <?php else: ?>
                    <span style="color:#dc2626;">☐</span>
                <?php endif; ?>
                <strong>Stripe webhook:</strong> Add your webhook URL (shown below) to the
                <a href="https://dashboard.stripe.com/test/webhooks" target="_blank">Stripe Dashboard → Webhooks</a>
                and paste the signing secret below.
            </li>
            <li>
                <?php if (!empty(get_option('fuse_stripe_price_ga','')) || !empty(get_option('fuse_stripe_price_ga_early_bird',''))): ?>
                    <span style="color:#16a34a;">✅</span>
                <?php else: ?>
                    <span style="color:#dc2626;">☐</span>
                <?php endif; ?>
                <strong>Stripe products:</strong> Use the "Set Up / Verify All Stripe Products" button below
                to link ticket types to Stripe Price IDs.
            </li>
        </ol>
        <p style="margin:10px 0 0;font-size:12px;color:#666;">
            <strong>Optional — faster deduplication:</strong> Add <code>stripe_session_id text</code>
            and <code>stripe_payment_intent text</code> columns to your <code>fuse_registrations</code>
            Supabase table. These prevent duplicate records if both the webhook and success page run
            within seconds of each other. Without them, processing still works — duplicate prevention
            falls back to a best-effort check only.
        </p>
    </div>

    <form method="post" action="options.php">
        <?php settings_fields('fuse_reg_settings'); ?>

        <table class="form-table" role="presentation">
            <tbody>
                <!-- Supabase Configuration -->
                <tr>
                    <th scope="row">
                        <label for="fuse_supabase_url">Supabase URL</label>
                    </th>
                    <td>
                        <input
                            type="text"
                            name="fuse_supabase_url"
                            id="fuse_supabase_url"
                            value="<?php echo esc_attr(get_option('fuse_supabase_url')); ?>"
                            class="regular-text"
                            placeholder="https://your-project.supabase.co"
                        >
                        <p class="description">Your Supabase project URL</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="fuse_supabase_service_role_key">Supabase Service Role Key</label>
                    </th>
                    <td>
                        <input
                            type="password"
                            name="fuse_supabase_service_role_key"
                            id="fuse_supabase_service_role_key"
                            value="<?php echo esc_attr(get_option('fuse_supabase_service_role_key')); ?>"
                            class="regular-text"
                            placeholder="Service role key"
                        >
                        <p class="description">Service role key for server-side operations (keep secret)</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="fuse_supabase_anon_key">Supabase Anon Key</label>
                    </th>
                    <td>
                        <input
                            type="password"
                            name="fuse_supabase_anon_key"
                            id="fuse_supabase_anon_key"
                            value="<?php echo esc_attr(get_option('fuse_supabase_anon_key')); ?>"
                            class="regular-text"
                            placeholder="Anon key"
                        >
                        <p class="description">Anon key for client-side operations</p>
                    </td>
                </tr>

                <!-- Database Configuration -->
                <tr>
                    <th scope="row">
                        <label for="fuse_members_table_name">Members Table Name</label>
                    </th>
                    <td>
                        <input
                            type="text"
                            name="fuse_members_table_name"
                            id="fuse_members_table_name"
                            value="<?php echo esc_attr(get_option('fuse_members_table_name') ?: 'profiles'); ?>"
                            class="regular-text"
                            placeholder="profiles"
                        >
                        <p class="description">
                            Your members table in Supabase (default: <code>profiles</code>).<br>
                            <strong>Note:</strong> This is for reference only. The table name is set directly inside the <code>check_member_status</code> SQL function in Supabase. If you ever change the table, update that function too.
                        </p>
                    </td>
                </tr>

                <!-- Stripe Configuration -->
                <tr>
                    <th scope="row">
                        <label for="fuse_stripe_secret_key">Stripe Secret Key</label>
                    </th>
                    <td>
                        <input
                            type="password"
                            name="fuse_stripe_secret_key"
                            id="fuse_stripe_secret_key"
                            value="<?php echo esc_attr(get_option('fuse_stripe_secret_key')); ?>"
                            class="regular-text"
                            placeholder="sk_live_..."
                        >
                        <p class="description">Stripe secret key (keep secret)</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="fuse_stripe_publishable_key">Stripe Publishable Key</label>
                    </th>
                    <td>
                        <input
                            type="text"
                            name="fuse_stripe_publishable_key"
                            id="fuse_stripe_publishable_key"
                            value="<?php echo esc_attr(get_option('fuse_stripe_publishable_key')); ?>"
                            class="regular-text"
                            placeholder="pk_live_..."
                        >
                        <p class="description">Stripe publishable key</p>
                    </td>
                </tr>

                <!-- Fuse Event Configuration -->
                <tr>
                    <th scope="row">
                        <label for="fuse_event_id">Fuse Event ID</label>
                    </th>
                    <td>
                        <input
                            type="text"
                            name="fuse_event_id"
                            id="fuse_event_id"
                            value="<?php echo esc_attr(get_option('fuse_event_id')); ?>"
                            class="regular-text"
                            placeholder="UUID of the Fuse 2026 event"
                        >
                        <p class="description">Unique identifier for the Fuse 2026 event</p>
                    </td>
                </tr>

                <!-- reCAPTCHA v3 -->
                <tr>
                    <th scope="row">
                        <label for="fuse_recaptcha_site_key">reCAPTCHA v3 Site Key</label>
                    </th>
                    <td>
                        <input
                            type="text"
                            name="fuse_recaptcha_site_key"
                            id="fuse_recaptcha_site_key"
                            value="<?php echo esc_attr(get_option('fuse_recaptcha_site_key')); ?>"
                            class="regular-text"
                            placeholder="6Lc..."
                        >
                        <p class="description">Google reCAPTCHA v3 site key. Get one free at <a href="https://www.google.com/recaptcha/admin" target="_blank">google.com/recaptcha</a> — choose <strong>reCAPTCHA v3</strong> and add your site's domain.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="fuse_recaptcha_secret_key">reCAPTCHA v3 Secret Key</label>
                    </th>
                    <td>
                        <input
                            type="password"
                            name="fuse_recaptcha_secret_key"
                            id="fuse_recaptcha_secret_key"
                            value="<?php echo esc_attr(get_option('fuse_recaptcha_secret_key')); ?>"
                            class="regular-text"
                            placeholder="6Lc..."
                        >
                        <p class="description">The secret key from the same reCAPTCHA v3 registration. Used server-side to verify tokens.</p>
                    </td>
                </tr>

                <!-- Conexsys API Key -->
                <tr>
                    <th scope="row">
                        <label for="fuse_api_key">Conexsys API Key</label>
                    </th>
                    <td>
                        <input
                            type="text"
                            name="fuse_api_key"
                            id="fuse_api_key"
                            value="<?php echo esc_attr(get_option('fuse_api_key')); ?>"
                            class="regular-text"
                            placeholder="Secret key for the /wp-json/fuse/v1/conexsys endpoint"
                        >
                        <p class="description">Send this in the <code>X-Fuse-API-Key</code> header (or <code>?api_key=</code> query param) when calling the Conexsys REST API endpoint. Leave blank to require a WordPress admin login instead.</p>
                    </td>
                </tr>

                <!-- Pricing Configuration -->
                <tr>
                    <th scope="row">
                        <label for="fuse_early_bird_end_date">Early Bird End Date</label>
                    </th>
                    <td>
                        <input
                            type="date"
                            name="fuse_early_bird_end_date"
                            id="fuse_early_bird_end_date"
                            value="<?php echo esc_attr(get_option('fuse_early_bird_end_date')); ?>"
                        >
                        <p class="description">Date after which early bird pricing ends (YYYY-MM-DD)</p>
                    </td>
                </tr>

                <!-- Stripe Price IDs -->
                <tr>
                    <th scope="row" colspan="2">
                        <h3 style="margin:10px 0 4px;">Stripe Price IDs</h3>
                        <p style="font-weight:normal;color:#666;font-size:13px;margin:0 0 10px;">
                            Link each ticket type and add-on to a Stripe Product Price
                            (format: <code>price_xxxx…</code>). Use the button below to automatically
                            create all products in whichever mode your Stripe key is set to
                            (test or live), or paste in existing Price IDs manually.
                        </p>
                        <button type="button" class="button button-secondary" id="fuse-setup-stripe-products">
                            ⚡ Set Up / Verify All Stripe Products
                        </button>
                        <span id="fuse-stripe-setup-status" style="margin-left:12px;font-weight:600;"></span>
                        <div id="fuse-stripe-setup-results" style="margin-top:10px;"></div>
                    </th>
                </tr>

                <?php
                $price_fields = [
                    // --- General Admission ---
                    'fuse_stripe_price_ga_early_bird'       => ['GA Ticket (Early Bird)',       'General Admission — early bird price ($699). Switches on the Early Bird End Date above.'],
                    'fuse_stripe_price_ga'                  => ['GA Ticket (Regular)',          'General Admission — full price after early bird ($899).'],
                    'fuse_stripe_price_ga_member'           => ['GA Ticket (Member)',           'General Admission — free/claimed for Premium &amp; Elite members ($0).'],
                    // --- VIP ---
                    'fuse_stripe_price_vip'                 => ['VIP Ticket',                  'VIP ticket — free/claimed for VIP members ($0). Creates a $0 invoice line item.'],
                    // --- Guest Tickets ---
                    'fuse_stripe_price_guest_regular'       => ['Guest Ticket',                'Standard guest ticket — $349 for all guests.'],
                    'fuse_stripe_price_guest_vip'           => ['VIP Guest Ticket (Included)',  'VIP included free guest ticket ($0). Only the first VIP guest uses this; additional guests use the standard $349 price.'],
                    // --- Hall of AIME ---
                    'fuse_stripe_price_hoa_nonmember_early' => ['Hall of AIME (Early Bird)',   'Hall of AIME during early bird period ($299).'],
                    'fuse_stripe_price_hoa_nonmember'       => ['Hall of AIME (Regular)',      'Hall of AIME after early bird ($349).'],
                    'fuse_stripe_price_hoa_member'          => ['Hall of AIME (Member)',        'Hall of AIME for Premium / Elite members ($199).'],
                    'fuse_stripe_price_hoa_vip'             => ['Hall of AIME (VIP)',           'Hall of AIME for VIP — included free ($0). Also used for VIP first included guest.'],
                    // --- WMN at Fuse ---
                    'fuse_stripe_price_wmn'                 => ['WMN at Fuse',                 'WMN at Fuse add-on — free ($0). Creates a $0 invoice line item.'],
                    // --- VIP Luncheon ---
                    'fuse_stripe_price_vip_luncheon'        => ['VIP Luncheon',                'VIP Luncheon add-on. Set the price in Stripe and paste the price ID here.'],
                    // --- Vetted VA ---
                    'fuse_stripe_price_vetted_va'           => ['Vetted VA',                   'Vetted VA add-on. Set the price in Stripe and paste the price ID here.'],
                ];
                foreach ($price_fields as $option_key => [$label, $desc]): ?>
                <tr>
                    <th scope="row"><label for="<?php echo $option_key; ?>"><?php echo $label; ?></label></th>
                    <td>
                        <input type="text"
                            name="<?php echo $option_key; ?>"
                            id="<?php echo $option_key; ?>"
                            value="<?php echo esc_attr(get_option($option_key)); ?>"
                            class="regular-text fuse-price-id-input"
                            data-option="<?php echo $option_key; ?>"
                            placeholder="price_…">
                        <button type="button" class="button button-secondary fuse-verify-price"
                            data-option="<?php echo $option_key; ?>" style="margin-left:6px;">Verify</button>
                        <span class="fuse-price-verify-result" data-option="<?php echo $option_key; ?>"
                            style="margin-left:8px;font-size:13px;font-weight:600;"></span>
                        <p class="description"><?php echo $desc; ?></p>
                    </td>
                </tr>
                <?php endforeach; ?>

                <!-- Stripe Webhook -->
                <tr>
                    <th scope="row" colspan="2">
                        <h3 style="margin:20px 0 4px;">Stripe Webhook</h3>
                        <p style="font-weight:normal;color:#666;font-size:13px;margin:0 0 10px;">
                            Stripe notifies your site when a payment completes. Add the URL below as a webhook
                            endpoint in your <a href="https://dashboard.stripe.com/webhooks" target="_blank">Stripe Dashboard → Webhooks</a>
                            (use the <strong>Test mode</strong> tab while testing). Select the event
                            <code>invoice.paid</code>
                            (needed to flip manually-invoiced registrations from
                            <em>Pending</em> to <em>Purchased</em>). Then paste the <em>Signing Secret</em>
                            Stripe shows you into the field below.
                            <br><strong>Note:</strong> Checkout completions are processed via the Stripe API
                            on the success page — no webhook needed for those.
                        </p>
                        <p style="font-weight:normal;margin:0 0 6px;">
                            <strong>Your webhook URL:</strong>
                        </p>
                        <code style="display:block;background:#f0f0f0;padding:8px 12px;border-radius:4px;font-size:13px;word-break:break-all;">
                            <?php echo esc_html(rest_url('fuse/v1/stripe-webhook')); ?>
                        </code>
                        <button type="button" class="button button-secondary" style="margin-top:8px;"
                            onclick="navigator.clipboard.writeText('<?php echo esc_js(rest_url('fuse/v1/stripe-webhook')); ?>').then(function(){this.textContent='✓ Copied!';setTimeout(function(b){b.textContent='Copy URL'},1500,this)}.bind(this))">
                            Copy URL
                        </button>
                        <p style="font-weight:normal;color:#666;font-size:12px;margin:8px 0 0;">
                            <strong>Note:</strong> Even without the webhook, registrations are also processed on
                            the success page when Stripe redirects back — so your data will always be saved.
                            The webhook is the more reliable path and also works for off-site payments.
                        </p>
                    </th>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="fuse_stripe_webhook_secret">Stripe Webhook Secret</label>
                    </th>
                    <td>
                        <input
                            type="password"
                            name="fuse_stripe_webhook_secret"
                            id="fuse_stripe_webhook_secret"
                            value="<?php echo esc_attr(get_option('fuse_stripe_webhook_secret')); ?>"
                            class="regular-text"
                            placeholder="whsec_…"
                        >
                        <p class="description">
                            Paste the <em>Signing Secret</em> from your Stripe webhook endpoint details here.
                            Starts with <code>whsec_</code>. Leave blank to skip signature verification (not recommended for production).
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>

        <h2 style="margin-top:30px;">GoHighLevel (GHL) Integration</h2>
        <p style="color:#666; margin-bottom:20px;">
            When enabled, every registration will automatically sync to GHL: contacts are created or updated,
            tags are applied, the <em>Previous Events Attended</em> field is updated, non-member registrants
            are added to your leads pipeline, and a confirmation workflow is triggered.
        </p>
        <?php
        $ghl_last_error = get_transient('fuse_ghl_last_error');
        if ($ghl_last_error): ?>
            <div class="notice notice-error" style="margin-bottom:20px;">
                <p><strong>Last GHL error:</strong> <?php echo esc_html($ghl_last_error); ?></p>
                <p><a href="#" id="fuse-clear-ghl-error">Dismiss</a></p>
            </div>
        <?php endif; ?>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="fuse_ghl_api_key">GHL API Key</label>
                    </th>
                    <td>
                        <input
                            type="password"
                            name="fuse_ghl_api_key"
                            id="fuse_ghl_api_key"
                            value="<?php echo esc_attr(get_option('fuse_ghl_api_key')); ?>"
                            class="regular-text"
                            placeholder="Your GHL private integration token"
                        >
                        <p class="description">Found in GHL → Settings → Integrations → API Keys → Private Integration Token</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="fuse_ghl_location_id">GHL Location ID</label>
                    </th>
                    <td>
                        <input
                            type="text"
                            name="fuse_ghl_location_id"
                            id="fuse_ghl_location_id"
                            value="<?php echo esc_attr(get_option('fuse_ghl_location_id')); ?>"
                            class="regular-text"
                            placeholder="Your GHL sub-account Location ID"
                        >
                        <button type="button" class="button button-secondary" id="fuse-test-ghl" style="margin-left:10px;">Test Connection</button>
                        <span id="fuse-ghl-test-result" style="margin-left:10px;font-weight:600;"></span>
                        <p class="description">Found in GHL → Settings → Business Info → Location ID (or in the URL of your sub-account)</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="fuse_ghl_pipeline_id">Leads Pipeline ID</label>
                    </th>
                    <td>
                        <input
                            type="text"
                            name="fuse_ghl_pipeline_id"
                            id="fuse_ghl_pipeline_id"
                            value="<?php echo esc_attr(get_option('fuse_ghl_pipeline_id')); ?>"
                            class="regular-text"
                            placeholder="Pipeline ID from GHL URL or API"
                        >
                        <p class="description">Non-member registrants will be added as opportunities to this pipeline. Find the ID in the GHL pipeline URL.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="fuse_ghl_pipeline_stage_id">Pipeline Stage ID</label>
                    </th>
                    <td>
                        <input
                            type="text"
                            name="fuse_ghl_pipeline_stage_id"
                            id="fuse_ghl_pipeline_stage_id"
                            value="<?php echo esc_attr(get_option('fuse_ghl_pipeline_stage_id')); ?>"
                            class="regular-text"
                            placeholder="Stage ID within the leads pipeline"
                        >
                        <p class="description">The stage new non-member opportunities should land in. Retrieve via <code>GET /pipelines/{id}</code> from GHL API.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="fuse_ghl_events_field_id">Previous Events Custom Field ID</label>
                    </th>
                    <td>
                        <input
                            type="text"
                            name="fuse_ghl_events_field_id"
                            id="fuse_ghl_events_field_id"
                            value="<?php echo esc_attr(get_option('fuse_ghl_events_field_id')); ?>"
                            class="regular-text"
                            placeholder="Custom field ID for Previous Events Attended"
                        >
                        <p class="description">
                            The GHL custom field ID for <strong>Previous Events Attended</strong>. Find it via
                            <code>GET /custom-fields/</code> from the GHL API. Values added: <em>Fuse 2026</em>,
                            <em>Hall of AIME 2026</em>, <em>WMN at Fuse 2026</em> (as applicable).
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="fuse_ghl_confirmation_workflow_id">Confirmation Workflow ID</label>
                    </th>
                    <td>
                        <input
                            type="text"
                            name="fuse_ghl_confirmation_workflow_id"
                            id="fuse_ghl_confirmation_workflow_id"
                            value="<?php echo esc_attr(get_option('fuse_ghl_confirmation_workflow_id')); ?>"
                            class="regular-text"
                            placeholder="GHL Workflow ID to trigger on registration"
                        >
                        <p class="description">
                            The contact will be added to this GHL workflow immediately after registration —
                            use it to send your confirmation SMS/email. Leave blank to skip.
                            Find the workflow ID in GHL → Automation → (open workflow) → URL.
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php submit_button(); ?>
    </form>

    <!-- Settings Help Section -->
    <div class="fuse-settings-help">
        <h2>Configuration Help</h2>
        <div class="fuse-help-section">
            <h3>Supabase Setup</h3>
            <ol>
                <li>Create a Supabase project at <a href="https://supabase.com" target="_blank">supabase.com</a></li>
                <li>In your project settings, find the API URL and keys</li>
                <li>Paste the URL and keys above</li>
                <li>Ensure your members table exists in Supabase</li>
            </ol>
        </div>
        <div class="fuse-help-section">
            <h3>Stripe Setup</h3>
            <ol>
                <li>Get your API keys from <a href="https://dashboard.stripe.com/apikeys" target="_blank">Stripe Dashboard</a></li>
                <li>Use the "Restricted API Keys" section for better security</li>
                <li>Paste your keys above</li>
            </ol>
        </div>
        <div class="fuse-help-section">
            <h3>GHL Setup</h3>
            <ol>
                <li>In GHL go to <strong>Settings → Integrations → API Keys</strong> and create a Private Integration Token with Contacts, Opportunities, and Workflows permissions</li>
                <li>Paste the token as the GHL API Key above</li>
                <li>Open your leads pipeline in GHL — the pipeline ID and stage IDs appear in the page URL</li>
                <li>To find the <strong>Previous Events Attended</strong> custom field ID, call <code>GET https://rest.gohighlevel.com/v1/custom-fields/</code> with your API key and look for the matching field name</li>
                <li>Create a GHL Automation workflow that starts with the <strong>Contact Added to Workflow</strong> trigger and sends your confirmation message — paste its ID into the Confirmation Workflow ID field above</li>
                <li>Tags applied automatically: <code>fuse-2026</code>, <code>hoa-2026</code> (if Hall of AIME), <code>wmn-at-fuse-2026</code> (if WMN)</li>
            </ol>
        </div>
    </div>
</div>

<style>
    .form-table {
        background: #fff;
        border: 1px solid #ccc;
        border-radius: 4px;
        margin-bottom: 30px;
    }

    .form-table th {
        background: #f9f9f9;
        border-bottom: 1px solid #ccc;
        padding: 15px;
        text-align: left;
        font-weight: 600;
    }

    .form-table td {
        padding: 15px;
        border-bottom: 1px solid #eee;
    }

    .form-table input[type="text"],
    .form-table input[type="password"],
    .form-table input[type="date"] {
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 4px;
    }

    .form-table .regular-text {
        width: 400px;
        max-width: 100%;
    }

    .form-table .description {
        display: block;
        margin-top: 8px;
        color: #666;
        font-size: 13px;
    }

    .fuse-settings-help {
        background: #f0f6ff;
        border: 1px solid #0073aa;
        border-radius: 4px;
        padding: 20px;
        margin-top: 30px;
    }

    .fuse-settings-help h2 {
        margin-top: 0;
        color: #0073aa;
    }

    .fuse-help-section {
        margin-bottom: 20px;
        padding-bottom: 20px;
        border-bottom: 1px solid #0073aa;
    }

    .fuse-help-section:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }

    .fuse-help-section h3 {
        margin-top: 0;
        color: #333;
    }

    .fuse-help-section ol {
        margin: 10px 0;
        padding-left: 20px;
    }

    .fuse-help-section li {
        margin-bottom: 8px;
        color: #333;
    }

    .fuse-help-section a {
        color: #0073aa;
        text-decoration: none;
    }

    .fuse-help-section a:hover {
        text-decoration: underline;
    }
</style>
<script>
jQuery(function($) {
    // Test GHL connection
    $('#fuse-test-ghl').on('click', function() {
        var $btn    = $(this);
        var $result = $('#fuse-ghl-test-result');
        $btn.prop('disabled', true).text('Testing...');
        $result.css('color','#666').text('');

        $.post(ajaxurl, {
            action: 'fuse_admin_test_ghl',
            nonce:  '<?php echo wp_create_nonce('fuse_admin_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                $result.css('color','green').text('✓ ' + response.data.message);
            } else {
                $result.css('color','red').text('✗ ' + response.data.message);
            }
        }).fail(function() {
            $result.css('color','red').text('✗ Server error');
        }).always(function() {
            $btn.prop('disabled', false).text('Test Connection');
        });
    });

    // Dismiss last GHL error
    $('#fuse-clear-ghl-error').on('click', function(e) {
        e.preventDefault();
        $.post(ajaxurl, { action: 'fuse_admin_test_ghl', nonce: '<?php echo wp_create_nonce('fuse_admin_nonce'); ?>' });
        $(this).closest('.notice').fadeOut();
    });

    // ── Stripe: Set Up / Verify All Products ──
    $('#fuse-setup-stripe-products').on('click', function() {
        var $btn    = $(this);
        var $status = $('#fuse-stripe-setup-status');
        var $results = $('#fuse-stripe-setup-results');
        var nonce = '<?php echo wp_create_nonce('fuse_admin_nonce'); ?>';

        $btn.prop('disabled', true).text('Working…');
        $status.css('color', '#666').text('Creating / verifying Stripe products…');
        $results.empty();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: { action: 'fuse_admin_setup_stripe_products', nonce: nonce },
            success: function(response) {
                $btn.prop('disabled', false).text('⚡ Set Up / Verify All Stripe Products');
                if (!response.success) {
                    $status.css('color', '#d32f2f').text('✗ ' + (response.data.message || 'Error'));
                    return;
                }

                var data = response.data;
                var allOk = true;
                var isTest = false;
                var html = '<table style="border-collapse:collapse;font-size:13px;margin-top:4px;"><tbody>';

                $.each(data, function(optionKey, item) {
                    var icon, color, detail;
                    if (item.status === 'created') {
                        icon = '✓ Created'; color = '#2e7d32';
                        detail = item.price_id;
                        if (item.livemode === false) isTest = true;
                    } else if (item.status === 'existing') {
                        icon = '✓ Already set'; color = '#1565c0';
                        detail = item.price_id;
                        if (item.livemode === false) isTest = true;
                    } else if (item.status === 'invalid') {
                        icon = '✗ Invalid ID'; color = '#d32f2f'; allOk = false;
                        detail = item.price_id + ' (not found in Stripe)';
                    } else {
                        icon = '✗ Error'; color = '#d32f2f'; allOk = false;
                        detail = item.message;
                    }

                    html += '<tr><td style="padding:3px 12px 3px 0;color:' + color + ';font-weight:600;">' + icon + '</td>';
                    html += '<td style="padding:3px 12px 3px 0;">' + item.name + '</td>';
                    html += '<td style="padding:3px 0;color:#555;font-family:monospace;">' + detail + '</td></tr>';

                    // Auto-fill the input if a price was just created
                    if (item.status === 'created' && item.price_id) {
                        $('#' + optionKey).val(item.price_id);
                    }
                });

                html += '</tbody></table>';
                $results.html(html);

                var modeLabel = isTest ? ' (TEST MODE)' : ' (LIVE MODE)';
                $status.css('color', allOk ? '#2e7d32' : '#d32f2f')
                       .text(allOk ? '✓ All products ready' + modeLabel : '✗ Some errors — see below');

                if (allOk) {
                    $status.after(' <strong style="color:#ff6f00;">Remember to click Save Changes to store the Price IDs.</strong>');
                }
            },
            error: function(xhr, status) {
                $btn.prop('disabled', false).text('⚡ Set Up / Verify All Stripe Products');
                $status.css('color', '#d32f2f').text(
                    status === 'parsererror'
                        ? '✗ Auth error — refresh the page and try again'
                        : '✗ Server error (' + xhr.status + ')'
                );
            }
        });
    });

    // ── Stripe: Verify individual Price ID ──
    $(document).on('click', '.fuse-verify-price', function() {
        var optionKey = $(this).data('option');
        var $input    = $('#' + optionKey);
        var $result   = $('.fuse-price-verify-result[data-option="' + optionKey + '"]');
        var priceId   = $.trim($input.val());
        var nonce     = '<?php echo wp_create_nonce('fuse_admin_nonce'); ?>';

        if (!priceId) {
            $result.css('color', '#d32f2f').text('Enter a Price ID first');
            return;
        }

        $result.css('color', '#666').text('Checking…');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: { action: 'fuse_admin_verify_stripe_price', nonce: nonce, price_id: priceId },
            success: function(response) {
                if (response.success) {
                    var d = response.data;
                    var amount = d.amount ? ('$' + (d.amount / 100).toFixed(2) + ' ' + d.currency) : '';
                    var mode   = d.livemode ? 'LIVE' : 'TEST';
                    var active = d.active ? '' : ' · ARCHIVED';
                    $result.css('color', d.active ? '#2e7d32' : '#ff6f00')
                           .text('✓ Valid · ' + amount + ' · ' + mode + active);
                } else {
                    $result.css('color', '#d32f2f').text('✗ ' + (response.data.message || 'Invalid'));
                }
            },
            error: function() {
                $result.css('color', '#d32f2f').text('✗ Server error');
            }
        });
    });
});
</script>

<?php
/**
 * Plugin Name: Fuse 2026 Registration
 * Description: Conference registration system for AIME Fuse 2026 with Supabase member verification, Stripe payments, and admin management.
 * Version: 2.5.0
 * Author: AIME Group
 * Text Domain: fuse-registration
 */

if (!defined('ABSPATH')) exit;

define('FUSE_REG_VERSION', '2.5.0');
define('FUSE_REG_PATH', plugin_dir_path(__FILE__));
define('FUSE_REG_URL', plugin_dir_url(__FILE__));

/**
 * Custom capability used to gate all Fuse registration admin functionality.
 * Assigned to the built-in 'administrator' role and to our custom
 * 'fuse_registration_manager' role created on plugin activation.
 */
define('FUSE_REG_CAP', 'manage_fuse_registrations');

// ── Role management ──────────────────────────────────────────────────────────

/**
 * Create the Fuse Registration Manager role.
 * Called on plugin activation AND on every admin_init to self-heal if the
 * role was accidentally removed or the capability was stripped.
 */
function fuse_reg_register_role() {
    // Create the role if it doesn't exist yet
    if (!get_role('fuse_registration_manager')) {
        add_role('fuse_registration_manager', 'Fuse Registration Manager', [
            'read'                      => true,  // required to access wp-admin
            FUSE_REG_CAP                => true,
        ]);
    } else {
        // Role already exists — ensure our capability is still assigned
        $role = get_role('fuse_registration_manager');
        if ($role && !$role->has_cap(FUSE_REG_CAP)) {
            $role->add_cap(FUSE_REG_CAP);
        }
    }

    // Also give the capability to administrators so they aren't locked out
    $admin = get_role('administrator');
    if ($admin && !$admin->has_cap(FUSE_REG_CAP)) {
        $admin->add_cap(FUSE_REG_CAP);
    }
}
register_activation_hook(__FILE__, 'fuse_reg_register_role');
add_action('admin_init', 'fuse_reg_register_role');

/**
 * Remove the role on plugin deactivation.
 * Capabilities stored in the DB via add_cap() are removed automatically
 * when the role is removed.
 */
function fuse_reg_remove_role() {
    remove_role('fuse_registration_manager');
    // Remove the custom cap from administrators
    $admin = get_role('administrator');
    if ($admin) {
        $admin->remove_cap(FUSE_REG_CAP);
    }
}
register_deactivation_hook(__FILE__, 'fuse_reg_remove_role');

// ============================================================
// SETTINGS PAGE
// ============================================================
class Fuse_Registration_Settings {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu_pages']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        // Clear the cached Stripe pricing amounts whenever Settings are saved
        $price_options = [
            'fuse_stripe_price_ga', 'fuse_stripe_price_ga_early_bird', 'fuse_stripe_price_ga_member',
            'fuse_stripe_price_vip',
            'fuse_stripe_price_guest_regular', 'fuse_stripe_price_guest_vip',
            'fuse_stripe_price_hoa_nonmember_early', 'fuse_stripe_price_hoa_nonmember',
            'fuse_stripe_price_hoa_member', 'fuse_stripe_price_hoa_vip',
            'fuse_stripe_price_wmn',
            'fuse_stripe_price_vip_luncheon', 'fuse_stripe_price_vetted_va',
        ];
        foreach ($price_options as $opt) {
            add_action('update_option_' . $opt, [__CLASS__, 'bust_pricing_cache']);
        }
    }

    public static function bust_pricing_cache() {
        delete_transient('fuse_stripe_pricing_amounts');
    }

    public static function add_menu_pages() {
        // Main menu — opens directly to the combined Registrations page
        add_menu_page(
            'Fuse 2026 Registrations',
            'Fuse 2026',
            FUSE_REG_CAP,
            'fuse-registrations',
            [__CLASS__, 'render_list_page'],
            'dashicons-tickets-alt',
            30
        );

        // Submenu pages — first entry renamed to "Registrations" (replaces Dashboard)
        add_submenu_page('fuse-registrations', 'Registrations', 'Registrations', FUSE_REG_CAP,     'fuse-registrations', [__CLASS__, 'render_list_page']);
        add_submenu_page('fuse-registrations', 'Add Registration', 'Add New',    FUSE_REG_CAP,     'fuse-reg-add',       [__CLASS__, 'render_add_page']);
        add_submenu_page('fuse-registrations', 'Export', 'Export / Conexsys',    FUSE_REG_CAP,     'fuse-reg-export',    [__CLASS__, 'render_export_page']);
        add_submenu_page('fuse-registrations', 'Settings', 'Settings',           'manage_options', 'fuse-reg-settings',  [__CLASS__, 'render_settings_page']); // admin-only
    }

    public static function register_settings() {
        register_setting('fuse_reg_settings', 'fuse_supabase_url');
        register_setting('fuse_reg_settings', 'fuse_supabase_service_role_key');
        register_setting('fuse_reg_settings', 'fuse_supabase_anon_key');
        register_setting('fuse_reg_settings', 'fuse_stripe_secret_key');
        register_setting('fuse_reg_settings', 'fuse_stripe_publishable_key');
        register_setting('fuse_reg_settings', 'fuse_event_id');
        register_setting('fuse_reg_settings', 'fuse_api_key', 'sanitize_text_field');
        register_setting('fuse_reg_settings', 'fuse_recaptcha_site_key', 'sanitize_text_field');
        register_setting('fuse_reg_settings', 'fuse_recaptcha_secret_key', 'sanitize_text_field');
        register_setting('fuse_reg_settings', 'fuse_early_bird_end_date');
        register_setting('fuse_reg_settings', 'fuse_members_table_name', 'sanitize_text_field');
        // GHL settings
        register_setting('fuse_reg_settings', 'fuse_ghl_api_key', 'sanitize_text_field');
        register_setting('fuse_reg_settings', 'fuse_ghl_location_id', 'sanitize_text_field');
        register_setting('fuse_reg_settings', 'fuse_ghl_pipeline_id', 'sanitize_text_field');
        register_setting('fuse_reg_settings', 'fuse_ghl_pipeline_stage_id', 'sanitize_text_field');
        register_setting('fuse_reg_settings', 'fuse_ghl_events_field_id', 'sanitize_text_field');
        register_setting('fuse_reg_settings', 'fuse_ghl_confirmation_workflow_id', 'sanitize_text_field');
        // Stripe Price IDs (link ticket types / add-ons to existing Stripe Products)
        // -- GA --
        register_setting('fuse_reg_settings', 'fuse_stripe_price_ga', 'sanitize_text_field');
        register_setting('fuse_reg_settings', 'fuse_stripe_price_ga_early_bird', 'sanitize_text_field');
        register_setting('fuse_reg_settings', 'fuse_stripe_price_ga_member', 'sanitize_text_field');
        // -- VIP --
        register_setting('fuse_reg_settings', 'fuse_stripe_price_vip', 'sanitize_text_field');
        // -- Guest --
        register_setting('fuse_reg_settings', 'fuse_stripe_price_guest_regular', 'sanitize_text_field');
        register_setting('fuse_reg_settings', 'fuse_stripe_price_guest_vip', 'sanitize_text_field');
        // -- Hall of AIME --
        register_setting('fuse_reg_settings', 'fuse_stripe_price_hoa_nonmember_early', 'sanitize_text_field');
        register_setting('fuse_reg_settings', 'fuse_stripe_price_hoa_nonmember', 'sanitize_text_field');
        register_setting('fuse_reg_settings', 'fuse_stripe_price_hoa_member', 'sanitize_text_field');
        register_setting('fuse_reg_settings', 'fuse_stripe_price_hoa_vip', 'sanitize_text_field');
        // -- WMN at Fuse --
        register_setting('fuse_reg_settings', 'fuse_stripe_price_wmn', 'sanitize_text_field');
        // -- VIP Luncheon --
        register_setting('fuse_reg_settings', 'fuse_stripe_price_vip_luncheon', 'sanitize_text_field');
        // -- Vetted VA --
        register_setting('fuse_reg_settings', 'fuse_stripe_price_vetted_va', 'sanitize_text_field');
        // Stripe webhook secret (for signature verification)
        register_setting('fuse_reg_settings', 'fuse_stripe_webhook_secret', 'sanitize_text_field');
    }

    // Helper to get a setting
    public static function get($key, $default = '') {
        return get_option('fuse_' . $key, $default);
    }

    // ---- Page Renderers (load templates) ----
    public static function render_dashboard_page() {
        include FUSE_REG_PATH . 'templates/admin-dashboard.php';
    }
    public static function render_list_page() {
        include FUSE_REG_PATH . 'templates/admin-list.php';
    }
    public static function render_add_page() {
        include FUSE_REG_PATH . 'templates/admin-add.php';
    }
    public static function render_export_page() {
        include FUSE_REG_PATH . 'templates/admin-export.php';
    }
    public static function render_settings_page() {
        include FUSE_REG_PATH . 'templates/admin-settings.php';
    }
}

// ============================================================
// SUPABASE API WRAPPER
// ============================================================
class Fuse_Supabase_API {

    private static function get_url() {
        return rtrim(get_option('fuse_supabase_url', ''), '/');
    }

    private static function get_service_key() {
        return get_option('fuse_supabase_service_role_key', '');
    }

    private static function get_anon_key() {
        return get_option('fuse_supabase_anon_key', '');
    }

    /**
     * Make a request to Supabase REST API
     */
    public static function request($endpoint, $method = 'GET', $body = null, $use_service_key = true) {
        $base_url = self::get_url();
        $key      = $use_service_key ? self::get_service_key() : self::get_anon_key();

        // Guard: bail early with a clear error if settings aren't configured
        if (empty($base_url)) {
            error_log('Fuse Registration: Supabase URL is not configured in settings.');
            return ['error' => 'Supabase URL is not configured. Please check Fuse 2026 → Settings.'];
        }
        if (empty($key)) {
            $key_type = $use_service_key ? 'Service Role Key' : 'Anon Key';
            error_log("Fuse Registration: Supabase $key_type is not configured in settings.");
            return ['error' => "Supabase $key_type is not configured. Please check Fuse 2026 → Settings."];
        }

        $url = $base_url . '/rest/v1/' . ltrim($endpoint, '/');

        $args = [
            'method'  => $method,
            'timeout' => 30,
            'headers' => [
                'apikey'        => $key,
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
                'Prefer'        => 'return=representation',
            ],
        ];

        if ($body !== null) {
            $args['body'] = json_encode($body);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            error_log('Fuse Registration: HTTP error — ' . $response->get_error_message());
            return ['error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400) {
            $msg = $data['message'] ?? $data['error'] ?? 'Supabase API error (HTTP ' . $code . ')';
            error_log("Fuse Registration: Supabase error $code on $url — $msg");
            return ['error' => $msg, 'code' => $code];
        }

        return $data;
    }

    /**
     * Call a Supabase RPC function
     */
    public static function rpc($function_name, $params = [], $use_service_key = true) {
        $base_url = self::get_url();
        $key      = $use_service_key ? self::get_service_key() : self::get_anon_key();

        if (empty($base_url) || empty($key)) {
            error_log('Fuse Registration: Supabase URL or key not configured for RPC call.');
            return ['error' => 'Supabase is not configured. Please check Fuse 2026 → Settings.'];
        }

        $url = $base_url . '/rest/v1/rpc/' . $function_name;

        $response = wp_remote_post($url, [
            'timeout' => 30,
            'headers' => [
                'apikey'        => $key,
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
            ],
            'body' => json_encode($params),
        ]);

        if (is_wp_error($response)) {
            error_log('Fuse Registration: RPC HTTP error on ' . $function_name . ' — ' . $response->get_error_message());
            return ['error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400) {
            $msg = $data['message'] ?? $data['error'] ?? 'RPC error (HTTP ' . $code . ')';
            error_log("Fuse Registration: RPC error $code on $function_name — $msg");
            return ['error' => $msg, 'code' => $code];
        }

        return $data;
    }

    /**
     * Check member status by email
     */
    public static function check_member($email) {
        return self::rpc('check_member_status', ['lookup_email' => strtolower($email)]);
    }

    /**
     * Get all registrations for the current event
     */
    public static function get_registrations($filters = []) {
        $event_id  = get_option('fuse_event_id', '');
        $event_pfx = !empty($event_id) ? 'fuse_event_id=eq.' . $event_id . '&' : '';

        $endpoint = 'fuse_registrations?' . $event_pfx . 'order=created_at.desc';

        if (!empty($filters['ticket_type'])) {
            $endpoint .= '&ticket_type=eq.' . $filters['ticket_type'];
        }
        if (!empty($filters['tier'])) {
            $endpoint .= '&tier=eq.' . $filters['tier'];
        }
        if (!empty($filters['purchase_type'])) {
            $endpoint .= '&purchase_type=eq.' . $filters['purchase_type'];
        }
        if (!empty($filters['search'])) {
            $search = urlencode($filters['search']);
            $endpoint .= '&or=(full_name.ilike.*' . $search . '*,email.ilike.*' . $search . '*,company.ilike.*' . $search . '*)';
        }

        // Select with full guest records so guest sub-rows render in the list
        $endpoint .= '&select=*,fuse_registration_guests(*)';

        $regs = self::request($endpoint);
        if (isset($regs['error'])) return $regs;

        // ── Guest-name search: find extra registrations whose guests match ──
        // The main query above only searches primary-attendee fields. When the
        // search term might be a guest name we run a second query against the
        // guests table and merge the parent registrations in.
        if (!empty($filters['search']) && empty($filters['ticket_type']) && empty($filters['tier']) && empty($filters['purchase_type'])) {
            $gsearch = urlencode($filters['search']);
            $guest_endpoint = 'fuse_registration_guests?full_name=ilike.*' . $gsearch . '*&select=registration_id';

            $matching_guests = self::request($guest_endpoint);
            if (is_array($matching_guests) && !isset($matching_guests['error']) && !empty($matching_guests)) {

                // Collect registration IDs we haven't already returned
                $existing_ids = array_flip(array_column($regs, 'id'));
                $extra_ids    = [];
                foreach ($matching_guests as $mg) {
                    $rid = $mg['registration_id'] ?? '';
                    if ($rid && !isset($existing_ids[$rid])) {
                        $extra_ids[] = urlencode($rid);
                    }
                }

                if (!empty($extra_ids)) {
                    $extra_endpoint = 'fuse_registrations?id=in.(' . implode(',', $extra_ids) . ')&order=created_at.desc'
                        . '&select=*,fuse_registration_guests(id,full_name,email,ticket_type,has_hall_of_aime,has_wmn_at_fuse)';
                    $extra_regs = self::request($extra_endpoint);
                    if (is_array($extra_regs) && !isset($extra_regs['error'])) {
                        $regs = array_merge($regs, $extra_regs);
                    }
                }
            }
        }

        return $regs;
    }

    /**
     * Get a single registration with guests
     */
    public static function get_registration($id) {
        $endpoint = 'fuse_registrations?id=eq.' . $id . '&select=*,fuse_registration_guests(*)';
        $result = self::request($endpoint);
        return is_array($result) && !empty($result) ? $result[0] : null;
    }

    /**
     * Create a registration
     */
    public static function create_registration($data) {
        return self::request('fuse_registrations', 'POST', $data);
    }

    /**
     * Update a registration
     */
    public static function update_registration($id, $data) {
        $data['updated_at'] = gmdate('c');
        return self::request('fuse_registrations?id=eq.' . $id, 'PATCH', $data);
    }

    /**
     * Create a guest registration
     */
    public static function create_guest($data) {
        return self::request('fuse_registration_guests', 'POST', $data);
    }

    /**
     * Create a guest registration with graceful fallbacks for schema mismatches.
     *
     * Attempt 1: Full data (all columns including addon flags and new ticket types).
     * Attempt 2: If the error mentions missing addon columns, retry without them.
     * Attempt 3: If the error mentions an invalid ticket_type value (check constraint),
     *            retry with ticket_type = 'general_admission' so the row still saves.
     * All failures are logged in detail for easier diagnosis.
     */
    public static function create_guest_safe($data) {
        $result = self::request('fuse_registration_guests', 'POST', $data);

        // Success — return immediately
        if (!isset($result['error']) && !isset($result['message'])) {
            return $result;
        }

        $err_msg = $result['message'] ?? ($result['error'] ?? '');
        error_log('[Fuse create_guest_safe] attempt 1 failed — ticket_type="' . ($data['ticket_type'] ?? '') . '" error: ' . $err_msg);

        // Attempt 2: addon columns don't exist yet — retry without them
        if (strpos($err_msg, 'has_hall_of_aime') !== false || strpos($err_msg, 'has_wmn_at_fuse') !== false) {
            error_log('[Fuse create_guest_safe] addon columns missing — retrying without them');
            $fallback = $data;
            unset($fallback['has_hall_of_aime'], $fallback['has_wmn_at_fuse']);
            $result2 = self::request('fuse_registration_guests', 'POST', $fallback);
            if (!isset($result2['error']) && !isset($result2['message'])) {
                return $result2;
            }
            $err_msg = $result2['message'] ?? ($result2['error'] ?? '');
            error_log('[Fuse create_guest_safe] attempt 2 (no addon cols) failed — error: ' . $err_msg);
            $data = $fallback; // carry forward so attempt 3 also excludes addon cols
        }

        // Attempt 3: ticket_type value not in schema's allowed list — fall back to 'general_admission'
        // This keeps the row saving even if the schema migration for new types hasn't been run.
        $ticket_type = $data['ticket_type'] ?? '';
        $needs_ticket_fallback = (
            strpos($err_msg, 'ticket_type') !== false
            || strpos($err_msg, 'violates check constraint') !== false
            || strpos($err_msg, 'invalid input value') !== false
        ) && !in_array($ticket_type, ['general_admission', 'vip'], true);

        if ($needs_ticket_fallback) {
            error_log('[Fuse create_guest_safe] ticket_type="' . $ticket_type . '" rejected by schema — falling back to general_admission');
            $fallback3 = $data;
            $fallback3['ticket_type'] = 'general_admission';
            $result3 = self::request('fuse_registration_guests', 'POST', $fallback3);
            if (!isset($result3['error']) && !isset($result3['message'])) {
                error_log('[Fuse create_guest_safe] attempt 3 succeeded with ticket_type=general_admission — run the schema migration to fix this!');
                return $result3;
            }
            error_log('[Fuse create_guest_safe] attempt 3 also failed — error: ' . ($result3['message'] ?? ($result3['error'] ?? '')));
            return $result3;
        }

        return $result;
    }

    /**
     * Delete a guest
     */
    public static function delete_guest($id) {
        return self::request('fuse_registration_guests?id=eq.' . $id, 'DELETE');
    }

    /**
     * Mark a member's Fuse ticket as claimed in their profile
     */
    public static function mark_ticket_claimed($user_id, $year = 2026) {
        return self::rpc('mark_fuse_ticket_claimed', [
            'p_user_id' => $user_id,
            'p_year' => $year,
        ]);
    }

    /**
     * Get registration counts / stats
     */
    public static function get_stats() {
        $event_id = get_option('fuse_event_id', '');
        $base = !empty($event_id) ? 'fuse_registrations?fuse_event_id=eq.' . $event_id . '&' : 'fuse_registrations?';
        $regs = self::request($base . 'select=id,ticket_type,tier,purchase_type,has_hall_of_aime,has_wmn_at_fuse');

        if (isset($regs['error'])) return $regs;

        // Fetch guest details (ticket_type + addons) scoped to this event's registrations
        $guests = [];
        if (!empty($regs)) {
            $reg_ids = array_filter(array_column($regs, 'id'));
            if (!empty($reg_ids)) {
                $g = self::request(
                    'fuse_registration_guests?registration_id=in.(' .
                    implode(',', array_map('urlencode', $reg_ids)) .
                    ')&select=*'
                );
                if (is_array($g) && !isset($g['error'])) {
                    $guests = $g;
                }
            }
        }

        $stats = [
            // Row 1 — top-level totals
            'total_attendees'    => count($regs) + count($guests),  // everyone (primary + guests)
            'premium_elite'      => 0,   // claimed GA tickets (members)
            'vip_claimed'        => 0,   // claimed VIP tickets (members)
            'purchased'          => 0,   // non-member tickets purchased

            // Row 2 — guest breakdown
            'ga_guests'          => 0,   // guest_member + guest_ticket
            'vip_guests'         => 0,   // vip_guest

            // Row 3 — add-ons (primary + guests combined)
            'hall_of_aime'       => 0,
            'wmn_at_fuse'        => 0,
            'vip_luncheon'       => 0,
            'vetted_va'          => 0,
        ];

        // Tally primary registrations
        foreach ($regs as $r) {
            $tt = $r['ticket_type'] ?? '';
            $pt = $r['purchase_type'] ?? '';

            if ($tt === 'general_admission' && $pt === 'claimed') $stats['premium_elite']++;
            if ($tt === 'vip'               && $pt === 'claimed') $stats['vip_claimed']++;
            if ($pt === 'purchased')                              $stats['purchased']++;

            if (!empty($r['has_hall_of_aime']))   $stats['hall_of_aime']++;
            if (!empty($r['has_wmn_at_fuse']))    $stats['wmn_at_fuse']++;
            if (!empty($r['has_vip_luncheon']))   $stats['vip_luncheon']++;
            if (!empty($r['has_vetted_va']))       $stats['vetted_va']++;
        }

        // Tally guests
        foreach ($guests as $g) {
            $gtt = $g['ticket_type'] ?? '';

            if ($gtt === 'vip_guest')                                   $stats['vip_guests']++;
            elseif (in_array($gtt, ['guest_member', 'guest_ticket']))   $stats['ga_guests']++;

            if (!empty($g['has_hall_of_aime']))  $stats['hall_of_aime']++;
            if (!empty($g['has_wmn_at_fuse']))   $stats['wmn_at_fuse']++;
            if (!empty($g['has_vip_luncheon']))  $stats['vip_luncheon']++;
            if (!empty($g['has_vetted_va']))      $stats['vetted_va']++;
        }

        return $stats;
    }
}

// ============================================================
// STRIPE API WRAPPER
// ============================================================
class Fuse_Stripe_API {

    private static function get_secret_key() {
        return get_option('fuse_stripe_secret_key', '');
    }

    /**
     * Create a Stripe Checkout session
     */
    /**
     * Find an existing Stripe customer by email, or create one if none exists.
     * Returns the customer ID string on success, or null on failure.
     */
    public static function find_or_create_customer($email, $name = '') {
        $sk      = self::get_secret_key();
        $headers = [
            'Authorization' => 'Basic ' . base64_encode($sk . ':'),
            'Content-Type'  => 'application/x-www-form-urlencoded',
        ];

        // Search for existing customer
        $search = wp_remote_get(
            'https://api.stripe.com/v1/customers?email=' . urlencode($email) . '&limit=1',
            ['headers' => $headers, 'timeout' => 15]
        );
        if (!is_wp_error($search)) {
            $body = json_decode(wp_remote_retrieve_body($search), true);
            if (!empty($body['data'][0]['id'])) {
                error_log('[Fuse Stripe] Reusing existing customer ' . $body['data'][0]['id'] . ' for ' . $email);
                return $body['data'][0]['id'];
            }
        }

        // No existing customer — create one
        $create_body = ['email' => $email, 'metadata[source]' => 'fuse_2026_registration'];
        if (!empty($name)) $create_body['name'] = $name;

        $create = wp_remote_post('https://api.stripe.com/v1/customers', [
            'headers' => $headers,
            'body'    => $create_body,
            'timeout' => 15,
        ]);
        if (is_wp_error($create)) {
            error_log('[Fuse Stripe] Customer create network error: ' . $create->get_error_message());
            return null;
        }
        $customer = json_decode(wp_remote_retrieve_body($create), true);
        if (empty($customer['id'])) {
            error_log('[Fuse Stripe] Customer create failed: ' . ($customer['error']['message'] ?? 'unknown'));
            return null;
        }
        error_log('[Fuse Stripe] Created new customer ' . $customer['id'] . ' for ' . $email);
        return $customer['id'];
    }

    public static function create_checkout_session($params) {
        $response = wp_remote_post('https://api.stripe.com/v1/checkout/sessions', [
            'timeout' => 30,
            'headers' => [
                'Authorization'  => 'Basic ' . base64_encode(self::get_secret_key() . ':'),
                'Content-Type'   => 'application/x-www-form-urlencoded',
                // Pin to API version that supports payment_method_collection for $0 payment sessions
                'Stripe-Version' => '2023-10-16',
            ],
            'body' => $params,
        ]);

        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Create a Stripe Invoice for manual registrations
     */
    public static function create_invoice($customer_email, $line_items, $metadata = [], $customer_name = '') {
        $sk = self::get_secret_key();
        $headers = [
            'Authorization' => 'Basic ' . base64_encode($sk . ':'),
            'Content-Type'  => 'application/x-www-form-urlencoded',
        ];
        // Generous timeout — we make several sequential Stripe API calls and the
        // default WordPress 5-second timeout can cut off later calls silently.
        $timeout = 30;

        // Helper: make a Stripe POST and return decoded body, or ['wp_error' => '...']
        $stripe_post = function($url, $body) use ($headers, $timeout) {
            $r = wp_remote_post($url, ['headers' => $headers, 'body' => $body, 'timeout' => $timeout]);
            if (is_wp_error($r)) return ['wp_error' => $r->get_error_message()];
            return json_decode(wp_remote_retrieve_body($r), true) ?: [];
        };

        // 1. Find existing customer or create new one (prevents duplicate customer objects)
        $customer_id = self::find_or_create_customer($customer_email, $customer_name);
        if (!$customer_id) {
            error_log('Fuse Invoice: could not find or create Stripe customer for ' . $customer_email);
            return ['error' => 'Could not find or create Stripe customer.'];
        }
        $customer = ['id' => $customer_id];

        // 2. Create pending invoice items BEFORE the invoice so Stripe can collect them
        $items_added    = 0;
        $has_paid_items = false;
        foreach ($line_items as $item) {
            $price_id = sanitize_text_field($item['price_id'] ?? '');
            $amount   = intval($item['amount'] ?? $item['amount_cents'] ?? 0);

            if ($amount > 0) $has_paid_items = true;

            if (!empty($price_id)) {
                // Use pre-configured Stripe Price (works for both $0 and paid prices)
                $ii = $stripe_post('https://api.stripe.com/v1/invoiceitems', [
                    'customer' => $customer['id'],
                    'price'    => $price_id,
                ]);
                if (!empty($ii['wp_error']))  error_log('Fuse Invoice: invoiceitem network error — ' . $ii['wp_error']);
                elseif (isset($ii['id']))      $items_added++;
                else                           error_log('Fuse Invoice: invoiceitem (price) failed — ' . ($ii['error']['message'] ?? 'unknown'));
            } else {
                // Dynamic item — works for both paid ($amount > 0) and complimentary ($amount == 0).
                // Stripe invoice items support $0; only checkout sessions require positive amounts.
                $ii = $stripe_post('https://api.stripe.com/v1/invoiceitems', [
                    'customer'    => $customer['id'],
                    'description' => sanitize_text_field($item['description'] ?? 'Ticket'),
                    'amount'      => $amount,
                    'currency'    => 'usd',
                ]);
                if (!empty($ii['wp_error']))  error_log('Fuse Invoice: invoiceitem network error — ' . $ii['wp_error']);
                elseif (isset($ii['id']))      $items_added++;
                else                           error_log('Fuse Invoice: invoiceitem (dynamic) failed — ' . ($ii['error']['message'] ?? 'unknown'));
            }
        }

        // For $0-total registrations it is fine to have no invoice items (Stripe creates a $0 invoice).
        // Only error if there were paid items that all failed to be added.
        if ($items_added === 0 && $has_paid_items) {
            return ['error' => 'No chargeable line items could be added. Check that Stripe Price IDs are configured in Settings.'];
        }

        // 3. Create invoice — Stripe auto-collects the pending items created above
        $inv_body = [
            'customer'          => $customer['id'],
            'collection_method' => 'send_invoice',
            'days_until_due'    => 7,
            'auto_advance'      => 'false',
            'currency'          => 'usd',
        ];
        foreach ($metadata as $k => $v) $inv_body['metadata[' . $k . ']'] = $v;

        $invoice = $stripe_post('https://api.stripe.com/v1/invoices', $inv_body);
        if (!empty($invoice['wp_error'])) return ['error' => 'Network error creating invoice: ' . $invoice['wp_error']];
        if (!isset($invoice['id'])) {
            $msg = $invoice['error']['message'] ?? 'unknown error';
            error_log('Fuse Invoice: invoice creation failed — ' . $msg);
            return ['error' => 'Could not create invoice: ' . $msg];
        }

        // 4. Finalize — moves invoice draft → open
        $finalized = $stripe_post('https://api.stripe.com/v1/invoices/' . $invoice['id'] . '/finalize', []);
        if (!empty($finalized['wp_error'])) return ['error' => 'Network error finalizing invoice: ' . $finalized['wp_error']];
        if (!isset($finalized['id'])) {
            $msg = $finalized['error']['message'] ?? 'unknown error';
            error_log('Fuse Invoice: finalize failed — ' . $msg);
            return ['error' => 'Invoice created but could not be finalized: ' . $msg];
        }

        // 5. Send the invoice email explicitly
        $sent = $stripe_post('https://api.stripe.com/v1/invoices/' . $invoice['id'] . '/send', []);
        if (!empty($sent['wp_error'])) {
            error_log('Fuse Invoice: /send network error — ' . $sent['wp_error']);
            return [
                'invoice_id'  => $invoice['id'],
                'invoice_url' => $finalized['hosted_invoice_url'] ?? '',
                'customer_id' => $customer['id'],
                'email_sent'  => false,
                'email_error' => 'Network error: ' . $sent['wp_error'],
            ];
        }
        if (isset($sent['error'])) {
            $msg = $sent['error']['message'] ?? 'unknown error';
            error_log('Fuse Invoice: /send failed — ' . $msg);
            return [
                'invoice_id'  => $invoice['id'],
                'invoice_url' => $finalized['hosted_invoice_url'] ?? '',
                'customer_id' => $customer['id'],
                'email_sent'  => false,
                'email_error' => $msg,
            ];
        }

        return [
            'invoice_id'  => $invoice['id'],
            'invoice_url' => $finalized['hosted_invoice_url'] ?? ($invoice['hosted_invoice_url'] ?? ''),
            'customer_id' => $customer['id'],
            'email_sent'  => true,
        ];
    }

    /**
     * Retrieve a Stripe Price by ID (used to verify a configured price ID)
     */
    public static function get_price($price_id) {
        $response = wp_remote_get('https://api.stripe.com/v1/prices/' . urlencode($price_id), [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode(self::get_secret_key() . ':'),
            ],
        ]);
        if (is_wp_error($response)) return ['error' => $response->get_error_message()];
        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Retrieve a Stripe Checkout Session by ID (expands line_items)
     */
    public static function retrieve_checkout_session($session_id) {
        $sk = self::get_secret_key();
        $response = wp_remote_get(
            'https://api.stripe.com/v1/checkout/sessions/' . urlencode($session_id) . '?expand[]=line_items',
            [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($sk . ':'),
                ],
            ]
        );
        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }
        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Create a Stripe Product + one-time Price and return the Price object
     */
    public static function create_product_price($name, $amount_cents, $currency = 'usd') {
        $sk = self::get_secret_key();
        $headers = [
            'Authorization' => 'Basic ' . base64_encode($sk . ':'),
            'Content-Type'  => 'application/x-www-form-urlencoded',
        ];

        // Create the product
        $prod_resp = wp_remote_post('https://api.stripe.com/v1/products', [
            'timeout' => 15,
            'headers' => $headers,
            'body'    => [
                'name'        => $name,
                'description' => 'Fuse 2026 Conference',
            ],
        ]);
        $product = json_decode(wp_remote_retrieve_body($prod_resp), true);
        if (!isset($product['id'])) {
            return ['error' => ['message' => $product['error']['message'] ?? 'Could not create Stripe product']];
        }

        // Create a one-time price for that product
        $price_resp = wp_remote_post('https://api.stripe.com/v1/prices', [
            'timeout' => 15,
            'headers' => $headers,
            'body'    => [
                'product'     => $product['id'],
                'unit_amount' => $amount_cents,
                'currency'    => $currency,
            ],
        ]);
        return json_decode(wp_remote_retrieve_body($price_resp), true);
    }
}

// ============================================================
// AJAX HANDLERS (for both frontend + admin)
// ============================================================
class Fuse_Registration_Ajax {

    public static function init() {
        // Public (nopriv = not logged in)
        add_action('wp_ajax_fuse_check_member', [__CLASS__, 'check_member']);
        add_action('wp_ajax_nopriv_fuse_check_member', [__CLASS__, 'check_member']);

        add_action('wp_ajax_fuse_submit_registration', [__CLASS__, 'submit_registration']);
        add_action('wp_ajax_nopriv_fuse_submit_registration', [__CLASS__, 'submit_registration']);

        add_action('wp_ajax_fuse_create_checkout', [__CLASS__, 'create_checkout']);
        add_action('wp_ajax_nopriv_fuse_create_checkout', [__CLASS__, 'create_checkout']);

        // Admin only
        add_action('wp_ajax_fuse_admin_get_registrations', [__CLASS__, 'admin_get_registrations']);
        add_action('wp_ajax_fuse_admin_get_registration', [__CLASS__, 'admin_get_registration']);
        add_action('wp_ajax_fuse_admin_save_registration', [__CLASS__, 'admin_save_registration']);
        add_action('wp_ajax_fuse_admin_send_invoice', [__CLASS__, 'admin_send_invoice']);
        add_action('wp_ajax_fuse_admin_get_stats', [__CLASS__, 'admin_get_stats']);
        add_action('wp_ajax_fuse_admin_export', [__CLASS__, 'admin_export']);
        add_action('wp_ajax_fuse_admin_lookup_member', [__CLASS__, 'admin_lookup_member']);
        add_action('wp_ajax_fuse_admin_delete_registration', [__CLASS__, 'admin_delete_registration']);
        add_action('wp_ajax_fuse_admin_test_ghl', [__CLASS__, 'admin_test_ghl']);
        add_action('wp_ajax_fuse_admin_setup_stripe_products', [__CLASS__, 'admin_setup_stripe_products']);
        add_action('wp_ajax_fuse_admin_verify_stripe_price', [__CLASS__, 'admin_verify_stripe_price']);
    }

    // --- PUBLIC: Check membership ---
    public static function check_member() {
        check_ajax_referer('fuse_reg_nonce', 'nonce');
        $email = sanitize_email($_POST['email'] ?? '');

        if (empty($email)) {
            wp_send_json_error(['message' => 'Please enter a valid email address.']);
        }

        // Check if already registered for this event
        $event_id = get_option('fuse_event_id', '');
        $existing = Fuse_Supabase_API::request(
            'fuse_registrations?fuse_event_id=eq.' . $event_id . '&email=eq.' . urlencode(strtolower($email)) . '&select=id,ticket_type'
        );

        if (!isset($existing['error']) && !empty($existing)) {
            wp_send_json_error([
                'message' => 'This email is already registered for Fuse 2026.',
                'already_registered' => true,
                'ticket_type' => $existing[0]['ticket_type'],
            ]);
        }

        $result = Fuse_Supabase_API::check_member($email);

        // If Supabase itself errored, return a clean error
        if (isset($result['error'])) {
            wp_send_json_error(['message' => 'Unable to verify membership. Please try again.', 'debug' => $result['error']]);
        }

        // Already claimed their 2026 ticket
        if (!empty($result['already_claimed'])) {
            wp_send_json_error([
                'message'            => $result['message'] ?? 'You have already claimed your Fuse 2026 ticket.',
                'already_registered' => true,
            ]);
        }

        // Already has a registration record
        if (!empty($result['already_registered'])) {
            wp_send_json_error([
                'message'            => $result['message'] ?? 'You are already registered for Fuse 2026.',
                'already_registered' => true,
            ]);
        }

        // Not a member (no account, no active plan, inactive subscription)
        // Send as json_error so the JS "not a member" path triggers correctly
        if (empty($result['is_member'])) {
            wp_send_json_error([
                'message'     => $result['message'] ?? 'No active membership found for this email.',
                'is_member'   => false,
                'has_account' => !empty($result['has_account']),
            ]);
        }

        // Active member — send full profile data
        wp_send_json_success($result);
    }

    // --- HELPER: Verify reCAPTCHA v3 token ---
    private static function verify_recaptcha($token) {
        $secret = get_option('fuse_recaptcha_secret_key', '');
        if (empty($secret)) return true; // reCAPTCHA not configured — skip check
        if (empty($token)) return false;

        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'body' => [
                'secret'   => $secret,
                'response' => $token,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ],
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) return false;

        $body = json_decode(wp_remote_retrieve_body($response), true);
        // Require success + score above 0.3 (very low bar — blocks obvious bots)
        return !empty($body['success']) && ($body['score'] ?? 0) >= 0.3;
    }

    // --- PUBLIC: Submit free registration (member claim) ---
    public static function submit_registration() {
        check_ajax_referer('fuse_reg_nonce', 'nonce');

        if (!self::verify_recaptcha(sanitize_text_field($_POST['recaptcha_token'] ?? ''))) {
            wp_send_json_error(['message' => 'Security check failed. Please refresh the page and try again.']);
        }

        $event_id = get_option('fuse_event_id', '');
        $data = [
            'fuse_event_id'      => $event_id,
            'email'              => sanitize_email($_POST['email'] ?? ''),
            'first_name'         => sanitize_text_field($_POST['first_name'] ?? ''),
            'last_name'          => sanitize_text_field($_POST['last_name'] ?? ''),
            'full_name'          => sanitize_text_field(($_POST['first_name'] ?? '') . ' ' . ($_POST['last_name'] ?? '')),
            'phone'              => sanitize_text_field($_POST['phone'] ?? ''),
            'company'            => sanitize_text_field($_POST['company'] ?? ''),
            'ticket_type'        => sanitize_text_field($_POST['ticket_type'] ?? 'general_admission'),
            'tier'               => sanitize_text_field($_POST['tier'] ?? '') ?: null,
            'purchase_type'      => 'claimed',
            // VIP members always include Hall of AIME regardless of form value
            'has_hall_of_aime'   => (sanitize_text_field($_POST['ticket_type'] ?? '') === 'vip')
                                        ? true
                                        : (bool) ($_POST['has_hall_of_aime'] ?? false),
            'has_wmn_at_fuse'    => (bool) ($_POST['has_wmn_at_fuse'] ?? false),
            'has_vip_luncheon'   => (bool) ($_POST['has_vip_luncheon'] ?? false),
            'has_vetted_va'      => (bool) ($_POST['has_vetted_va'] ?? false),
            'registration_source' => 'wordpress', // using existing allowed value
            'preferred_name'     => sanitize_text_field($_POST['preferred_name'] ?? ''),
            'gender'             => sanitize_text_field($_POST['gender'] ?? ''),
            'marketing_consent'  => (bool) ($_POST['marketing_consent'] ?? false),
            'user_id'            => sanitize_text_field($_POST['user_id'] ?? '') ?: null,
        ];

        $result = Fuse_Supabase_API::create_registration($data);

        if (isset($result['error'])) {
            wp_send_json_error(['message' => $result['error']]);
        }

        // Create guest records from guests_json array
        $reg_id = $result[0]['id'] ?? null;
        if ($reg_id) {
            $primary_ticket_type = sanitize_text_field($_POST['ticket_type'] ?? 'general_admission');
            $guests_json = stripslashes($_POST['guests_json'] ?? '[]');
            $guests = json_decode($guests_json, true) ?: [];
            foreach ($guests as $i => $guest) {
                $full_name = sanitize_text_field($guest['name'] ?? ($guest['full_name'] ?? ''));
                if (empty($full_name)) continue;
                // VIP: first guest (index 0) is free and includes Hall of AIME (vip_guest).
                // Additional VIP guests (index 1+) pay the regular guest rate (guest_ticket).
                if ($primary_ticket_type === 'vip') {
                    $guest_ticket_type = ($i === 0) ? 'vip_guest' : 'guest_ticket';
                } else {
                    // submit_registration is always a member claim — guests get regular rate
                    $guest_ticket_type = sanitize_text_field($guest['ticket_type'] ?? 'guest_ticket');
                }
                $g_has_hoa          = ($guest_ticket_type === 'vip_guest') || !empty($guest['hasHoa']) || !empty($guest['has_hall_of_aime']);
                $g_has_wmn          = !empty($guest['hasWmn']) || !empty($guest['has_wmn_at_fuse']);
                $g_has_vip_luncheon = !empty($guest['hasVipLuncheon']) || !empty($guest['has_vip_luncheon']);
                $g_has_vetted_va    = !empty($guest['hasVettedVa']) || !empty($guest['has_vetted_va']);
                Fuse_Supabase_API::create_guest_safe([
                    'registration_id'  => $reg_id,
                    'full_name'        => $full_name,
                    'email'            => sanitize_email($guest['email'] ?? ''),
                    'phone'            => sanitize_text_field($guest['phone'] ?? ''),
                    'ticket_type'      => $guest_ticket_type,
                    'has_hall_of_aime' => $g_has_hoa,
                    'has_wmn_at_fuse'  => $g_has_wmn,
                    'has_vip_luncheon' => $g_has_vip_luncheon,
                    'has_vetted_va'    => $g_has_vetted_va,
                ]);
            }
        }

        // Mark the ticket as claimed in their profile (prevents double-claiming)
        $user_id = sanitize_text_field($_POST['user_id'] ?? '');
        if ($user_id && $data['purchase_type'] === 'claimed') {
            Fuse_Supabase_API::mark_ticket_claimed($user_id, 2026);
        }

        // Sync to GHL
        Fuse_GHL_API::process_registration($data);

        wp_send_json_success(['registration_id' => $reg_id, 'message' => 'Registration confirmed!']);
    }

    // --- PUBLIC: Create Stripe Checkout for paid tickets ---
    public static function create_checkout() {
        check_ajax_referer('fuse_reg_nonce', 'nonce');

        if (!self::verify_recaptcha(sanitize_text_field($_POST['recaptcha_token'] ?? ''))) {
            wp_send_json_error(['message' => 'Security check failed. Please refresh the page and try again.']);
        }

        $email = sanitize_email($_POST['email'] ?? '');
        $items_json = stripslashes($_POST['items'] ?? '[]');
        $items = json_decode($items_json, true) ?: [];

        error_log('[Fuse create_checkout] items received (' . count($items) . '): ' . $items_json);

        if (empty($items)) {
            wp_send_json_error(['message' => 'No items to purchase.']);
        }

        // Calculate total
        $total_cents = 0;
        foreach ($items as $item) {
            $total_cents += intval($item['price_cents'] ?? 0);
        }

        // ── Collect registration metadata from POST ───────────────────────────
        $meta_fields = ['email', 'first_name', 'last_name', 'company', 'phone', 'ticket_type',
                        'tier', 'has_hall_of_aime', 'has_wmn_at_fuse', 'has_vip_luncheon', 'has_vetted_va',
                        'gender', 'preferred_name', 'purchase_type', 'marketing_consent', 'user_id'];
        $reg_meta = ['fuse_event_id' => get_option('fuse_event_id', '')];
        foreach ($meta_fields as $f) {
            $reg_meta[$f] = sanitize_text_field($_POST[$f] ?? '');
        }
        $reg_meta['email'] = $email; // use sanitized email

        // ── Collect and store guest data ─────────────────────────────────────
        $guests_json    = stripslashes($_POST['guests_json'] ?? '[]');
        $guests_decoded = json_decode($guests_json, true) ?: [];
        error_log('[Fuse create_checkout] guests_json received — count: ' . count($guests_decoded) . ' — raw: ' . substr($guests_json, 0, 500));

        $guests_key = '';
        $guests_compact = '';
        if (!empty($guests_decoded)) {
            $guests_key       = 'fuse_guests_' . substr(md5(uniqid('', true)), 0, 12);
            $transient_result = set_transient($guests_key, $guests_json, DAY_IN_SECONDS);
            error_log('[Fuse create_checkout] transient key: ' . $guests_key . ' — set_transient: ' . ($transient_result ? 'OK' : 'FAILED'));

            $compact_parts = [];
            foreach ($guests_decoded as $g) {
                $gname  = substr(sanitize_text_field($g['name'] ?? ($g['first_name'] ?? '') . ' ' . ($g['last_name'] ?? '')), 0, 60);
                $gemail = substr(sanitize_email($g['email'] ?? ''), 0, 80);
                $gphone = substr(sanitize_text_field($g['phone'] ?? ''), 0, 20);
                $ghoa   = !empty($g['hasHoa']) ? '1' : '0';
                $gwmn   = !empty($g['hasWmn']) ? '1' : '0';
                $glunch = !empty($g['hasVipLuncheon']) ? '1' : '0';
                $gva    = !empty($g['hasVettedVa']) ? '1' : '0';
                $compact_parts[] = $gname . '|' . $gemail . '|' . $gphone . '|' . $ghoa . '|' . $gwmn . '|' . $glunch . '|' . $gva;
            }
            $guests_compact = substr(implode('~', $compact_parts), 0, 490);
            error_log('[Fuse create_checkout] guests_compact: ' . $guests_compact);
        } else {
            error_log('[Fuse create_checkout] guests_json is empty — no guests');
        }

        // ── $0 TOTAL: bypass Stripe Checkout ─────────────────────────────────
        // Stripe payment mode does not support $0 checkout sessions.
        // Instead we process the registration directly here and create a $0
        // Stripe Invoice via the Invoice API (which auto-marks as paid).
        if ($total_cents === 0) {
            error_log('[Fuse create_checkout] $0 total — processing directly (bypassing Stripe Checkout)');

            // Build a synthetic session object that matches what fuse_reg_process_paid_registration expects
            $synthetic_session = [
                'id'             => 'free_' . substr(md5(uniqid('', true)), 0, 16),
                'payment_status' => 'paid',
                'status'         => 'complete',
                'payment_intent' => null,
                'amount_total'   => 0,
                'customer_email' => $email,
                'metadata'       => array_merge($reg_meta, [
                    'guests_key'     => $guests_key,
                    'guests_compact' => $guests_compact,
                ]),
            ];

            $result = fuse_reg_process_paid_registration($synthetic_session);

            if (isset($result['error'])) {
                error_log('[Fuse create_checkout] $0 registration processing error: ' . $result['error']);
                wp_send_json_error(['message' => 'Registration could not be saved: ' . $result['error']]);
            }

            // Best-effort: create a $0 Stripe Invoice for record-keeping
            $invoice_items = [];
            foreach ($items as $item) {
                $invoice_items[] = [
                    'price_id'    => sanitize_text_field($item['price_id'] ?? ''),
                    'description' => sanitize_text_field($item['label'] ?? 'Ticket'),
                    'amount_cents' => intval($item['price_cents'] ?? 0),
                ];
            }
            $invoice_result = Fuse_Stripe_API::create_invoice($email, $invoice_items, [
                'fuse_registration_id' => $result['registration_id'] ?? '',
                'source'               => 'fuse_free_registration',
                'ticket_type'          => $reg_meta['ticket_type'] ?? '',
                'tier'                 => $reg_meta['tier'] ?? '',
            ], $full_name);
            if (isset($invoice_result['error'])) {
                error_log('[Fuse create_checkout] $0 invoice creation failed (non-fatal): ' . $invoice_result['error']);
            } else {
                error_log('[Fuse create_checkout] $0 invoice created: ' . ($invoice_result['invoice_id'] ?? 'unknown'));
            }

            $success_url = home_url('/fuse/registration-success/');
            wp_send_json_success(['checkout_url' => $success_url]);
            return;
        }

        // ── PAID: create Stripe Checkout session ─────────────────────────────
        $success_url = home_url('/fuse/registration-success/') . '?session_id={CHECKOUT_SESSION_ID}';
        $cancel_url  = home_url('/fuse/register/');

        // Look up or create Stripe customer so returning attendees aren't duplicated
        $first_name  = sanitize_text_field($_POST['first_name'] ?? '');
        $last_name   = sanitize_text_field($_POST['last_name']  ?? '');
        $full_name   = trim("$first_name $last_name");
        $customer_id = Fuse_Stripe_API::find_or_create_customer($email, $full_name);

        $params = [
            'mode'                      => 'payment',
            'success_url'               => $success_url,
            'cancel_url'                => $cancel_url,
            'invoice_creation[enabled]' => 'true',
        ];

        // Attach to existing/new customer (prevents duplicate customer objects)
        if ($customer_id) {
            $params['customer'] = $customer_id;
        } else {
            // Fallback: pass email and let Stripe create the customer
            $params['customer_email'] = $email;
        }

        // Build line items — skip $0 items that have no configured Stripe Price ID
        $item_index = 0;
        foreach ($items as $item) {
            $price_cents = intval($item['price_cents'] ?? 0);
            $price_id    = sanitize_text_field($item['price_id'] ?? '');
            $label       = sanitize_text_field($item['label'] ?? 'Ticket');
            $item_type   = sanitize_text_field($item['type'] ?? '');

            if ($price_id) {
                $params["line_items[$item_index][price]"] = $price_id;
                error_log("[Fuse create_checkout] item[$item_index] type=$item_type label=\"$label\" price_id=$price_id cents=$price_cents");
            } elseif ($price_cents > 0) {
                $params["line_items[$item_index][price_data][currency]"]                = 'usd';
                $params["line_items[$item_index][price_data][product_data][name]"]      = "Fuse 2026 - $label";
                $params["line_items[$item_index][price_data][unit_amount]"]             = $price_cents;
                error_log("[Fuse create_checkout] item[$item_index] type=$item_type label=\"$label\" DYNAMIC cents=$price_cents (no price_id!)");
            } else {
                error_log("[Fuse create_checkout] SKIPPING item type=$item_type label=\"$label\" — \$0 with no price_id");
                continue;
            }
            $params["line_items[$item_index][quantity]"] = 1;
            $item_index++;
        }

        // Metadata
        foreach ($reg_meta as $f => $val) {
            if ($val !== '') $params["metadata[$f]"] = $val;
        }
        if ($guests_key)    $params['metadata[guests_key]']     = $guests_key;
        if ($guests_compact) $params['metadata[guests_compact]'] = $guests_compact;

        error_log('[Fuse create_checkout] sending ' . $item_index . ' line items to Stripe, total_cents=' . $total_cents);
        $session = Fuse_Stripe_API::create_checkout_session($params);

        if (isset($session['error'])) {
            error_log('[Fuse create_checkout] Stripe error: ' . ($session['error']['message'] ?? json_encode($session['error'])));
            wp_send_json_error(['message' => $session['error']['message'] ?? $session['error']]);
        }

        error_log('[Fuse create_checkout] Stripe session created: ' . ($session['id'] ?? 'no-id') . ' url=' . ($session['url'] ?? 'no-url'));
        wp_send_json_success(['checkout_url' => $session['url']]);
    }

    // --- ADMIN: Get registrations ---
    public static function admin_get_registrations() {
        check_ajax_referer('fuse_admin_nonce', 'nonce');
        if (!current_user_can(FUSE_REG_CAP)) wp_send_json_error(['message' => 'Unauthorized']);

        $filters = [
            'ticket_type'  => sanitize_text_field($_POST['ticket_type'] ?? ''),
            'tier'         => sanitize_text_field($_POST['tier'] ?? ''),
            'purchase_type' => sanitize_text_field($_POST['purchase_type'] ?? ''),
            'search'       => sanitize_text_field($_POST['search'] ?? ''),
        ];

        $result = Fuse_Supabase_API::get_registrations($filters);

        if (isset($result['error'])) {
            wp_send_json_error(['message' => $result['error']]);
        }

        // Return in the format JS expects: { registrations: [...], total: n }
        wp_send_json_success([
            'registrations' => is_array($result) ? $result : [],
            'total'         => is_array($result) ? count($result) : 0,
        ]);
    }

    // --- ADMIN: Delete a registration ---
    public static function admin_delete_registration() {
        check_ajax_referer('fuse_admin_nonce', 'nonce');
        if (!current_user_can(FUSE_REG_CAP)) wp_send_json_error(['message' => 'Unauthorized']);

        $id = sanitize_text_field($_POST['id'] ?? '');
        if (empty($id)) {
            wp_send_json_error(['message' => 'Registration ID required.']);
        }

        // Delete guests first (foreign key safety)
        Fuse_Supabase_API::request('fuse_registration_guests?registration_id=eq.' . $id, 'DELETE');

        // Delete the registration
        $result = Fuse_Supabase_API::request('fuse_registrations?id=eq.' . $id, 'DELETE');

        if (isset($result['error'])) {
            wp_send_json_error(['message' => $result['error']]);
        }

        wp_send_json_success(['message' => 'Registration deleted.']);
    }

    // --- ADMIN: Test GHL connection ---
    public static function admin_test_ghl() {
        check_ajax_referer('fuse_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized']);

        $result = Fuse_GHL_API::test_connection();

        if ($result['success']) {
            wp_send_json_success(['message' => $result['message']]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }

    // --- ADMIN: Lookup member in Supabase ---
    public static function admin_lookup_member() {
        check_ajax_referer('fuse_admin_nonce', 'nonce');
        if (!current_user_can(FUSE_REG_CAP)) wp_send_json_error(['message' => 'Unauthorized']);

        $email = sanitize_email($_POST['email'] ?? '');
        if (empty($email)) {
            wp_send_json_error(['message' => 'Email is required.']);
        }

        $result = Fuse_Supabase_API::check_member($email);

        if (isset($result['error'])) {
            wp_send_json_error(['message' => 'Unable to look up member: ' . $result['error']]);
        }

        wp_send_json_success($result);
    }

    // --- ADMIN: Get single registration ---
    public static function admin_get_registration() {
        check_ajax_referer('fuse_admin_nonce', 'nonce');
        if (!current_user_can(FUSE_REG_CAP)) wp_send_json_error(['message' => 'Unauthorized']);

        $id = sanitize_text_field($_POST['id'] ?? '');
        $result = Fuse_Supabase_API::get_registration($id);
        wp_send_json_success($result);
    }

    // --- ADMIN: Save (create or update) registration ---
    public static function admin_save_registration() {
        check_ajax_referer('fuse_admin_nonce', 'nonce');
        if (!current_user_can(FUSE_REG_CAP)) wp_send_json_error(['message' => 'Unauthorized']);

        $id = sanitize_text_field($_POST['registration_id'] ?? '');
        $event_id = get_option('fuse_event_id', '');

        $data = [
            'fuse_event_id'      => $event_id,
            'email'              => sanitize_email($_POST['email'] ?? ''),
            'first_name'         => sanitize_text_field($_POST['first_name'] ?? ''),
            'last_name'          => sanitize_text_field($_POST['last_name'] ?? ''),
            'full_name'          => sanitize_text_field(($_POST['first_name'] ?? '') . ' ' . ($_POST['last_name'] ?? '')),
            'phone'              => sanitize_text_field($_POST['phone'] ?? ''),
            'company'            => sanitize_text_field($_POST['company'] ?? ''),
            'ticket_type'        => sanitize_text_field($_POST['ticket_type'] ?? 'general_admission'),
            'tier'               => sanitize_text_field($_POST['tier'] ?? '') ?: null,
            'purchase_type'      => sanitize_text_field($_POST['purchase_type'] ?? 'purchased'), // pending | purchased | claimed
            // VIP members always include Hall of AIME
            'has_hall_of_aime'   => (sanitize_text_field($_POST['ticket_type'] ?? '') === 'vip')
                                        ? true
                                        : (bool) ($_POST['has_hall_of_aime'] ?? false),
            'has_wmn_at_fuse'    => (bool) ($_POST['has_wmn_at_fuse'] ?? false),
            'has_vip_luncheon'   => (bool) ($_POST['has_vip_luncheon'] ?? false),
            'has_vetted_va'      => (bool) ($_POST['has_vetted_va'] ?? false),
            'registration_source' => 'admin_manual',
            'notes'              => sanitize_textarea_field($_POST['notes'] ?? ''),
            'preferred_name'     => sanitize_text_field($_POST['preferred_name'] ?? ''),
            'gender'             => sanitize_text_field($_POST['gender'] ?? ''),
            'fuse_attendance'    => sanitize_text_field($_POST['fuse_attendance'] ?? ''),
        ];

        if ($id) {
            // Update existing
            unset($data['fuse_event_id']); // don't change event on update
            $result = Fuse_Supabase_API::update_registration($id, $data);
            $action = 'updated';
            $reg_id = $id;
        } else {
            // Create new
            $result = Fuse_Supabase_API::create_registration($data);
            $action = 'created';
            $reg_id = $result[0]['id'] ?? ($result['id'] ?? null);
        }

        if (isset($result['error'])) {
            wp_send_json_error(['message' => $result['error']]);
        }

        // Save guests — replace existing guests with the current list
        if ($reg_id) {
            $primary_ticket_type = sanitize_text_field($_POST['ticket_type'] ?? 'general_admission');
            $guests_json = stripslashes($_POST['guests'] ?? '[]');
            $guests = json_decode($guests_json, true) ?: [];

            // Delete existing guests for this registration
            Fuse_Supabase_API::request('fuse_registration_guests?registration_id=eq.' . $reg_id, 'DELETE');

            // Re-create from current list
            foreach ($guests as $i => $guest) {
                $full_name = sanitize_text_field($guest['full_name'] ?? ($guest['name'] ?? ''));
                if (empty($full_name)) continue;
                // VIP: first guest (index 0) gets vip_guest (free + HOA included).
                // Additional VIP guests (index 1+) pay regular guest rate → guest_ticket.
                if ($primary_ticket_type === 'vip') {
                    $guest_ticket_type = ($i === 0) ? 'vip_guest' : 'guest_ticket';
                } else {
                    $provided_type = sanitize_text_field($guest['ticket_type'] ?? '');
                    $guest_ticket_type = !empty($provided_type) ? $provided_type : 'guest_ticket';
                }
                $g_has_hoa          = ($guest_ticket_type === 'vip_guest') || !empty($guest['hasHoa']) || !empty($guest['has_hall_of_aime']);
                $g_has_wmn          = !empty($guest['hasWmn']) || !empty($guest['has_wmn_at_fuse']);
                $g_has_vip_luncheon = !empty($guest['hasVipLuncheon']) || !empty($guest['has_vip_luncheon']);
                $g_has_vetted_va    = !empty($guest['hasVettedVa']) || !empty($guest['has_vetted_va']);
                Fuse_Supabase_API::create_guest_safe([
                    'registration_id'  => $reg_id,
                    'full_name'        => $full_name,
                    'email'            => sanitize_email($guest['email'] ?? ''),
                    'phone'            => sanitize_text_field($guest['phone'] ?? ''),
                    'ticket_type'      => $guest_ticket_type,
                    'has_hall_of_aime' => $g_has_hoa,
                    'has_wmn_at_fuse'  => $g_has_wmn,
                    'has_vip_luncheon' => $g_has_vip_luncheon,
                    'has_vetted_va'    => $g_has_vetted_va,
                ]);
            }
        }

        // Sync to GHL — trigger confirmation workflow only for new registrations that are
        // already paid/claimed. Pending (invoice) registrations must wait for invoice.paid webhook.
        $send_confirmation = ($action === 'created') && ($data['purchase_type'] !== 'pending');
        Fuse_GHL_API::process_registration($data, $send_confirmation);

        wp_send_json_success(['message' => "Registration $action successfully.", 'data' => $result]);
    }

    // --- ADMIN: Send Stripe Invoice ---
    public static function admin_send_invoice() {
        check_ajax_referer('fuse_admin_nonce', 'nonce');
        if (!current_user_can(FUSE_REG_CAP)) wp_send_json_error(['message' => 'Unauthorized']);

        $email      = sanitize_email($_POST['email'] ?? '');
        $reg_id     = sanitize_text_field($_POST['registration_id'] ?? '');
        $items_json = stripslashes($_POST['line_items'] ?? '[]');
        $line_items = json_decode($items_json, true);
        $inv_name   = trim(sanitize_text_field($_POST['first_name'] ?? '') . ' ' . sanitize_text_field($_POST['last_name'] ?? ''));

        if (empty($email) || empty($line_items)) {
            wp_send_json_error(['message' => 'Email and at least one line item required.']);
        }

        $result = Fuse_Stripe_API::create_invoice($email, $line_items, [
            'fuse_registration_id' => $reg_id,
            'source'               => 'fuse_admin',
        ], $inv_name);

        if (isset($result['error'])) {
            wp_send_json_error(['message' => $result['error']]);
        }

        // Mark the registration as pending-payment and store the Stripe invoice ID.
        // purchase_type will be changed to 'purchased' when the invoice.paid webhook fires.
        if ($reg_id && !empty($result['invoice_id'])) {
            // Try with stripe_invoice_id first; if the column doesn't exist, fall back to
            // just updating purchase_type so the row is at least marked pending.
            $update_result = Fuse_Supabase_API::update_registration($reg_id, [
                'purchase_type'     => 'pending',
                'stripe_invoice_id' => $result['invoice_id'],
            ]);
            if (isset($update_result['error'])) {
                error_log('[Fuse send_invoice] stripe_invoice_id column may not exist — retrying without it: ' . $update_result['error']);
                Fuse_Supabase_API::update_registration($reg_id, [
                    'purchase_type' => 'pending',
                ]);
            }
        }

        wp_send_json_success($result);
    }

    // --- ADMIN: Set up / verify all Stripe products ---
    public static function admin_setup_stripe_products() {
        check_ajax_referer('fuse_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized']);

        // Map of option key → product config
        $products = [
            // GA
            'fuse_stripe_price_ga_early_bird'      => ['name' => 'Fuse 2026 – General Admission (Early Bird)', 'amount' => 69900],
            'fuse_stripe_price_ga'                 => ['name' => 'Fuse 2026 – General Admission',              'amount' => 89900],
            'fuse_stripe_price_ga_member'          => ['name' => 'Fuse 2026 – General Admission (Member)',     'amount' => 0],
            // VIP
            'fuse_stripe_price_vip'                => ['name' => 'Fuse 2026 – VIP Ticket',                     'amount' => 0],
            // Guest
            'fuse_stripe_price_guest_regular'      => ['name' => 'Fuse 2026 – Guest Ticket',                  'amount' => 34900],
            'fuse_stripe_price_guest_vip'          => ['name' => 'Fuse 2026 – VIP Guest Ticket (Included)',    'amount' => 0],
            // HOA
            'fuse_stripe_price_hoa_nonmember_early'=> ['name' => 'Fuse 2026 – Hall of AIME (Early Bird)',      'amount' => 29900],
            'fuse_stripe_price_hoa_nonmember'      => ['name' => 'Fuse 2026 – Hall of AIME (Regular)',         'amount' => 34900],
            'fuse_stripe_price_hoa_member'         => ['name' => 'Fuse 2026 – Hall of AIME (Member)',          'amount' => 19900],
            'fuse_stripe_price_hoa_vip'            => ['name' => 'Fuse 2026 – Hall of AIME (VIP)',             'amount' => 0],
            // WMN
            'fuse_stripe_price_wmn'                => ['name' => 'Fuse 2026 – WMN at Fuse',                    'amount' => 0],
        ];

        $results = [];
        foreach ($products as $option_key => $config) {
            $existing = get_option($option_key, '');
            if ($existing) {
                // Verify the existing Price ID
                $price = Fuse_Stripe_API::get_price($existing);
                $results[$option_key] = [
                    'status'    => isset($price['id']) ? 'existing' : 'invalid',
                    'price_id'  => $existing,
                    'name'      => $config['name'],
                    'livemode'  => $price['livemode'] ?? null,
                    'active'    => $price['active'] ?? null,
                ];
            } else {
                // Create product + price in Stripe
                $price = Fuse_Stripe_API::create_product_price($config['name'], $config['amount']);
                if (isset($price['id'])) {
                    update_option($option_key, $price['id']);
                    $results[$option_key] = [
                        'status'    => 'created',
                        'price_id'  => $price['id'],
                        'name'      => $config['name'],
                        'livemode'  => $price['livemode'] ?? null,
                    ];
                } else {
                    $results[$option_key] = [
                        'status'  => 'error',
                        'name'    => $config['name'],
                        'message' => $price['error']['message'] ?? 'Unknown error',
                    ];
                }
            }
        }

        wp_send_json_success($results);
    }

    // --- ADMIN: Verify a single Stripe Price ID ---
    public static function admin_verify_stripe_price() {
        check_ajax_referer('fuse_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized']);

        $price_id = sanitize_text_field($_POST['price_id'] ?? '');
        if (empty($price_id)) wp_send_json_error(['message' => 'No Price ID provided']);

        $price = Fuse_Stripe_API::get_price($price_id);

        if (isset($price['id'])) {
            wp_send_json_success([
                'price_id'  => $price['id'],
                'amount'    => $price['unit_amount'],
                'currency'  => strtoupper($price['currency']),
                'active'    => $price['active'],
                'livemode'  => $price['livemode'],
            ]);
        } else {
            wp_send_json_error(['message' => $price['error']['message'] ?? 'Invalid or unknown Price ID']);
        }
    }

    // --- ADMIN: Get dashboard stats ---
    public static function admin_get_stats() {
        check_ajax_referer('fuse_admin_nonce', 'nonce');
        if (!current_user_can(FUSE_REG_CAP)) wp_send_json_error(['message' => 'Unauthorized']);

        $result = Fuse_Supabase_API::get_stats();
        wp_send_json_success($result);
    }

    // --- ADMIN: Export registrations ---
    public static function admin_export() {
        check_ajax_referer('fuse_admin_nonce', 'nonce');
        if (!current_user_can(FUSE_REG_CAP)) wp_send_json_error(['message' => 'Unauthorized']);

        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        $event_id = get_option('fuse_event_id', '');

        $regs = Fuse_Supabase_API::request(
            'fuse_registrations?fuse_event_id=eq.' . $event_id .
            '&select=*,fuse_registration_guests(*)&order=created_at.desc'
        );

        if (isset($regs['error'])) {
            wp_send_json_error(['message' => $regs['error']]);
        }

        wp_send_json_success(['registrations' => $regs, 'format' => $format]);
    }
}

// ============================================================
// FRONTEND SHORTCODE
// ============================================================
class Fuse_Registration_Shortcode {

    public static function init() {
        add_shortcode('fuse_registration', [__CLASS__, 'render']);
        add_shortcode('fuse_registration_nonmember', [__CLASS__, 'render_nonmember']);
        add_shortcode('fuse_registration_success', [__CLASS__, 'render_success']);
    }

    public static function render($atts) {
        // Enqueue assets
        wp_enqueue_style('fuse-reg-frontend', FUSE_REG_URL . 'assets/css/frontend.css', [], FUSE_REG_VERSION);
        wp_enqueue_script('fuse-reg-frontend', FUSE_REG_URL . 'assets/js/registration.js', ['jquery'], FUSE_REG_VERSION, true);

        // Pass config to JS
        $early_bird_end = get_option('fuse_early_bird_end_date', '');
        $is_early_bird = !empty($early_bird_end) && strtotime($early_bird_end) > time();

        wp_localize_script('fuse-reg-frontend', 'fuseReg', [
            'ajaxUrl'    => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce('fuse_reg_nonce'),
            'isEarlyBird' => $is_early_bird,
            'pricing'    => [
                'ga_early_bird'        => 69900,   // $699
                'ga_regular'           => 89900,   // $899
                'ga_member'            => 0,       // member claimed — $0
                'vip'                  => 0,       // VIP claimed — $0
                'hoa_member'           => 19900,   // member HOA — $199
                'hoa_nonmember_early'  => 29900,   // HOA early bird — $299
                'hoa_nonmember'        => 34900,   // HOA regular — $349
                'hoa_vip'              => 0,       // VIP HOA (included) — $0
                'guest_regular'        => 34900,   // all guests — $349
                'guest_vip'            => 0,       // VIP included guest — $0
                'wmn'                  => 0,       // WMN at Fuse — $0
                'vip_luncheon'         => 0,       // set via Stripe price lookup
                'vetted_va'            => 0,       // set via Stripe price lookup
            ],
            'priceIds'   => [
                'ga'                    => get_option('fuse_stripe_price_ga', ''),
                'ga_early_bird'         => get_option('fuse_stripe_price_ga_early_bird', ''),
                'ga_member'             => get_option('fuse_stripe_price_ga_member', ''),
                'vip'                   => get_option('fuse_stripe_price_vip', ''),
                'hoa_member'            => get_option('fuse_stripe_price_hoa_member', ''),
                'hoa_nonmember_early'   => get_option('fuse_stripe_price_hoa_nonmember_early', ''),
                'hoa_nonmember'         => get_option('fuse_stripe_price_hoa_nonmember', ''),
                'hoa_vip'               => get_option('fuse_stripe_price_hoa_vip', ''),
                'guest_regular'         => get_option('fuse_stripe_price_guest_regular', ''),
                'guest_vip'             => get_option('fuse_stripe_price_guest_vip', ''),
                'wmn'                   => get_option('fuse_stripe_price_wmn', ''),
                'vip_luncheon'          => get_option('fuse_stripe_price_vip_luncheon', ''),
                'vetted_va'             => get_option('fuse_stripe_price_vetted_va', ''),
            ],
            'recaptchaSiteKey' => get_option('fuse_recaptcha_site_key', ''),
        ]);

        // Enqueue reCAPTCHA v3 if a site key is configured
        $recaptcha_site_key = get_option('fuse_recaptcha_site_key', '');
        if (!empty($recaptcha_site_key)) {
            wp_enqueue_script(
                'google-recaptcha',
                'https://www.google.com/recaptcha/api.js?render=' . esc_attr($recaptcha_site_key),
                [],
                null,
                true
            );
        }

        ob_start();
        include FUSE_REG_PATH . 'templates/registration-form.php';
        return ob_get_clean();
    }

    /**
     * [fuse_registration_nonmember] shortcode
     *
     * Renders the non-member registration form — skips the membership check step
     * and goes straight to ticket selection with non-member pricing.
     */
    public static function render_nonmember($atts) {
        wp_enqueue_style('fuse-reg-frontend', FUSE_REG_URL . 'assets/css/frontend.css', [], FUSE_REG_VERSION);
        wp_enqueue_script('fuse-reg-frontend', FUSE_REG_URL . 'assets/js/registration.js', ['jquery'], FUSE_REG_VERSION, true);

        $early_bird_end = get_option('fuse_early_bird_end_date', '');
        $is_early_bird  = !empty($early_bird_end) && strtotime($early_bird_end) > time();

        wp_localize_script('fuse-reg-frontend', 'fuseReg', [
            'ajaxUrl'         => admin_url('admin-ajax.php'),
            'nonce'           => wp_create_nonce('fuse_reg_nonce'),
            'isEarlyBird'     => $is_early_bird,
            'nonMemberMode'   => true,   // ← skips member check, enables editable email
            'pricing'         => [
                'ga_early_bird'        => 69900,
                'ga_regular'           => 89900,
                'hoa_nonmember_early'  => 29900,
                'hoa_nonmember'        => 34900,
                'guest_regular'        => 34900,   // all guests — $349
                'wmn'                  => 0,
                'vip_luncheon'         => 0,       // set via Stripe price lookup
                'vetted_va'            => 0,       // set via Stripe price lookup
            ],
            'priceIds'        => [
                'ga'                   => get_option('fuse_stripe_price_ga', ''),
                'ga_early_bird'        => get_option('fuse_stripe_price_ga_early_bird', ''),
                'hoa_nonmember_early'  => get_option('fuse_stripe_price_hoa_nonmember_early', ''),
                'hoa_nonmember'        => get_option('fuse_stripe_price_hoa_nonmember', ''),
                'guest_regular'        => get_option('fuse_stripe_price_guest_regular', ''),
                'wmn'                  => get_option('fuse_stripe_price_wmn', ''),
                'vip_luncheon'         => get_option('fuse_stripe_price_vip_luncheon', ''),
                'vetted_va'            => get_option('fuse_stripe_price_vetted_va', ''),
            ],
            'recaptchaSiteKey' => get_option('fuse_recaptcha_site_key', ''),
        ]);

        $recaptcha_site_key = get_option('fuse_recaptcha_site_key', '');
        if (!empty($recaptcha_site_key)) {
            wp_enqueue_script(
                'google-recaptcha',
                'https://www.google.com/recaptcha/api.js?render=' . esc_attr($recaptcha_site_key),
                [],
                null,
                true
            );
        }

        // Pricing vars available inside the template
        $nm_ticket_price   = $is_early_bird ? 699 : 899;  // dollars
        $nm_membership_low = 199;                           // dollars — lowest membership tier
        $nm_savings        = $nm_ticket_price - $nm_membership_low;

        ob_start();
        include FUSE_REG_PATH . 'templates/registration-form-nonmember.php';
        return ob_get_clean();
    }

    /**
     * [fuse_registration_success] shortcode
     *
     * Place this on the /fuse/registration-success/ page.
     * When Stripe redirects back with ?session_id=..., this shortcode
     * fetches the session from Stripe and processes the registration
     * (saves to Supabase, creates guests, syncs GHL, triggers confirmation).
     *
     * This acts as a reliable fallback — the webhook does the same thing
     * but may not be configured yet. The shared function is idempotent so
     * running it twice (webhook + success page) is safe.
     */
    public static function render_success($atts) {
        $session_id = sanitize_text_field($_GET['session_id'] ?? '');

        ob_start();

        if (empty($session_id)) {
            // No session — generic success message (e.g., free registration)
            echo '<div class="fuse-success-box">';
            echo '<h2>🎉 Registration Complete!</h2>';
            echo '<p>Thank you for registering for Fuse 2026. A confirmation email is on its way.</p>';
            echo '</div>';
            return ob_get_clean();
        }

        // Retrieve the Stripe session to get all the details
        $session = Fuse_Stripe_API::retrieve_checkout_session($session_id);

        if (isset($session['error'])) {
            // Session retrieval failed — show generic success; webhook will still process it
            echo '<div class="fuse-success-box">';
            echo '<h2>🎉 Payment Received!</h2>';
            echo '<p>Your payment was successful. We\'ll send a confirmation email shortly.</p>';
            echo '</div>';
            return ob_get_clean();
        }

        // Only process completed sessions
        if (($session['payment_status'] ?? '') !== 'paid' && ($session['status'] ?? '') !== 'complete') {
            echo '<div class="fuse-notice fuse-notice--warning">';
            echo '<p>Your payment is still being processed. You\'ll receive a confirmation email once it clears.</p>';
            echo '</div>';
            return ob_get_clean();
        }

        // Process the registration (idempotent — safe if webhook already ran)
        $result = fuse_reg_process_paid_registration($session);

        $meta = $session['metadata'] ?? [];
        $first_name = sanitize_text_field($meta['first_name'] ?? '');
        $email      = sanitize_email($meta['email'] ?? $session['customer_details']['email'] ?? '');

        echo '<div class="fuse-success-box">';

        if (isset($result['error'])) {
            // Registration processing failed — payment went through but we need to follow up
            echo '<h2>🎉 Payment Confirmed!</h2>';
            echo '<p>Hi ' . esc_html($first_name ?: 'there') . ', your payment was received successfully.</p>';
            echo '<p>We\'re finalising your registration record — you\'ll receive a confirmation email at <strong>' . esc_html($email) . '</strong> shortly. If you don\'t hear from us within 24 hours, please contact us.</p>';
            error_log('Fuse success page: registration processing error after payment — ' . $result['error'] . ' — session: ' . $session_id);
        } else {
            echo '<h2>🎉 You\'re registered for Fuse 2026!</h2>';
            echo '<p>Hi ' . esc_html($first_name ?: 'there') . ', your registration is confirmed.</p>';
            echo '<p>A confirmation email has been sent to <strong>' . esc_html($email) . '</strong>.</p>';

            // Show what they registered for
            $ticket_type = sanitize_text_field($meta['ticket_type'] ?? '');
            $has_hoa     = ($meta['has_hall_of_aime'] ?? '0') === '1';
            $has_wmn     = ($meta['has_wmn_at_fuse'] ?? '0') === '1';

            echo '<ul class="fuse-success-items">';
            if ($ticket_type === 'general_admission') {
                echo '<li>General Admission ticket</li>';
            } elseif ($ticket_type === 'vip') {
                echo '<li>VIP ticket</li>';
            }
            if ($has_hoa) echo '<li>Hall of AIME</li>';
            if ($has_wmn) echo '<li>WMN at Fuse</li>';

            // Show guests — read from the compact pipe-delimited string in Stripe metadata.
            // Format: "Name|email|phone~Name2|email2|phone2"
            $guests_compact = $meta['guests_compact'] ?? '';
            if (!empty($guests_compact)) {
                foreach (explode('~', $guests_compact) as $part) {
                    $fields = explode('|', $part);
                    $gname  = sanitize_text_field($fields[0] ?? '');
                    if ($gname) echo '<li>Guest: ' . esc_html($gname) . '</li>';
                }
            }
            echo '</ul>';

            echo '<p>We look forward to seeing you at Fuse 2026!</p>';
        }

        echo '</div>';

        // Inline styles for the success box
        echo '<style>
            .fuse-success-box { max-width: 600px; margin: 40px auto; padding: 40px; background: #f0fdf4; border: 2px solid #16a34a; border-radius: 12px; text-align: center; }
            .fuse-success-box h2 { color: #15803d; margin-bottom: 16px; font-size: 28px; }
            .fuse-success-box p { color: #374151; margin-bottom: 12px; font-size: 16px; }
            .fuse-success-items { list-style: none; padding: 0; margin: 20px 0; }
            .fuse-success-items li { display: inline-block; margin: 4px 8px; padding: 6px 16px; background: #dcfce7; border-radius: 20px; color: #15803d; font-weight: 500; }
            .fuse-notice--warning { max-width: 600px; margin: 40px auto; padding: 24px; background: #fffbeb; border: 1px solid #f59e0b; border-radius: 8px; text-align: center; }
        </style>';

        return ob_get_clean();
    }
}

// ============================================================
// ENQUEUE ADMIN ASSETS
// ============================================================
function fuse_reg_admin_assets($hook) {
    // Only load on our plugin pages
    if (strpos($hook, 'fuse-reg') === false && strpos($hook, 'fuse-registrations') === false) return;

    wp_enqueue_style('fuse-reg-admin', FUSE_REG_URL . 'assets/css/admin.css', [], FUSE_REG_VERSION);
    wp_enqueue_script('fuse-reg-admin', FUSE_REG_URL . 'assets/js/admin.js', ['jquery'], FUSE_REG_VERSION, true);

    $early_bird_end = get_option('fuse_early_bird_end_date', '');
    $is_early_bird  = !empty($early_bird_end) && strtotime($early_bird_end) > time();

    // Build price ID map
    $price_id_map = [
        'ga'                  => get_option('fuse_stripe_price_ga', ''),
        'ga_early_bird'       => get_option('fuse_stripe_price_ga_early_bird', ''),
        'hoa_member'          => get_option('fuse_stripe_price_hoa_member', ''),
        'hoa_nonmember_early' => get_option('fuse_stripe_price_hoa_nonmember_early', ''),
        'hoa_nonmember'       => get_option('fuse_stripe_price_hoa_nonmember', ''),
        'guest_regular'       => get_option('fuse_stripe_price_guest_regular', ''),
        'wmn'                 => get_option('fuse_stripe_price_wmn', ''),
        'vip_luncheon'        => get_option('fuse_stripe_price_vip_luncheon', ''),
        'vetted_va'           => get_option('fuse_stripe_price_vetted_va', ''),
    ];

    // Fetch live amounts from Stripe, cached for 1 hour so we don't hit the API on every page load.
    // Falls back to hardcoded defaults if a price ID isn't configured or Stripe is unreachable.
    $pricing_defaults = [
        'ga'                  => 89900,
        'ga_early_bird'       => 69900,
        'hoa_member'          => 19900,
        'hoa_nonmember_early' => 24900,
        'hoa_nonmember'       => 34900,
        'guest_regular'       => 34900,
        'wmn'                 => 0,
        'vip_luncheon'        => 0,
        'vetted_va'           => 0,
    ];
    $cached_pricing = get_transient('fuse_stripe_pricing_amounts');
    if ($cached_pricing === false) {
        $cached_pricing = [];
        foreach ($price_id_map as $key => $price_id) {
            if (!empty($price_id)) {
                $price = Fuse_Stripe_API::get_price($price_id);
                if (isset($price['unit_amount'])) {
                    $cached_pricing[$key] = intval($price['unit_amount']);
                }
            }
        }
        set_transient('fuse_stripe_pricing_amounts', $cached_pricing, HOUR_IN_SECONDS);
    }
    // Merge: Stripe amounts win where available, fall back to hardcoded defaults
    $pricing = array_merge($pricing_defaults, $cached_pricing);

    wp_localize_script('fuse-reg-admin', 'fuseAdmin', [
        'ajaxUrl'          => admin_url('admin-ajax.php'),
        'nonce'            => wp_create_nonce('fuse_admin_nonce'),
        'isEarlyBird'      => $is_early_bird,
        'pricing'          => $pricing,
        'priceIds'         => $price_id_map,
        'registrationsUrl' => admin_url('admin.php?page=fuse-registrations'),
    ]);
}
add_action('admin_enqueue_scripts', 'fuse_reg_admin_assets');

// ============================================================
// SHARED: Process a completed paid registration from a Stripe session
// Called by both the webhook AND the success-page shortcode.
// Returns ['success'=>true, 'registration_id'=>..., 'already_processed'=>bool]
//      or ['error' => '...']
// ============================================================
function fuse_reg_process_paid_registration($session) {
    if (empty($session['id'])) {
        return ['error' => 'Invalid session data'];
    }

    $session_id = $session['id'];
    $meta = $session['metadata'] ?? [];

    // ── Idempotency guard ────────────────────────────────────────────────
    // Check if we already saved a registration for this Stripe session.
    // Graceful: if stripe_session_id column doesn't exist yet, the query
    // will return an error — we just skip the check and proceed.
    $existing = Fuse_Supabase_API::request(
        'fuse_registrations?stripe_session_id=eq.' . urlencode($session_id) . '&select=id',
        'GET'
    );
    if (!empty($existing) && !isset($existing['error']) && is_array($existing) && count($existing) > 0) {
        return ['success' => true, 'already_processed' => true, 'registration_id' => $existing[0]['id']];
    }

    // ── Build registration record ────────────────────────────────────────
    $email      = sanitize_email($meta['email'] ?? $session['customer_email'] ?? $session['customer_details']['email'] ?? '');
    $first_name = sanitize_text_field($meta['first_name'] ?? '');
    $last_name  = sanitize_text_field($meta['last_name'] ?? '');

    $data = [
        'fuse_event_id'       => $meta['fuse_event_id'] ?? get_option('fuse_event_id', ''),
        'email'               => $email,
        'first_name'          => $first_name,
        'last_name'           => $last_name,
        'full_name'           => trim($first_name . ' ' . $last_name),
        'phone'               => sanitize_text_field($meta['phone'] ?? ''),
        'company'             => sanitize_text_field($meta['company'] ?? ''),
        'ticket_type'         => sanitize_text_field($meta['ticket_type'] ?? 'general_admission'),
        'tier'                => !empty($meta['tier']) ? sanitize_text_field($meta['tier']) : null,
        'purchase_type'       => 'purchased',
        // VIP members always include Hall of AIME
        'has_hall_of_aime'    => (sanitize_text_field($meta['ticket_type'] ?? '') === 'vip')
                                    ? true
                                    : (($meta['has_hall_of_aime'] ?? '0') === '1'),
        'has_wmn_at_fuse'     => ($meta['has_wmn_at_fuse'] ?? '0') === '1',
        'has_vip_luncheon'    => ($meta['has_vip_luncheon'] ?? '0') === '1',
        'has_vetted_va'       => ($meta['has_vetted_va'] ?? '0') === '1',
        'registration_source' => 'wordpress',
        'preferred_name'      => sanitize_text_field($meta['preferred_name'] ?? ''),
        'gender'              => sanitize_text_field($meta['gender'] ?? ''),
        'marketing_consent'   => ($meta['marketing_consent'] ?? '0') === '1',
        'user_id'             => !empty($meta['user_id']) ? sanitize_text_field($meta['user_id']) : null,
    ];

    $result = Fuse_Supabase_API::create_registration($data);

    if (isset($result['error'])) {
        error_log('Fuse Stripe: Supabase create_registration error — ' . $result['error']);
        return ['error' => $result['error']];
    }

    $reg_id = $result[0]['id'] ?? null;

    // ── Store Stripe session ID for idempotency (best-effort) ────────────
    // Requires stripe_session_id and stripe_payment_intent columns in the
    // fuse_registrations table. If the columns don't exist, this call fails
    // silently — registration is already saved above.
    if ($reg_id) {
        Fuse_Supabase_API::update_registration($reg_id, [
            'stripe_session_id'     => $session_id,
            'stripe_payment_intent' => $session['payment_intent'] ?? null,
        ]);
    }

    // ── Guest records ─────────────────────────────────────────────────────
    if ($reg_id) {
        $primary_ticket = sanitize_text_field($meta['ticket_type'] ?? 'general_admission');

        // --- Retrieve guest data, trying each storage method in order ---
        $guests = [];

        // 1. Transient (current preferred approach — no Stripe metadata length limits)
        $guests_key = $meta['guests_key'] ?? '';
        error_log('[Fuse process_paid] session=' . $session_id . ' — guests_key from meta: "' . $guests_key . '"');
        if (!empty($guests_key)) {
            $raw = get_transient($guests_key);
            error_log('[Fuse process_paid] transient raw value: ' . ($raw !== false ? substr($raw, 0, 500) : 'FALSE (not found)'));
            if ($raw) {
                $guests = json_decode($raw, true) ?: [];
                error_log('[Fuse process_paid] guests from transient: ' . count($guests));
            }
        }

        // 2. Indexed metadata fields (previous approach — fallback for in-flight sessions)
        if (empty($guests)) {
            $guest_count = intval($meta['guest_count'] ?? -1);
            error_log('[Fuse process_paid] fallback: guest_count from meta = ' . $guest_count);
            if ($guest_count > 0) {
                for ($gi = 0; $gi < $guest_count; $gi++) {
                    $gname = $meta["guest_{$gi}_name"] ?? '';
                    if (!empty($gname)) {
                        $guests[] = [
                            'name'  => $gname,
                            'email' => $meta["guest_{$gi}_email"] ?? '',
                            'phone' => $meta["guest_{$gi}_phone"] ?? '',
                        ];
                    }
                }
            }
        }

        // 3. Compact pipe-delimited string stored directly in Stripe metadata (reliable backup).
        //    Format: "Name|email|phone~Name2|email2|phone2"
        //    This is the most reliable fallback — no dependency on WordPress caching.
        if (empty($guests)) {
            $compact = $meta['guests_compact'] ?? '';
            error_log('[Fuse process_paid] fallback guests_compact: "' . $compact . '"');
            if (!empty($compact)) {
                foreach (explode('~', $compact) as $part) {
                    $fields = explode('|', $part);
                    $gname  = sanitize_text_field($fields[0] ?? '');
                    if (!empty($gname)) {
                        $guests[] = [
                            'name'           => $gname,
                            'email'          => sanitize_email($fields[1] ?? ''),
                            'phone'          => sanitize_text_field($fields[2] ?? ''),
                            'hasHoa'         => ($fields[3] ?? '0') === '1',
                            'hasWmn'         => ($fields[4] ?? '0') === '1',
                            'hasVipLuncheon' => ($fields[5] ?? '0') === '1',
                            'hasVettedVa'    => ($fields[6] ?? '0') === '1',
                        ];
                    }
                }
                error_log('[Fuse process_paid] guests from compact string: ' . count($guests));
            }
        }

        // 4. Legacy single guests_json blob (oldest fallback)
        if (empty($guests)) {
            $guests_raw = $meta['guests_json'] ?? '';
            if (!empty($guests_raw)) {
                $guests = json_decode($guests_raw, true) ?: [];
            }
        }

        // Determine correct base guest ticket type
        // All guests pay the same $349 rate; VIP first guest is free (overridden in loop).
        if ($primary_ticket === 'vip') {
            $guest_ticket_type = 'vip_guest'; // overridden per-guest in loop below
        } else {
            $guest_ticket_type = 'guest_ticket';
        }
        error_log('[Fuse process_paid] total guests to save: ' . count($guests) . ' — primary_ticket: ' . $primary_ticket . ' — guest_ticket_type: ' . $guest_ticket_type);

        if (is_array($guests)) {
            foreach ($guests as $i => $guest) {
                $guest_name = sanitize_text_field($guest['name'] ?? ($guest['full_name'] ?? ''));
                error_log('[Fuse process_paid] guest[' . $i . '] name="' . $guest_name . '" email="' . ($guest['email'] ?? '') . '"');
                if (empty($guest_name)) {
                    error_log('[Fuse process_paid] guest[' . $i . '] skipped — empty name');
                    continue;
                }
                // VIP: first guest (index 0) is free + includes Hall of AIME (vip_guest).
                // Additional VIP guests (index 1+) pay the regular guest rate (guest_ticket).
                if ($primary_ticket === 'vip') {
                    $gt = ($i === 0) ? 'vip_guest' : 'guest_ticket';
                } else {
                    $gt = $guest_ticket_type; // already set above for non-VIP
                }
                // HOA is true for vip_guest (always included) OR if guest explicitly selected it
                $guest_has_hoa          = ($gt === 'vip_guest') || !empty($guest['hasHoa']) || !empty($guest['has_hall_of_aime']);
                $guest_has_wmn          = !empty($guest['hasWmn']) || !empty($guest['has_wmn_at_fuse']);
                $guest_has_vip_luncheon = !empty($guest['hasVipLuncheon']) || !empty($guest['has_vip_luncheon']);
                $guest_has_vetted_va    = !empty($guest['hasVettedVa']) || !empty($guest['has_vetted_va']);

                $guest_result = Fuse_Supabase_API::create_guest_safe([
                    'registration_id'  => $reg_id,
                    'full_name'        => $guest_name,
                    'email'            => sanitize_email($guest['email'] ?? ''),
                    'phone'            => sanitize_text_field($guest['phone'] ?? ''),
                    'ticket_type'      => $gt,
                    'has_hall_of_aime' => $guest_has_hoa,
                    'has_wmn_at_fuse'  => $guest_has_wmn,
                    'has_vip_luncheon' => $guest_has_vip_luncheon,
                    'has_vetted_va'    => $guest_has_vetted_va,
                ]);
                error_log('[Fuse process_paid] guest[' . $i . '] create_guest result: ' . json_encode($guest_result));
            }
        }
    }

    // ── Mark member ticket as claimed ────────────────────────────────────
    $user_id = $data['user_id'] ?? null;
    if ($user_id && $data['purchase_type'] === 'purchased' && !empty($data['tier'])) {
        // For members who purchased (Premium/Elite with HOA, or non-member), mark claimed
        Fuse_Supabase_API::mark_ticket_claimed($user_id, 2026);
    }

    // ── Sync to GHL (creates contact, tags, triggers confirmation workflow) ─
    Fuse_GHL_API::process_registration($data);

    return ['success' => true, 'already_processed' => false, 'registration_id' => $reg_id];
}

// ============================================================
// REST API ENDPOINTS (Conexsys + Stripe Webhook)
// ============================================================
function fuse_reg_register_routes() {
    // Stripe webhook — no auth required (Stripe verifies via signature)
    register_rest_route('fuse/v1', '/stripe-webhook', [
        'methods'             => 'POST',
        'callback'            => 'fuse_reg_handle_stripe_webhook',
        'permission_callback' => '__return_true',
    ]);

    // Conexsys attendee list — API key required
    register_rest_route('fuse/v1', '/conexsys', [
        'methods'             => 'GET',
        'callback'            => 'fuse_reg_handle_conexsys_export',
        'permission_callback' => 'fuse_reg_conexsys_auth',
    ]);
}
add_action('rest_api_init', 'fuse_reg_register_routes');

/**
 * Validate the X-Fuse-API-Key header against the stored option.
 */
function fuse_reg_conexsys_auth(WP_REST_Request $request) {
    $stored_key = get_option('fuse_api_key', '');
    if (empty($stored_key)) {
        // No key configured — fall back to requiring WP admin capability
        return current_user_can('manage_options');
    }
    $provided = $request->get_header('X-Fuse-API-Key') ?: ($request->get_param('api_key') ?: '');
    return hash_equals($stored_key, $provided);
}

/**
 * Return a flat list of attendees (one row per person, guests included)
 * formatted for Conexsys badge printing.
 */
function fuse_reg_handle_conexsys_export(WP_REST_Request $request) {
    $event_id = get_option('fuse_event_id', '');

    $regs = Fuse_Supabase_API::request(
        'fuse_registrations?fuse_event_id=eq.' . $event_id .
        '&select=*,fuse_registration_guests(*)&order=created_at.desc'
    );

    if (isset($regs['error'])) {
        return new WP_REST_Response(['error' => $regs['error']], 500);
    }

    $rows = [];

    foreach ($regs as $reg) {
        // Primary registrant
        $rows[] = [
            'badge_type'        => 'registrant',
            'first_name'        => $reg['first_name'] ?? '',
            'last_name'         => $reg['last_name'] ?? '',
            'preferred_name'    => $reg['preferred_name'] ?? '',
            'email'             => $reg['email'] ?? '',
            'phone'             => $reg['phone'] ?? '',
            'company'           => $reg['company'] ?? '',
            'ticket_type'       => $reg['ticket_type'] ?? '',
            'tier'              => $reg['tier'] ?? '',
            'purchase_type'     => $reg['purchase_type'] ?? '',
            'has_hall_of_aime'  => !empty($reg['has_hall_of_aime']),
            'has_wmn_at_fuse'   => !empty($reg['has_wmn_at_fuse']),
            'has_vip_luncheon'  => !empty($reg['has_vip_luncheon']),
            'has_vetted_va'     => !empty($reg['has_vetted_va']),
            'guest_of'          => '',
            'registration_id'   => $reg['id'] ?? '',
            'created_at'        => $reg['created_at'] ?? '',
        ];

        // Guest rows
        $guests = $reg['fuse_registration_guests'] ?? [];
        foreach ($guests as $guest) {
            if (empty($guest['full_name'])) continue;
            $name_parts   = explode(' ', trim($guest['full_name']), 2);
            $is_vip_guest = ($guest['ticket_type'] ?? '') === 'vip_guest';
            $guest_hoa    = $is_vip_guest || !empty($guest['has_hall_of_aime']);
            $guest_wmn    = !empty($guest['has_wmn_at_fuse']);

            // badge_type reflects HOA attendance for non-VIP guests too
            if ($is_vip_guest) {
                $badge_type = 'guest_hoa';
            } elseif ($guest_hoa) {
                $badge_type = 'guest_hoa';
            } else {
                $badge_type = 'guest';
            }

            $rows[] = [
                'badge_type'        => $badge_type,
                'first_name'        => $name_parts[0] ?? '',
                'last_name'         => $name_parts[1] ?? '',
                'preferred_name'    => '',
                'email'             => $guest['email'] ?? '',
                'phone'             => '',
                'company'           => $reg['company'] ?? '',
                'ticket_type'       => $guest['ticket_type'] ?? 'guest',
                'tier'              => $reg['tier'] ?? '',
                'purchase_type'     => $reg['purchase_type'] ?? '',
                'has_hall_of_aime'  => $guest_hoa,
                'has_wmn_at_fuse'   => $guest_wmn,
                'has_vip_luncheon'  => !empty($guest['has_vip_luncheon']),
                'has_vetted_va'     => !empty($guest['has_vetted_va']),
                'guest_of'          => trim(($reg['first_name'] ?? '') . ' ' . ($reg['last_name'] ?? '')),
                'registration_id'   => $reg['id'] ?? '',
                'created_at'        => $reg['created_at'] ?? '',
            ];
        }
    }

    return new WP_REST_Response([
        'success' => true,
        'count'   => count($rows),
        'data'    => $rows,
    ], 200);
}

/** Legacy alias kept for backwards compatibility */
function fuse_reg_register_webhook_route() {} // no-op — route registered in fuse_reg_register_routes()

function fuse_reg_handle_stripe_webhook(WP_REST_Request $request) {
    $payload    = $request->get_body();
    $sig_header = $request->get_header('stripe-signature');
    $secret     = get_option('fuse_stripe_webhook_secret', '');

    error_log('[Fuse webhook] received — sig_header=' . (empty($sig_header) ? 'NONE' : 'present') . ' secret_configured=' . (!empty($secret) ? 'yes' : 'no'));

    // Verify webhook signature if a secret is configured
    if (!empty($secret) && !empty($sig_header)) {
        $verified = false;
        // Stripe uses HMAC-SHA256; parse timestamp + signatures from header
        $parts = [];
        foreach (explode(',', $sig_header) as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) === 2) $parts[$kv[0]] = $kv[1];
        }
        $timestamp = $parts['t'] ?? '';
        $v1        = $parts['v1'] ?? '';
        if ($timestamp && $v1) {
            $signed_payload = $timestamp . '.' . $payload;
            $expected       = hash_hmac('sha256', $signed_payload, $secret);
            $verified       = hash_equals($expected, $v1);
        }
        if (!$verified) {
            error_log('[Fuse webhook] signature verification FAILED — check that the webhook secret in Settings matches the Stripe dashboard signing secret.');
            return new WP_REST_Response(['error' => 'Invalid signature'], 400);
        }
        error_log('[Fuse webhook] signature verified OK');
    } else {
        error_log('[Fuse webhook] signature check SKIPPED — ' . (empty($secret) ? 'no webhook secret configured in Settings' : 'no signature header in request'));
    }

    $event = json_decode($payload, true);

    if (!$event || !isset($event['type'])) {
        error_log('[Fuse webhook] invalid payload — could not decode JSON');
        return new WP_REST_Response(['error' => 'Invalid payload'], 400);
    }

    error_log('[Fuse webhook] event type: ' . $event['type'] . ' id: ' . ($event['id'] ?? 'n/a'));

    // ── invoice.paid — flip manually-invoiced registrations from pending → purchased ──
    if ($event['type'] === 'invoice.paid') {
        $invoice    = $event['data']['object'];
        $invoice_id = $invoice['id'] ?? '';

        error_log('[Fuse webhook] invoice.paid — invoice_id: ' . $invoice_id . ' customer_email: ' . ($invoice['customer_email'] ?? 'n/a'));

        if (!empty($invoice_id)) {
            // Find the registration by stripe_invoice_id
            $existing = Fuse_Supabase_API::request(
                'fuse_registrations?stripe_invoice_id=eq.' . urlencode($invoice_id) . '&select=id,email,first_name,last_name,tier,phone,company,has_hall_of_aime,has_wmn_at_fuse',
                'GET'
            );

            if (!empty($existing) && !isset($existing['error']) && count($existing) > 0) {
                $reg = $existing[0];
                error_log('[Fuse webhook] invoice.paid — matched registration id: ' . $reg['id'] . ' email: ' . ($reg['email'] ?? 'n/a') . ' — updating purchase_type → purchased');

                Fuse_Supabase_API::update_registration($reg['id'], [
                    'purchase_type' => 'purchased',
                ]);

                // Trigger GHL confirmation workflow now that payment is confirmed
                $reg_data = [
                    'email'            => $reg['email'] ?? '',
                    'first_name'       => $reg['first_name'] ?? '',
                    'last_name'        => $reg['last_name'] ?? '',
                    'tier'             => $reg['tier'] ?? null,
                    'phone'            => $reg['phone'] ?? '',
                    'company'          => $reg['company'] ?? '',
                    'has_hall_of_aime' => !empty($reg['has_hall_of_aime']),
                    'has_wmn_at_fuse'  => !empty($reg['has_wmn_at_fuse']),
                ];
                Fuse_GHL_API::process_registration($reg_data, true);

                error_log('[Fuse webhook] invoice.paid — registration updated and GHL triggered OK');
                return new WP_REST_Response(['success' => true, 'updated' => $reg['id']], 200);
            } else {
                error_log('[Fuse webhook] invoice.paid — NO registration found with stripe_invoice_id=' . $invoice_id . ' (may be a non-Fuse invoice — safe to ignore)');
            }
        }

        return new WP_REST_Response(['received' => true], 200);
    }

    // checkout.session.completed is now handled exclusively by the success-page shortcode
    // via the Stripe API (API-based approach). The webhook no longer processes it so there
    // is no race condition between webhook and success page, and no transient/cache dependency.
    // If you ever want to re-enable webhook processing, uncomment the block below.
    //
    // if ($event['type'] === 'checkout.session.completed') {
    //     $session = $event['data']['object'];
    //     $result  = fuse_reg_process_paid_registration($session);
    //     return new WP_REST_Response(['success' => true], 200);
    // }

    // Acknowledge all other event types so Stripe doesn't retry them
    return new WP_REST_Response(['received' => true], 200);
}

// ============================================================
// GHL (GoHighLevel) API INTEGRATION
// ============================================================
class Fuse_GHL_API {

    // GHL API v2 base URL (current standard — v1 is deprecated)
    const BASE_URL = 'https://services.leadconnectorhq.com/';

    private static function get_api_key() {
        return get_option('fuse_ghl_api_key', '');
    }

    private static function get_location_id() {
        return get_option('fuse_ghl_location_id', '');
    }

    private static function request($endpoint, $method = 'GET', $body = null) {
        $api_key     = self::get_api_key();
        $location_id = self::get_location_id();

        if (empty($api_key)) {
            self::store_last_error('GHL API key is not configured in Settings.');
            return ['error' => 'GHL API key not configured'];
        }
        if (empty($location_id)) {
            self::store_last_error('GHL Location ID is not configured in Settings.');
            return ['error' => 'GHL Location ID not configured'];
        }

        $url  = self::BASE_URL . ltrim($endpoint, '/');
        $args = [
            'method'  => $method,
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
                'Version'       => '2021-07-28',
            ],
        ];

        if ($body !== null) {
            $args['body'] = json_encode($body);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $msg = 'HTTP error: ' . $response->get_error_message();
            self::store_last_error($msg);
            error_log('Fuse GHL: ' . $msg);
            return ['error' => $msg];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);
        $data = json_decode($body_raw, true);

        if ($code >= 400) {
            $detail = $data['message'] ?? $data['msg'] ?? $body_raw;
            $msg    = "GHL API returned HTTP $code on $endpoint — $detail";
            self::store_last_error($msg);
            error_log('Fuse GHL: ' . $msg);
            return ['error' => $msg, 'code' => $code, 'body' => $data];
        }

        return $data ?: [];
    }

    /** Store the last GHL error as a transient so the settings page can show it. */
    private static function store_last_error($msg) {
        set_transient('fuse_ghl_last_error', '[' . gmdate('Y-m-d H:i:s') . '] ' . $msg, DAY_IN_SECONDS);
    }

    /**
     * Look up a contact by email using GHL v2 search.
     * Returns contact array or null.
     */
    public static function lookup_contact($email) {
        $location_id = self::get_location_id();

        // GET /contacts with query= searches by email in GHL v2
        $result = self::request(
            'contacts/?locationId=' . urlencode($location_id) . '&query=' . urlencode($email)
        );
        if (!empty($result['contacts'])) {
            foreach ($result['contacts'] as $c) {
                if (strtolower($c['email'] ?? '') === strtolower($email)) {
                    return $c;
                }
            }
        }

        return null;
    }

    /**
     * Create a new contact. If GHL rejects due to duplicate-contact policy,
     * fall back to a lookup so we still get the contact ID.
     */
    public static function create_contact($data) {
        $data['locationId'] = self::get_location_id();
        $result = self::request('contacts/', 'POST', $data);

        if (!empty($result['contact']['id'])) {
            return $result['contact'];
        }

        // GHL rejected with "does not allow duplicated contacts" — contact already exists
        $err = $result['error'] ?? '';
        if (stripos($err, 'duplicat') !== false || ($result['code'] ?? 0) === 400) {
            return self::lookup_contact($data['email'] ?? '');
        }

        return null;
    }

    /**
     * Update an existing contact by ID.
     */
    public static function update_contact($contact_id, $data) {
        return self::request('contacts/' . $contact_id, 'PUT', $data);
    }

    /**
     * Add tags to a contact (GHL merges with existing — no duplicates on their side).
     */
    public static function add_tags($contact_id, array $tags) {
        return self::request('contacts/' . $contact_id . '/tags', 'POST', ['tags' => $tags]);
    }

    /**
     * Add the contact to a pipeline as an opportunity.
     */
    public static function create_opportunity($contact_id, $title) {
        $pipeline_id = get_option('fuse_ghl_pipeline_id', '');
        $stage_id    = get_option('fuse_ghl_pipeline_stage_id', '');
        $location_id = self::get_location_id();

        if (empty($pipeline_id) || empty($stage_id)) {
            error_log('Fuse GHL: Pipeline ID or Stage ID not configured — skipping opportunity creation.');
            return ['error' => 'Pipeline not configured'];
        }

        return self::request('opportunities/', 'POST', [
            'pipelineId'      => $pipeline_id,
            'pipelineStageId' => $stage_id,
            'title'           => $title,
            'contactId'       => $contact_id,
            'locationId'      => $location_id,
            'status'          => 'open',
        ]);
    }

    /**
     * Add the contact to a GHL workflow to trigger the confirmation message.
     */
    public static function trigger_workflow($contact_id) {
        $workflow_id = get_option('fuse_ghl_confirmation_workflow_id', '');
        if (empty($workflow_id)) return;

        self::request('contacts/' . $contact_id . '/workflow/' . $workflow_id, 'POST');
    }

    /**
     * Update the "Previous events attended" custom field, appending new values.
     */
    private static function update_events_field($contact_id, array $new_events) {
        $field_id = get_option('fuse_ghl_events_field_id', '');
        if (empty($field_id)) {
            error_log('FUSE GHL: update_events_field skipped — no field ID configured');
            return;
        }

        error_log('FUSE GHL: update_events_field called — contact_id=' . $contact_id . ' field_id=' . $field_id . ' new_events=' . implode(',', $new_events));

        // Fetch current contact to read existing field value
        $current = self::request('contacts/' . $contact_id);
        error_log('FUSE GHL: GET contact response: ' . json_encode($current));

        // Multi-select fields return value as an array in GHL v2
        $existing_list = [];
        $custom_fields = $current['contact']['customFields'] ?? $current['contact']['customField'] ?? [];
        foreach ($custom_fields as $field) {
            if (($field['id'] ?? '') === $field_id || ($field['key'] ?? '') === $field_id) {
                $raw = $field['value'] ?? $field['fieldValue'] ?? [];
                // GHL returns multi-select values as an array; fall back to splitting a string
                if (is_array($raw)) {
                    $existing_list = $raw;
                } elseif (!empty($raw)) {
                    $existing_list = array_map('trim', explode(',', $raw));
                }
                error_log('FUSE GHL: found existing field value: ' . json_encode($existing_list));
                break;
            }
        }

        // Merge new events without duplicates
        foreach ($new_events as $event) {
            if (!in_array($event, $existing_list, true)) {
                $existing_list[] = $event;
            }
        }

        // Multi-select fields require an array value, not a comma-separated string
        $is_key_string      = strpos($field_id, '.') !== false;
        $custom_field_entry = $is_key_string
            ? ['key' => $field_id, 'field_value' => array_values($existing_list)]
            : ['id'  => $field_id, 'field_value' => array_values($existing_list)];

        $payload = ['customFields' => [$custom_field_entry]];
        error_log('FUSE GHL: PUT contact payload: ' . json_encode($payload));

        $update_result = self::update_contact($contact_id, $payload);
        error_log('FUSE GHL: PUT contact response: ' . json_encode($update_result));

        // Surface any error in the admin GHL settings page
        if (!empty($update_result['error']) || !empty($update_result['message'])) {
            $err_msg = $update_result['error'] ?? $update_result['message'] ?? 'Unknown error';
            set_transient('fuse_ghl_last_error', 'Events field update: ' . $err_msg, HOUR_IN_SECONDS);
        }
    }

    /**
     * Test the GHL connection — returns ['success' => true] or ['error' => '...'].
     */
    public static function test_connection() {
        $location_id = self::get_location_id();
        // A lightweight call: fetch the location info
        $result = self::request('locations/' . $location_id);
        if (isset($result['error'])) {
            return ['success' => false, 'message' => $result['error']];
        }
        $name = $result['location']['name'] ?? $result['name'] ?? 'Connected';
        return ['success' => true, 'message' => 'Connected — Location: ' . $name];
    }

    /**
     * Main entry point — call this after every successful registration.
     *
     * $reg_data keys: email, first_name, last_name, phone, company,
     *                 tier (empty = non-member), has_hall_of_aime, has_wmn_at_fuse
     */
    public static function process_registration($reg_data, $send_confirmation = true) {
        if (empty(self::get_api_key()) || empty(self::get_location_id())) return;

        $email      = sanitize_email($reg_data['email'] ?? '');
        $first_name = $reg_data['first_name'] ?? '';
        $last_name  = $reg_data['last_name'] ?? '';
        $phone      = $reg_data['phone'] ?? '';
        $company    = $reg_data['company'] ?? '';
        $is_member  = !empty($reg_data['tier']);
        $has_hoa    = !empty($reg_data['has_hall_of_aime']);
        $has_wmn    = !empty($reg_data['has_wmn_at_fuse']);

        if (empty($email)) return;

        // ── 1. Upsert contact ──────────────────────────────────────────
        $contact = self::lookup_contact($email);

        if (!$contact) {
            $contact = self::create_contact([
                'firstName'   => $first_name,
                'lastName'    => $last_name,
                'email'       => $email,
                'phone'       => $phone,
                'companyName' => $company,
                'source'      => 'Fuse 2026 Registration',
            ]);
        } else {
            // Fill in any blank fields on the existing contact
            $updates = [];
            if ($first_name && empty($contact['firstName'])) $updates['firstName']   = $first_name;
            if ($last_name  && empty($contact['lastName']))  $updates['lastName']    = $last_name;
            if ($phone      && empty($contact['phone']))     $updates['phone']       = $phone;
            if ($company    && empty($contact['companyName'])) $updates['companyName'] = $company;
            if (!empty($updates)) self::update_contact($contact['id'], $updates);
        }

        $contact_id = $contact['id'] ?? null;
        if (!$contact_id) {
            // The real API error was already stored by request() — append context so it's visible
            $last = get_transient('fuse_ghl_last_error') ?: 'Unknown API error';
            self::store_last_error('Failed to get/create contact for ' . $email . '. Detail: ' . $last);
            return;
        }

        // ── 2. Tags ────────────────────────────────────────────────────
        $tags = ['fuse-2026'];
        if ($has_hoa) $tags[] = 'hoa-2026';
        if ($has_wmn) $tags[] = 'wmn-at-fuse-2026';

        self::add_tags($contact_id, $tags);

        // ── 3. Previous events attended custom field ───────────────────
        $events = ['Fuse 2026'];
        if ($has_hoa) $events[] = 'Hall of AIME 2026';
        if ($has_wmn) $events[] = 'WMN at Fuse 2026';

        self::update_events_field($contact_id, $events);

        // ── 4. Non-member → add to leads pipeline ─────────────────────
        if (!$is_member) {
            $opp_title = trim($first_name . ' ' . $last_name) . ' — Fuse 2026 Non-Member Registration';
            self::create_opportunity($contact_id, $opp_title);
        }

        // ── 5. Trigger confirmation workflow (new registrations only) ─
        if ($send_confirmation) {
            self::trigger_workflow($contact_id);
        }
    }
}

// ============================================================
// INIT EVERYTHING
// ============================================================
Fuse_Registration_Settings::init();
Fuse_Registration_Ajax::init();
Fuse_Registration_Shortcode::init();

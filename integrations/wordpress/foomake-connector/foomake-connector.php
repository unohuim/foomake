<?php
/**
 * Plugin Name: FooMake Connector
 * Plugin URI: https://foomake.com/
 * Description: Connects WooCommerce stores to FooMake for customer, product, and sales order import workflows.
 * Version: 0.2.1
 * Author: FooMake
 * Author URI: https://foomake.com/
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * Text Domain: foomake-connector
 */

if (! defined('ABSPATH')) {
    exit;
}

define('FOOMAKE_CONNECTOR_VERSION', '0.2.1');
define('FOOMAKE_CONNECTOR_FILE', __FILE__);
define('FOOMAKE_CONNECTOR_OPTION_BASE_URL', 'foomake_connector_base_url');
define('FOOMAKE_CONNECTOR_OPTION_PLUGIN_UUID', 'foomake_connector_plugin_uuid');
define('FOOMAKE_CONNECTOR_OPTION_ACCESS_TOKEN', 'foomake_connector_access_token');
define('FOOMAKE_CONNECTOR_OPTION_SITE_ACCESS_TOKEN', 'foomake_connector_site_access_token');
define('FOOMAKE_CONNECTOR_OPTION_TENANT_NAME', 'foomake_connector_tenant_name');
define('FOOMAKE_CONNECTOR_OPTION_SITE_URL', 'foomake_connector_site_url');
define('FOOMAKE_CONNECTOR_OPTION_STATUS', 'foomake_connector_status');
define('FOOMAKE_CONNECTOR_OPTION_LAST_SEEN_AT', 'foomake_connector_last_seen_at');

add_action('admin_menu', 'foomake_connector_register_menu');
add_action('admin_notices', 'foomake_connector_woocommerce_notice');
add_action('admin_post_foomake_connector_start_pairing', 'foomake_connector_start_pairing');
add_action('admin_post_foomake_connector_complete_pairing', 'foomake_connector_complete_pairing');
add_action('admin_post_foomake_connector_disconnect', 'foomake_connector_disconnect');
add_action('rest_api_init', 'foomake_connector_register_rest_routes');

/**
 * Register the FooMake connector admin page under WooCommerce when available.
 */
function foomake_connector_register_menu()
{
    $parent = class_exists('WooCommerce') ? 'woocommerce' : 'options-general.php';
    $capability = class_exists('WooCommerce') ? 'manage_woocommerce' : 'manage_options';

    add_submenu_page(
        $parent,
        __('FooMake Connector', 'foomake-connector'),
        __('FooMake', 'foomake-connector'),
        $capability,
        'foomake-connector',
        'foomake_connector_render_admin_page'
    );
}

/**
 * Render the connector admin page.
 */
function foomake_connector_render_admin_page()
{
    $capability = foomake_connector_manage_capability();

    if (! current_user_can($capability)) {
        wp_die(esc_html__('You do not have permission to manage the FooMake connector.', 'foomake-connector'));
    }

    $status_message = foomake_connector_refresh_status();
    $base_url = foomake_connector_base_url();
    $has_token = foomake_connector_access_token() !== '';
    $tenant_name = (string) get_option(FOOMAKE_CONNECTOR_OPTION_TENANT_NAME, '');
    $site_url = (string) get_option(FOOMAKE_CONNECTOR_OPTION_SITE_URL, '');
    $status = (string) get_option(FOOMAKE_CONNECTOR_OPTION_STATUS, $has_token ? 'connected' : 'disconnected');
    $last_seen_at = (string) get_option(FOOMAKE_CONNECTOR_OPTION_LAST_SEEN_AT, '');
    $notice = foomake_connector_notice_message();
    $is_connected = $has_token && $status === 'connected';

    ?>
    <div class="wrap">
        <h1><?php esc_html_e('FooMake Connector', 'foomake-connector'); ?></h1>

        <?php if ($notice !== '') : ?>
            <div class="notice notice-info">
                <p><?php echo esc_html($notice); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($status_message !== '') : ?>
            <div class="notice notice-warning">
                <p><?php echo esc_html($status_message); ?></p>
            </div>
        <?php endif; ?>

        <h2><?php esc_html_e('Connection Status', 'foomake-connector'); ?></h2>
        <table class="widefat striped" style="max-width: 720px;">
            <tbody>
                <tr>
                    <th scope="row"><?php esc_html_e('Status', 'foomake-connector'); ?></th>
                    <td><?php echo esc_html(ucfirst($status)); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('FooMake Tenant', 'foomake-connector'); ?></th>
                    <td><?php echo esc_html($tenant_name !== '' ? $tenant_name : __('Not paired', 'foomake-connector')); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('WordPress Site', 'foomake-connector'); ?></th>
                    <td><?php echo esc_html($site_url !== '' ? $site_url : home_url('/')); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Last Seen', 'foomake-connector'); ?></th>
                    <td><?php echo esc_html($last_seen_at !== '' ? $last_seen_at : __('Not checked yet', 'foomake-connector')); ?></td>
                </tr>
            </tbody>
        </table>

        <h2><?php esc_html_e('Pair with FooMake', 'foomake-connector'); ?></h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width: 720px;">
            <?php wp_nonce_field('foomake_connector_start_pairing'); ?>
            <input type="hidden" name="action" value="foomake_connector_start_pairing">

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="foomake_base_url"><?php esc_html_e('FooMake URL', 'foomake-connector'); ?></label>
                        </th>
                        <td>
                            <input
                                id="foomake_base_url"
                                name="foomake_base_url"
                                type="url"
                                class="regular-text"
                                value="<?php echo esc_attr($base_url); ?>"
                                <?php disabled($is_connected); ?>
                                required
                            >
                            <p class="description">
                                <?php esc_html_e('Use https://fm.test for local FooMake development, or https://foomake.com for production.', 'foomake-connector'); ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php
            submit_button(
                $is_connected ? __('Connected to FooMake', 'foomake-connector') : __('Connect to FooMake', 'foomake-connector'),
                'primary',
                'submit',
                true,
                $is_connected ? ['disabled' => 'disabled'] : []
            );
            ?>
        </form>

        <?php if ($has_token) : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('foomake_connector_disconnect'); ?>
                <input type="hidden" name="action" value="foomake_connector_disconnect">
                <?php submit_button(__('Disconnect Locally', 'foomake-connector'), 'delete'); ?>
            </form>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Start pairing by creating a pending request in FooMake and redirecting to approval.
 */
function foomake_connector_start_pairing()
{
    if (! current_user_can(foomake_connector_manage_capability())) {
        wp_die(esc_html__('You do not have permission to manage the FooMake connector.', 'foomake-connector'));
    }

    check_admin_referer('foomake_connector_start_pairing');

    $base_url = isset($_POST['foomake_base_url'])
        ? esc_url_raw(wp_unslash($_POST['foomake_base_url']))
        : '';

    if ($base_url === '') {
        foomake_connector_redirect(['foomake_error' => 'missing_base_url']);
    }

    $base_url = untrailingslashit($base_url);
    update_option(FOOMAKE_CONNECTOR_OPTION_BASE_URL, $base_url, false);

    $response = wp_remote_post($base_url . '/api/wordpress-plugin/pairing/start', [
        'timeout' => 15,
        'sslverify' => foomake_connector_should_verify_ssl($base_url),
        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ],
        'body' => wp_json_encode([
            'plugin_uuid' => foomake_connector_plugin_uuid(),
            'site_url' => home_url('/'),
            'site_name' => get_bloginfo('name'),
            'callback_url' => admin_url('admin-post.php?action=foomake_connector_complete_pairing'),
        ]),
    ]);

    if (is_wp_error($response)) {
        foomake_connector_redirect(['foomake_error' => 'pairing_start_failed']);
    }

    $body = json_decode((string) wp_remote_retrieve_body($response), true);
    $pairing_url = is_array($body) ? (string) ($body['pairing_url'] ?? '') : '';

    if (wp_remote_retrieve_response_code($response) !== 201 || $pairing_url === '') {
        foomake_connector_redirect(['foomake_error' => 'pairing_start_rejected']);
    }

    wp_redirect($pairing_url);
    exit;
}

/**
 * Complete pairing after FooMake approval redirects back with a code.
 */
function foomake_connector_complete_pairing()
{
    if (! current_user_can(foomake_connector_manage_capability())) {
        wp_die(esc_html__('You do not have permission to manage the FooMake connector.', 'foomake-connector'));
    }

    $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';

    if ($code === '') {
        foomake_connector_redirect(['foomake_error' => 'missing_pairing_code']);
    }

    $base_url = foomake_connector_base_url();
    $response = wp_remote_post($base_url . '/api/wordpress-plugin/pairing/complete', [
        'timeout' => 15,
        'sslverify' => foomake_connector_should_verify_ssl($base_url),
        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ],
        'body' => wp_json_encode([
            'code' => $code,
            'plugin_uuid' => foomake_connector_plugin_uuid(),
        ]),
    ]);

    if (is_wp_error($response)) {
        foomake_connector_redirect(['foomake_error' => 'pairing_complete_failed']);
    }

    $body = json_decode((string) wp_remote_retrieve_body($response), true);
    $token = is_array($body) ? (string) ($body['access_token'] ?? '') : '';
    $connection = is_array($body) ? ($body['connection'] ?? []) : [];
    $site_access_token = is_array($connection) ? (string) ($connection['site_access_token'] ?? '') : '';

    if (wp_remote_retrieve_response_code($response) !== 200 || $token === '' || $site_access_token === '') {
        foomake_connector_redirect(['foomake_error' => 'pairing_complete_rejected']);
    }

    update_option(FOOMAKE_CONNECTOR_OPTION_ACCESS_TOKEN, $token, false);
    update_option(FOOMAKE_CONNECTOR_OPTION_SITE_ACCESS_TOKEN, $site_access_token, false);
    foomake_connector_store_connection_payload($connection);

    foomake_connector_redirect(['foomake_paired' => '1']);
}

/**
 * Clear this WordPress site's local FooMake token.
 */
function foomake_connector_disconnect()
{
    if (! current_user_can(foomake_connector_manage_capability())) {
        wp_die(esc_html__('You do not have permission to manage the FooMake connector.', 'foomake-connector'));
    }

    check_admin_referer('foomake_connector_disconnect');

    delete_option(FOOMAKE_CONNECTOR_OPTION_ACCESS_TOKEN);
    delete_option(FOOMAKE_CONNECTOR_OPTION_SITE_ACCESS_TOKEN);
    update_option(FOOMAKE_CONNECTOR_OPTION_STATUS, 'disconnected', false);
    delete_option(FOOMAKE_CONNECTOR_OPTION_TENANT_NAME);
    delete_option(FOOMAKE_CONNECTOR_OPTION_LAST_SEEN_AT);

    foomake_connector_redirect(['foomake_disconnected' => '1']);
}

/**
 * Refresh token status from FooMake when a token exists.
 */
function foomake_connector_refresh_status()
{
    $token = foomake_connector_access_token();

    if ($token === '') {
        return '';
    }

    $base_url = foomake_connector_base_url();
    $response = wp_remote_get($base_url . '/api/wordpress-plugin/status', [
        'timeout' => 10,
        'sslverify' => foomake_connector_should_verify_ssl($base_url),
        'headers' => [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ],
    ]);

    if (is_wp_error($response)) {
        return __('FooMake status could not be checked.', 'foomake-connector');
    }

    if (wp_remote_retrieve_response_code($response) === 401) {
        update_option(FOOMAKE_CONNECTOR_OPTION_STATUS, 'revoked', false);

        return __('FooMake rejected this plugin token. Pair again to reconnect.', 'foomake-connector');
    }

    $body = json_decode((string) wp_remote_retrieve_body($response), true);

    if (wp_remote_retrieve_response_code($response) !== 200 || ! is_array($body)) {
        return __('FooMake status could not be checked.', 'foomake-connector');
    }

    foomake_connector_store_connection_payload($body['connection'] ?? []);

    return '';
}

/**
 * Store safe connection details returned by FooMake.
 *
 * @param mixed $connection
 */
function foomake_connector_store_connection_payload($connection)
{
    if (! is_array($connection)) {
        return;
    }

    update_option(FOOMAKE_CONNECTOR_OPTION_STATUS, (string) ($connection['status'] ?? 'connected'), false);
    update_option(FOOMAKE_CONNECTOR_OPTION_TENANT_NAME, (string) ($connection['tenant_name'] ?? ''), false);
    update_option(FOOMAKE_CONNECTOR_OPTION_SITE_URL, (string) ($connection['site_url'] ?? home_url('/')), false);
    update_option(FOOMAKE_CONNECTOR_OPTION_LAST_SEEN_AT, (string) ($connection['last_seen_at'] ?? ''), false);

    if (isset($connection['site_access_token']) && (string) $connection['site_access_token'] !== '') {
        update_option(FOOMAKE_CONNECTOR_OPTION_SITE_ACCESS_TOKEN, (string) $connection['site_access_token'], false);
    }
}

/**
 * Register FooMake-facing REST routes.
 */
function foomake_connector_register_rest_routes()
{
    register_rest_route('foomake/v1', '/customers', [
        'methods' => 'GET',
        'callback' => 'foomake_connector_rest_customers',
        'permission_callback' => 'foomake_connector_rest_can_serve',
    ]);

    register_rest_route('foomake/v1', '/orders', [
        'methods' => 'GET',
        'callback' => 'foomake_connector_rest_orders',
        'permission_callback' => 'foomake_connector_rest_can_serve',
    ]);
}

/**
 * Authorize FooMake server-to-plugin REST requests.
 *
 * @param WP_REST_Request $request
 */
function foomake_connector_rest_can_serve($request)
{
    $expected = (string) get_option(FOOMAKE_CONNECTOR_OPTION_SITE_ACCESS_TOKEN, '');
    $provided = (string) $request->get_header('x_foomake_site_token');

    if ($provided === '') {
        $provided = (string) $request->get_header('x-foomake-site-token');
    }

    return $expected !== ''
        && $provided !== ''
        && hash_equals($expected, $provided)
        && (string) get_option(FOOMAKE_CONNECTOR_OPTION_STATUS, 'disconnected') === 'connected';
}

/**
 * Return normalized WooCommerce customer rows for FooMake import preview.
 */
function foomake_connector_rest_customers()
{
    if (! class_exists('WooCommerce') || ! function_exists('wc_get_customers')) {
        return new WP_Error(
            'foomake_woocommerce_unavailable',
            __('WooCommerce is not available.', 'foomake-connector'),
            ['status' => 503]
        );
    }

    $customers = wc_get_customers([
        'limit' => 100,
        'orderby' => 'ID',
        'order' => 'ASC',
    ]);

    $rows = [];

    foreach ($customers as $customer) {
        if (! is_object($customer) || ! method_exists($customer, 'get_id')) {
            continue;
        }

        $rows[] = foomake_connector_normalize_customer($customer);
    }

    return rest_ensure_response([
        'data' => $rows,
    ]);
}

/**
 * Normalize one WooCommerce customer object for FooMake import preview.
 *
 * @param object $customer
 * @return array<string, string>
 */
function foomake_connector_normalize_customer($customer)
{
    $first_name = method_exists($customer, 'get_first_name') ? (string) $customer->get_first_name() : '';
    $last_name = method_exists($customer, 'get_last_name') ? (string) $customer->get_last_name() : '';
    $name = trim($first_name . ' ' . $last_name);

    if ($name === '' && method_exists($customer, 'get_billing_company')) {
        $name = (string) $customer->get_billing_company();
    }

    if ($name === '' && method_exists($customer, 'get_username')) {
        $name = (string) $customer->get_username();
    }

    if ($name === '' && method_exists($customer, 'get_email')) {
        $name = (string) $customer->get_email();
    }

    if ($name === '') {
        $name = 'Woo Customer ' . (string) $customer->get_id();
    }

    return [
        'external_id' => (string) $customer->get_id(),
        'name' => $name,
        'email' => method_exists($customer, 'get_email') ? (string) $customer->get_email() : '',
        'phone' => method_exists($customer, 'get_billing_phone') ? (string) $customer->get_billing_phone() : '',
        'address_line_1' => method_exists($customer, 'get_billing_address_1') ? (string) $customer->get_billing_address_1() : '',
        'address_line_2' => method_exists($customer, 'get_billing_address_2') ? (string) $customer->get_billing_address_2() : '',
        'city' => method_exists($customer, 'get_billing_city') ? (string) $customer->get_billing_city() : '',
        'region' => method_exists($customer, 'get_billing_state') ? (string) $customer->get_billing_state() : '',
        'postal_code' => method_exists($customer, 'get_billing_postcode') ? (string) $customer->get_billing_postcode() : '',
        'country_code' => method_exists($customer, 'get_billing_country') ? (string) $customer->get_billing_country() : '',
    ];
}

/**
 * Return normalized WooCommerce order rows for FooMake import preview.
 */
function foomake_connector_rest_orders()
{
    if (! class_exists('WooCommerce') || ! function_exists('wc_get_orders')) {
        return new WP_Error(
            'foomake_woocommerce_unavailable',
            __('WooCommerce is not available.', 'foomake-connector'),
            ['status' => 503]
        );
    }

    $orders = wc_get_orders([
        'limit' => 100,
        'orderby' => 'ID',
        'order' => 'DESC',
    ]);

    $rows = [];

    foreach ($orders as $order) {
        if (! is_object($order) || ! method_exists($order, 'get_id')) {
            continue;
        }

        $normalized = foomake_connector_normalize_order($order);

        if ($normalized !== null) {
            $rows[] = $normalized;
        }
    }

    return rest_ensure_response([
        'data' => $rows,
    ]);
}

/**
 * Normalize one WooCommerce order object for FooMake import preview.
 *
 * @param object $order
 * @return array<string, mixed>|null
 */
function foomake_connector_normalize_order($order)
{
    $lines = [];

    foreach ($order->get_items('line_item') as $line) {
        if (! is_object($line) || ! method_exists($line, 'get_id')) {
            continue;
        }

        $lines[] = foomake_connector_normalize_order_line($line, (string) $order->get_currency());
    }

    if ($lines === []) {
        return null;
    }

    $first_name = method_exists($order, 'get_billing_first_name') ? (string) $order->get_billing_first_name() : '';
    $last_name = method_exists($order, 'get_billing_last_name') ? (string) $order->get_billing_last_name() : '';
    $name = trim($first_name . ' ' . $last_name);

    if ($name === '' && method_exists($order, 'get_billing_company')) {
        $name = (string) $order->get_billing_company();
    }

    if ($name === '') {
        $name = 'Woo Customer ' . (string) $order->get_customer_id();
    }

    $created_at = method_exists($order, 'get_date_created') ? $order->get_date_created() : null;
    $date = is_object($created_at) && method_exists($created_at, 'date')
        ? (string) $created_at->date('Y-m-d')
        : '';

    return [
        'external_id' => (string) $order->get_id(),
        'external_source' => 'woocommerce',
        'external_status' => method_exists($order, 'get_status') ? (string) $order->get_status() : '',
        'date' => $date,
        'customer' => [
            'external_id' => method_exists($order, 'get_customer_id') ? (string) $order->get_customer_id() : '',
            'name' => $name,
            'email' => method_exists($order, 'get_billing_email') ? (string) $order->get_billing_email() : '',
            'phone' => method_exists($order, 'get_billing_phone') ? (string) $order->get_billing_phone() : '',
            'address_line_1' => method_exists($order, 'get_billing_address_1') ? (string) $order->get_billing_address_1() : '',
            'address_line_2' => method_exists($order, 'get_billing_address_2') ? (string) $order->get_billing_address_2() : '',
            'city' => method_exists($order, 'get_billing_city') ? (string) $order->get_billing_city() : '',
            'region' => method_exists($order, 'get_billing_state') ? (string) $order->get_billing_state() : '',
            'postal_code' => method_exists($order, 'get_billing_postcode') ? (string) $order->get_billing_postcode() : '',
            'country_code' => method_exists($order, 'get_billing_country') ? (string) $order->get_billing_country() : '',
        ],
        'lines' => $lines,
    ];
}

/**
 * Normalize one WooCommerce order line for FooMake import preview.
 *
 * @param object $line
 * @return array<string, mixed>
 */
function foomake_connector_normalize_order_line($line, string $currency_code)
{
    $product = method_exists($line, 'get_product') ? $line->get_product() : null;
    $sku = is_object($product) && method_exists($product, 'get_sku') ? (string) $product->get_sku() : '';
    $variation_id = method_exists($line, 'get_variation_id') ? (string) $line->get_variation_id() : '';
    $product_id = method_exists($line, 'get_product_id') ? (string) $line->get_product_id() : '';
    $product_external_id = $sku !== '' ? $sku : ($variation_id !== '' ? $variation_id : $product_id);

    return [
        'external_id' => (string) $line->get_id(),
        'product_external_id' => $product_external_id,
        'name' => method_exists($line, 'get_name') ? (string) $line->get_name() : '',
        'quantity' => foomake_connector_normalize_quantity(method_exists($line, 'get_quantity') ? $line->get_quantity() : 0),
        'unit_price_cents' => foomake_connector_order_line_unit_price_cents($line),
        'currency_code' => strtoupper($currency_code !== '' ? $currency_code : 'USD'),
    ];
}

/**
 * Normalize a WooCommerce quantity to FooMake's scale-six string.
 *
 * @param mixed $quantity
 */
function foomake_connector_normalize_quantity($quantity)
{
    return number_format((float) $quantity, 6, '.', '');
}

/**
 * Resolve the order-line unit price in cents from WooCommerce's line subtotal.
 *
 * @param object $line
 */
function foomake_connector_order_line_unit_price_cents($line)
{
    $quantity_units = foomake_connector_decimal_to_scaled_int(
        method_exists($line, 'get_quantity') ? $line->get_quantity() : 0,
        6
    );
    $subtotal_cents = foomake_connector_decimal_to_scaled_int(
        method_exists($line, 'get_subtotal') ? $line->get_subtotal() : 0,
        2
    );

    if ($quantity_units <= 0) {
        return 0;
    }

    return intdiv(($subtotal_cents * 1000000) + intdiv($quantity_units, 2), $quantity_units);
}

/**
 * Convert a decimal-like value into an integer at the requested scale.
 *
 * @param mixed $value
 */
function foomake_connector_decimal_to_scaled_int($value, int $scale)
{
    $normalized = trim((string) $value);

    if ($normalized === '') {
        return 0;
    }

    $negative = substr($normalized, 0, 1) === '-';

    if ($negative) {
        $normalized = substr($normalized, 1);
    }

    $parts = explode('.', $normalized, 2);
    $whole = preg_replace('/\D/', '', $parts[0] ?? '') ?: '0';
    $fraction = preg_replace('/\D/', '', $parts[1] ?? '') ?: '';
    $fraction = str_pad(substr($fraction, 0, $scale), $scale, '0');
    $scaled = ((int) $whole * (10 ** $scale)) + (int) $fraction;

    return $negative ? $scaled * -1 : $scaled;
}

/**
 * Get the current FooMake base URL.
 */
function foomake_connector_base_url()
{
    return untrailingslashit((string) get_option(FOOMAKE_CONNECTOR_OPTION_BASE_URL, 'https://foomake.com'));
}

/**
 * Get or create this plugin install's UUID.
 */
function foomake_connector_plugin_uuid()
{
    $uuid = (string) get_option(FOOMAKE_CONNECTOR_OPTION_PLUGIN_UUID, '');

    if ($uuid !== '') {
        return $uuid;
    }

    $uuid = wp_generate_uuid4();
    update_option(FOOMAKE_CONNECTOR_OPTION_PLUGIN_UUID, $uuid, false);

    return $uuid;
}

/**
 * Get the stored FooMake access token.
 */
function foomake_connector_access_token()
{
    return (string) get_option(FOOMAKE_CONNECTOR_OPTION_ACCESS_TOKEN, '');
}

/**
 * Return the capability required for the current install state.
 */
function foomake_connector_manage_capability()
{
    return class_exists('WooCommerce') ? 'manage_woocommerce' : 'manage_options';
}

/**
 * Redirect to the plugin admin page with optional query arguments.
 *
 * @param array<string, string> $query
 */
function foomake_connector_redirect(array $query = [])
{
    wp_safe_redirect(add_query_arg($query, foomake_connector_admin_url()));
    exit;
}

/**
 * Return the plugin admin page URL for either WooCommerce or Settings placement.
 */
function foomake_connector_admin_url()
{
    $path = class_exists('WooCommerce') ? 'admin.php' : 'options-general.php';

    return admin_url($path . '?page=foomake-connector');
}

/**
 * Determine whether SSL verification should be used for the FooMake base URL.
 */
function foomake_connector_should_verify_ssl($base_url)
{
    $host = parse_url($base_url, PHP_URL_HOST);

    if (! is_string($host)) {
        return true;
    }

    $host = strtolower($host);

    return ! in_array($host, ['localhost', '127.0.0.1'], true)
        && substr($host, -5) !== '.test';
}

/**
 * Resolve a short admin notice from query arguments.
 */
function foomake_connector_notice_message()
{
    if (isset($_GET['foomake_paired'])) {
        return __('FooMake pairing completed.', 'foomake-connector');
    }

    if (isset($_GET['foomake_disconnected'])) {
        return __('FooMake token removed from this WordPress site.', 'foomake-connector');
    }

    if (! isset($_GET['foomake_error'])) {
        return '';
    }

    $error = sanitize_text_field(wp_unslash($_GET['foomake_error']));
    $messages = [
        'missing_base_url' => __('Enter a FooMake URL before pairing.', 'foomake-connector'),
        'pairing_start_failed' => __('FooMake could not be reached to start pairing.', 'foomake-connector'),
        'pairing_start_rejected' => __('FooMake rejected the pairing start request.', 'foomake-connector'),
        'missing_pairing_code' => __('FooMake did not return a pairing code.', 'foomake-connector'),
        'pairing_complete_failed' => __('FooMake could not be reached to complete pairing.', 'foomake-connector'),
        'pairing_complete_rejected' => __('FooMake rejected the approved pairing code.', 'foomake-connector'),
    ];

    return $messages[$error] ?? __('FooMake pairing could not be completed.', 'foomake-connector');
}

/**
 * Warn administrators when WooCommerce is not active.
 */
function foomake_connector_woocommerce_notice()
{
    if (class_exists('WooCommerce')) {
        return;
    }

    if (! current_user_can('activate_plugins')) {
        return;
    }

    ?>
    <div class="notice notice-warning">
        <p>
            <?php esc_html_e('FooMake Connector is installed, but WooCommerce is not active.', 'foomake-connector'); ?>
        </p>
    </div>
    <?php
}

<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Top-level "Podio Integration" admin menu with three sub-pages:
 *   1. Form Mappings  (admin.php?page=podio-integration)
 *   2. Authentication (admin.php?page=podio-integration-auth)
 *   3. Debug Logs     (admin.php?page=podio-integration-logs)
 *
 * POST actions are handled via admin-post.php so redirects work cleanly.
 * Notices survive redirects via a short-lived transient keyed per user.
 */
class EPOD_Admin_Page {

    const SLUG_MAPPINGS = 'podio-integration';
    const SLUG_AUTH     = 'podio-integration-auth';
    const SLUG_LOGS     = 'podio-integration-logs';

    const NONCE_MAPPINGS = 'epod_save_mappings';
    const NONCE_AUTH     = 'epod_auth_action';
    const NONCE_LOGS     = 'epod_clear_logs';
    const NONCE_TEST     = 'epod_test_connection';

    const NOTICE_KEY     = 'epod_admin_notice_';
    const OPT_MAPPINGS   = 'epod_form_mappings';

    // =========================================================================
    // INIT
    // =========================================================================

    static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_post_epod_save_mappings', [ __CLASS__, 'handle_save_mappings' ] );
        add_action( 'admin_post_epod_auth_action',   [ __CLASS__, 'handle_auth_action' ] );
        add_action( 'admin_post_epod_clear_logs',    [ __CLASS__, 'handle_clear_logs' ] );
        add_action( 'wp_ajax_epod_test_connection',  [ __CLASS__, 'ajax_test_connection' ] );
    }

    static function register_menu() {
        add_menu_page(
            'Podio Integration',
            'Podio Integration',
            'manage_options',
            self::SLUG_MAPPINGS,
            [ __CLASS__, 'render_mappings' ],
            'dashicons-rest-api',
            30
        );

        // First submenu shares the parent slug so there is no duplicate top-level entry
        add_submenu_page(
            self::SLUG_MAPPINGS,
            'Form Mappings — Podio Integration',
            'Form Mappings',
            'manage_options',
            self::SLUG_MAPPINGS,
            [ __CLASS__, 'render_mappings' ]
        );

        add_submenu_page(
            self::SLUG_MAPPINGS,
            'Authentication — Podio Integration',
            'Authentication',
            'manage_options',
            self::SLUG_AUTH,
            [ __CLASS__, 'render_auth' ]
        );

        add_submenu_page(
            self::SLUG_MAPPINGS,
            'Debug Logs — Podio Integration',
            'Debug Logs',
            'manage_options',
            self::SLUG_LOGS,
            [ __CLASS__, 'render_logs' ]
        );
    }

    // =========================================================================
    // POST ACTION HANDLERS
    // =========================================================================

    static function handle_save_mappings() {
        check_admin_referer( self::NONCE_MAPPINGS );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions.' );
        }

        $raw      = isset( $_POST[ self::OPT_MAPPINGS ] ) ? wp_unslash( $_POST[ self::OPT_MAPPINGS ] ) : [];
        $mappings = self::sanitize_mappings( $raw );
        update_option( self::OPT_MAPPINGS, $mappings );

        self::set_notice( 'success', count( $mappings ) . ' form mapping(s) saved successfully.' );
        wp_safe_redirect( self::page_url( self::SLUG_MAPPINGS ) );
        exit;
    }

    static function handle_auth_action() {
        check_admin_referer( self::NONCE_AUTH );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions.' );
        }

        $action = sanitize_key( $_POST['epod_action'] ?? '' );

        switch ( $action ) {
            case 'authenticate':
                $ok = EPOD_Auth::authenticate();
                self::set_notice( $ok ? 'success' : 'error',
                    $ok ? 'Authenticated with Podio successfully.' : EPOD_Auth::get_last_error()
                );
                break;

            case 'refresh':
                $ok = EPOD_Auth::refresh();
                self::set_notice( $ok ? 'success' : 'error',
                    $ok ? 'Access token refreshed successfully.' : EPOD_Auth::get_last_error()
                );
                break;

            case 'revoke':
                EPOD_Auth::revoke();
                self::set_notice( 'success', 'Token revoked and cleared.' );
                break;

            default:
                self::set_notice( 'error', 'Unknown action.' );
        }

        wp_safe_redirect( self::page_url( self::SLUG_AUTH ) );
        exit;
    }

    static function handle_clear_logs() {
        check_admin_referer( self::NONCE_LOGS );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions.' );
        }

        EPOD_Logger::clear();
        self::set_notice( 'success', 'Debug logs cleared.' );
        wp_safe_redirect( self::page_url( self::SLUG_LOGS ) );
        exit;
    }

    static function ajax_test_connection() {
        check_ajax_referer( self::NONCE_TEST, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Insufficient permissions.' ] );
        }

        $result = EPOD_API::request( '/user/', 'GET' );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        if ( $result['code'] === 200 ) {
            $name  = $result['body']['name'] ?? 'Unknown user';
            $mails = $result['body']['mail']  ?? [];
            $email = '';
            foreach ( $mails as $m ) {
                if ( ! empty( $m['primary'] ) ) {
                    $email = $m['value'] ?? '';
                    break;
                }
            }
            wp_send_json_success( [
                'message' => 'Connected as ' . $name . ( $email ? " ({$email})" : '' ) . '.',
            ] );
        }

        $err = $result['body']['error_description'] ?? $result['body']['error'] ?? "HTTP {$result['code']}";
        wp_send_json_error( [ 'message' => $err ] );
    }

    // =========================================================================
    // PAGE: FORM MAPPINGS
    // =========================================================================

    static function render_mappings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $mappings = get_option( self::OPT_MAPPINGS, [] );
        if ( ! is_array( $mappings ) ) {
            $mappings = [];
        }

        self::render_wrap_open( 'mappings' );

        $types = self::field_types();
        ?>

        <p class="description" style="margin-bottom:20px;">
            Add one block per Elementor form. Each form can target a different Podio app.<br>
            <strong>Elementor Field ID</strong> — the custom ID you set per field in Elementor (e.g. <code>name</code>, <code>email</code>). The <code>form-field-</code> prefix is stripped automatically.<br>
            <strong>Podio External ID</strong> — the field's External ID inside your Podio app (e.g. <code>title</code>, <code>contact-email</code>). Find it via the Podio app → wrench icon → Developer.
        </p>

        <?php if ( empty( $mappings ) ) : ?>
        <div class="epod-empty-state" id="epod-empty-state">
            <span class="dashicons dashicons-forms"></span>
            <p>No form mappings yet.</p>
            <p>Click <strong>+ Add Form Mapping</strong> below to get started.</p>
        </div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="epod_save_mappings">
            <?php wp_nonce_field( self::NONCE_MAPPINGS ); ?>

            <div id="epod-form-mappings">
                <?php foreach ( $mappings as $fi => $form ) :
                    $field_count = count( $form['field_mappings'] ?? [] );
                ?>
                <div class="epod-form-block"
                     data-form-index="<?php echo esc_attr( $fi ); ?>"
                     data-field-counter="<?php echo esc_attr( $field_count ); ?>">

                    <div class="epod-block-header">
                        <div class="epod-block-title">
                            <span class="dashicons dashicons-forms epod-block-icon"></span>
                            <strong class="epod-form-name-label"><?php echo esc_html( $form['form_name'] ?: 'Untitled Form' ); ?></strong>
                            <span class="epod-block-meta">App <?php echo esc_html( $form['podio_app_id'] ); ?> &nbsp;·&nbsp; <?php echo esc_html( $field_count ); ?> field(s)</span>
                        </div>
                        <div class="epod-block-actions">
                            <button type="button" class="button epod-toggle-block" aria-expanded="true">Collapse ▲</button>
                            <button type="button" class="button button-link-delete epod-remove-form">Remove</button>
                        </div>
                    </div>

                    <div class="epod-block-body">
                        <table class="form-table epod-meta-table">
                            <tr>
                                <th><label>Elementor Form Name</label></th>
                                <td>
                                    <input type="text"
                                           name="epod_form_mappings[<?php echo $fi; ?>][form_name]"
                                           value="<?php echo esc_attr( $form['form_name'] ); ?>"
                                           class="regular-text epod-form-name-input"
                                           placeholder="Exact name from Elementor form widget" />
                                    <p class="description">Must match exactly what is set under Elementor → Form widget → Content → Form Name.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label>Podio App ID</label></th>
                                <td>
                                    <input type="text"
                                           name="epod_form_mappings[<?php echo $fi; ?>][podio_app_id]"
                                           value="<?php echo esc_attr( $form['podio_app_id'] ); ?>"
                                           class="regular-text"
                                           placeholder="e.g. 12345678" />
                                    <p class="description">Found in Podio app → wrench icon → Developer → App ID.</p>
                                </td>
                            </tr>
                        </table>

                        <div class="epod-fields-section">
                            <div class="epod-fields-header">
                                <strong>Field Mappings</strong>
                                <button type="button" class="button button-small epod-add-field">+ Add Field</button>
                            </div>

                            <table class="wp-list-table widefat fixed striped epod-field-table">
                                <thead>
                                    <tr>
                                        <th style="width:22%;">Elementor Field ID</th>
                                        <th style="width:22%;">Podio External ID</th>
                                        <th>Field Type &amp; Options</th>
                                        <th style="width:42px;"></th>
                                    </tr>
                                </thead>
                                <tbody class="epod-fields-tbody">
                                    <?php foreach ( ( $form['field_mappings'] ?? [] ) as $fli => $fm ) :
                                        $type     = $fm['field_type'] ?? 'text';
                                        $cat_opts = $fm['category_options'] ?? [];
                                    ?>
                                    <tr class="epod-field-row"
                                        data-form-index="<?php echo esc_attr( $fi ); ?>"
                                        data-field-index="<?php echo esc_attr( $fli ); ?>"
                                        data-opt-counter="<?php echo esc_attr( count( $cat_opts ) ); ?>">

                                        <td>
                                            <input type="text"
                                                   name="epod_form_mappings[<?php echo $fi; ?>][field_mappings][<?php echo $fli; ?>][elementor_id]"
                                                   value="<?php echo esc_attr( $fm['elementor_id'] ); ?>"
                                                   placeholder="e.g. email" class="widefat" />
                                        </td>
                                        <td>
                                            <input type="text"
                                                   name="epod_form_mappings[<?php echo $fi; ?>][field_mappings][<?php echo $fli; ?>][podio_external_id]"
                                                   value="<?php echo esc_attr( $fm['podio_external_id'] ); ?>"
                                                   placeholder="e.g. contact-email" class="widefat" />
                                        </td>
                                        <td>
                                            <select name="epod_form_mappings[<?php echo $fi; ?>][field_mappings][<?php echo $fli; ?>][field_type]"
                                                    class="epod-type-select">
                                                <?php foreach ( $types as $key => $label ) : ?>
                                                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $type, $key ); ?>>
                                                    <?php echo esc_html( $label ); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>

                                            <div class="epod-cat-section"
                                                 style="display:<?php echo $type === 'category' ? 'block' : 'none'; ?>;">
                                                <p class="description" style="margin:6px 0 4px;">Map each submitted label to its Podio Option ID:</p>
                                                <table class="epod-cat-table">
                                                    <tbody class="epod-cat-tbody">
                                                        <?php foreach ( $cat_opts as $oi => $opt ) : ?>
                                                        <tr>
                                                            <td>
                                                                <input type="text"
                                                                       name="epod_form_mappings[<?php echo $fi; ?>][field_mappings][<?php echo $fli; ?>][category_options][<?php echo $oi; ?>][label]"
                                                                       value="<?php echo esc_attr( $opt['label'] ); ?>"
                                                                       placeholder="Submitted value" />
                                                            </td>
                                                            <td class="epod-arrow">→</td>
                                                            <td>
                                                                <input type="number"
                                                                       name="epod_form_mappings[<?php echo $fi; ?>][field_mappings][<?php echo $fli; ?>][category_options][<?php echo $oi; ?>][option_id]"
                                                                       value="<?php echo esc_attr( $opt['option_id'] ); ?>"
                                                                       placeholder="Podio ID"
                                                                       style="width:80px;" />
                                                            </td>
                                                            <td>
                                                                <button type="button" class="button button-small epod-remove-opt">✕</button>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                                <button type="button" class="button button-small epod-add-opt" style="margin-top:6px;">+ Add Option</button>
                                            </div>
                                        </td>
                                        <td>
                                            <button type="button" class="button button-small epod-remove-field" title="Remove this field">✕</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div><!-- .epod-fields-section -->
                    </div><!-- .epod-block-body -->
                </div><!-- .epod-form-block -->
                <?php endforeach; ?>
            </div><!-- #epod-form-mappings -->

            <div class="epod-form-footer">
                <button type="button" class="button button-secondary" id="epod-add-form">
                    <span class="dashicons dashicons-plus-alt2"></span> Add Form Mapping
                </button>
                <button type="submit" class="button button-primary epod-save-btn">Save Mappings</button>
            </div>
        </form>

        <?php self::render_wrap_close(); ?>
        <?php self::render_styles(); ?>
        <?php self::render_mappings_js( count( $mappings ), $types ); ?>
        <?php
    }

    // =========================================================================
    // PAGE: AUTHENTICATION
    // =========================================================================

    static function render_auth() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $status      = EPOD_Auth::get_status();
        $is_active   = in_array( $status['state'], [ 'active', 'expiring' ], true );
        $creds_url   = admin_url( 'options-general.php?page=podio-credentials' );
        $test_nonce  = wp_create_nonce( self::NONCE_TEST );

        self::render_wrap_open( 'auth' );
        ?>

        <!-- Status Card -->
        <div class="epod-status-card epod-status-<?php echo esc_attr( $status['state'] ); ?>">
            <div class="epod-status-icon">
                <?php if ( $status['state'] === 'active' ) : ?>
                    <span class="dashicons dashicons-yes-alt" style="color:#46b450;font-size:40px;width:40px;height:40px;"></span>
                <?php elseif ( $status['state'] === 'unauthenticated' ) : ?>
                    <span class="dashicons dashicons-dismiss" style="color:#dc3232;font-size:40px;width:40px;height:40px;"></span>
                <?php else : ?>
                    <span class="dashicons dashicons-warning" style="color:#f0b849;font-size:40px;width:40px;height:40px;"></span>
                <?php endif; ?>
            </div>
            <div class="epod-status-details">
                <div class="epod-status-label" style="color:<?php echo esc_attr( $status['color'] ); ?>;">
                    <?php echo esc_html( $status['label'] ); ?>
                </div>
                <?php if ( ! empty( $status['expires_in'] ) ) : ?>
                <p>Token expires in <strong><?php echo esc_html( human_time_diff( time(), time() + $status['expires_in'] ) ); ?></strong></p>
                <?php endif; ?>
                <?php if ( ! empty( $status['has_refresh'] ) ) : ?>
                <p>Refresh token: <span style="color:#46b450;">&#10003; Available</span></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="epod-auth-buttons">
            <?php if ( ! $is_active ) : ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
                <input type="hidden" name="action"      value="epod_auth_action">
                <input type="hidden" name="epod_action" value="authenticate">
                <?php wp_nonce_field( self::NONCE_AUTH ); ?>
                <button type="submit" class="button button-primary">
                    <span class="dashicons dashicons-admin-network"></span> Authenticate with Podio
                </button>
            </form>
            <?php else : ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline; margin-right:8px;">
                <input type="hidden" name="action"      value="epod_auth_action">
                <input type="hidden" name="epod_action" value="refresh">
                <?php wp_nonce_field( self::NONCE_AUTH ); ?>
                <button type="submit" class="button">
                    <span class="dashicons dashicons-update"></span> Refresh Token
                </button>
            </form>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
                <input type="hidden" name="action"      value="epod_auth_action">
                <input type="hidden" name="epod_action" value="revoke">
                <?php wp_nonce_field( self::NONCE_AUTH ); ?>
                <button type="submit" class="button" onclick="return confirm('Revoke the current token?');">
                    <span class="dashicons dashicons-no"></span> Revoke Token
                </button>
            </form>
            <?php endif; ?>
        </div>

        <?php if ( $is_active ) : ?>
        <!-- Test Connection -->
        <div class="epod-card" style="margin-top:25px;">
            <h3 style="margin-top:0;">Test API Connection</h3>
            <p class="description">Verify the token works by making a live request to Podio.</p>
            <button type="button" class="button" id="epod-test-btn">
                <span class="dashicons dashicons-networking"></span> Test Connection
            </button>
            <div id="epod-test-result" hidden style="margin-top:12px;padding:10px 14px;border-radius:3px;"></div>
        </div>
        <script>
        document.getElementById('epod-test-btn').addEventListener('click', function () {
            var btn = this;
            var res = document.getElementById('epod-test-result');
            btn.disabled = true;
            btn.innerHTML = '<span class="dashicons dashicons-update epod-spin"></span> Testing...';
            res.hidden = true;

            fetch(ajaxurl, {
                method:  'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body:    new URLSearchParams({
                    action: 'epod_test_connection',
                    nonce:  '<?php echo esc_js( $test_nonce ); ?>'
                })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                res.hidden = false;
                if (data.success) {
                    res.style.cssText = 'background:#edfaee;border:1px solid #46b450;color:#1a6128;';
                    res.textContent   = '✓ ' + data.data.message;
                } else {
                    res.style.cssText = 'background:#fde8e8;border:1px solid #dc3232;color:#8b1a1a;';
                    res.textContent   = '✗ ' + data.data.message;
                }
            })
            .catch(function (e) {
                res.hidden    = false;
                res.textContent = '✗ Request failed: ' + e.message;
                res.style.cssText = 'background:#fde8e8;border:1px solid #dc3232;color:#8b1a1a;';
            })
            .finally(function () {
                btn.disabled = false;
                btn.innerHTML = '<span class="dashicons dashicons-networking"></span> Test Connection';
            });
        });
        </script>
        <?php endif; ?>

        <!-- How it works -->
        <div class="epod-info-box" style="margin-top:25px;">
            <h3 style="margin-top:0;">How Authentication Works</h3>
            <ol style="margin-bottom:0;">
                <li>Enter your Podio API credentials under <a href="<?php echo esc_url( $creds_url ); ?>">Settings → Podio Integration</a>.</li>
                <li>Click <strong>Authenticate with Podio</strong> — the plugin exchanges your credentials for an OAuth2 access token.</li>
                <li>The token is stored in your WordPress database and used automatically on every form submission.</li>
                <li>Tokens expire after ~4 hours. If a refresh token is available, the plugin renews it automatically in the background when a form is submitted.</li>
                <li>You can also manually refresh or revoke the token at any time using the buttons above.</li>
            </ol>
        </div>

        <?php self::render_wrap_close(); ?>
        <?php self::render_styles(); ?>
        <?php
    }

    // =========================================================================
    // PAGE: DEBUG LOGS
    // =========================================================================

    static function render_logs() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $debug_mode = get_option( 'epod_debug_mode' );
        $all_logs   = get_option( 'epod_debug_log', [] );
        $total      = count( $all_logs );
        $recent     = array_slice( $all_logs, -100 );
        $today      = 0;
        $today_date = current_time( 'Y-m-d' );

        foreach ( $all_logs as $log ) {
            if ( strpos( $log, '[' . $today_date ) === 0 ) {
                $today++;
            }
        }

        self::render_wrap_open( 'logs' );
        ?>

        <?php if ( ! $debug_mode ) : ?>
        <div class="notice notice-warning inline" style="margin:0 0 20px;">
            <p>
                <strong>Debug mode is off.</strong>
                Enable it under <a href="<?php echo esc_url( admin_url( 'options-general.php?page=podio-credentials' ) ); ?>">Settings → Podio Integration</a> to start capturing logs.
            </p>
        </div>
        <?php endif; ?>

        <?php if ( $total > 0 ) : ?>

        <div class="epod-log-stats">
            <span class="epod-stat"><strong><?php echo number_format( $total ); ?></strong> total entries</span>
            <span class="epod-stat"><strong><?php echo number_format( $today ); ?></strong> today</span>
            <span class="epod-stat">Showing last <strong><?php echo count( $recent ); ?></strong></span>
        </div>

        <div class="epod-log-toolbar">
            <div class="epod-log-filters">
                <button type="button" class="button epod-filter active" data-filter="all">All</button>
                <button type="button" class="button epod-filter" data-filter="error">Errors</button>
                <button type="button" class="button epod-filter" data-filter="warning">Warnings</button>
                <button type="button" class="button epod-filter" data-filter="success">Success</button>
            </div>
            <div class="epod-log-tools">
                <button type="button" class="button" id="epod-log-copy">Copy</button>
                <button type="button" class="button" id="epod-log-wrap">Wrap</button>
                <button type="button" class="button" id="epod-log-download">Download</button>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
                    <input type="hidden" name="action" value="epod_clear_logs">
                    <?php wp_nonce_field( self::NONCE_LOGS ); ?>
                    <button type="submit" class="button button-link-delete"
                            onclick="return confirm('Clear all logs? This cannot be undone.');">
                        Clear All
                    </button>
                </form>
            </div>
        </div>

        <div id="epod-log-box">
            <?php foreach ( $recent as $log ) :
                $cls = 'log-default';
                if ( strpos( $log, 'ERROR:' ) !== false )      $cls = 'log-error';
                elseif ( strpos( $log, 'WARNING:' ) !== false ) $cls = 'log-warning';
                elseif ( strpos( $log, 'SUCCESS' ) !== false )  $cls = 'log-success';
                elseif ( strpos( $log, '===' )     !== false )  $cls = 'log-section';
            ?>
            <div class="epod-log-line <?php echo esc_attr( $cls ); ?>"><?php echo esc_html( $log ); ?></div>
            <?php endforeach; ?>
        </div>

        <script>
        (function () {
            var box = document.getElementById('epod-log-box');

            // Scroll to bottom on load
            if (box) box.scrollTop = box.scrollHeight;

            // Filter buttons
            document.querySelectorAll('.epod-filter').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    document.querySelectorAll('.epod-filter').forEach(function (b) { b.classList.remove('active'); });
                    this.classList.add('active');
                    var f   = this.dataset.filter;
                    var map = { error: 'log-error', warning: 'log-warning', success: 'log-success' };
                    document.querySelectorAll('.epod-log-line').forEach(function (line) {
                        line.style.display = (f === 'all' || line.classList.contains(map[f] || '')) ? '' : 'none';
                    });
                });
            });

            document.getElementById('epod-log-copy').addEventListener('click', function () {
                navigator.clipboard.writeText(box.innerText)
                    .then(function () { alert('Logs copied to clipboard.'); });
            });

            document.getElementById('epod-log-wrap').addEventListener('click', function () {
                box.classList.toggle('log-wrap');
            });

            document.getElementById('epod-log-download').addEventListener('click', function () {
                var blob = new Blob([box.innerText], { type: 'text/plain' });
                var a    = document.createElement('a');
                a.href     = URL.createObjectURL(blob);
                a.download = 'podio-logs-' + new Date().toISOString().split('T')[0] + '.txt';
                a.click();
                URL.revokeObjectURL(a.href);
            });
        }());
        </script>

        <?php else : ?>
        <div class="epod-empty-state">
            <span class="dashicons dashicons-clipboard"></span>
            <p>No logs yet.</p>
            <p><?php echo $debug_mode ? 'Submit an Elementor form to generate logs.' : 'Enable debug mode to start capturing logs.'; ?></p>
        </div>
        <?php endif; ?>

        <?php self::render_wrap_close(); ?>
        <?php self::render_styles(); ?>
        <style>
        #epod-log-box {
            background: #1d2327; color: #f0f0f0;
            padding: 15px; font-family: 'Menlo','Monaco','Consolas',monospace;
            font-size: 12px; line-height: 1.6; height: 500px;
            overflow: auto; white-space: nowrap; border-radius: 4px;
            margin-bottom: 10px;
        }
        #epod-log-box.log-wrap { white-space: pre-wrap; }
        .epod-log-line { margin-bottom: 1px; padding-left: 6px; border-left: 3px solid transparent; }
        .log-error   { color: #ff6b6b; border-left-color: #dc3232; background: rgba(220,50,50,.08); }
        .log-warning { color: #ffd166; border-left-color: #f0b849; background: rgba(240,184,73,.08); }
        .log-success { color: #88d498; border-left-color: #46b450; background: rgba(70,180,80,.08); }
        .log-section { color: #4ecdc4; border-left-color: #00a0d2; font-weight: 700; }
        .epod-log-stats   { display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
        .epod-stat        { background: #f6f7f7; border: 1px solid #c3c4c7; padding: 6px 14px; border-radius: 3px; font-size: 13px; }
        .epod-log-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 8px; }
        .epod-log-filters, .epod-log-tools { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
        .epod-filter.active { background: #2271b1; border-color: #2271b1; color: #fff; }
        </style>
        <?php
    }

    // =========================================================================
    // SHARED LAYOUT HELPERS
    // =========================================================================

    private static function render_wrap_open( $active_tab ) {
        $notice = self::get_notice();
        ?>
        <div class="wrap epod-wrap">
            <h1 class="wp-heading-inline">Podio Integration</h1>
            <a href="<?php echo esc_url( admin_url( 'options-general.php?page=podio-credentials' ) ); ?>"
               class="page-title-action">API Credentials</a>
            <hr class="wp-header-end">

            <nav class="nav-tab-wrapper" style="margin-bottom:0;">
                <?php
                $tabs = [
                    'mappings' => [ 'label' => 'Form Mappings',  'slug' => self::SLUG_MAPPINGS ],
                    'auth'     => [ 'label' => 'Authentication', 'slug' => self::SLUG_AUTH ],
                    'logs'     => [ 'label' => 'Debug Logs',     'slug' => self::SLUG_LOGS ],
                ];
                foreach ( $tabs as $key => $tab ) {
                    $class = 'nav-tab' . ( $active_tab === $key ? ' nav-tab-active' : '' );
                    printf(
                        '<a href="%s" class="%s">%s</a>',
                        esc_url( self::page_url( $tab['slug'] ) ),
                        esc_attr( $class ),
                        esc_html( $tab['label'] )
                    );
                }
                ?>
            </nav>

            <div class="epod-tab-content">
                <?php if ( $notice ) : ?>
                <div class="notice <?php echo $notice['type'] === 'success' ? 'notice-success' : 'notice-error'; ?> is-dismissible" style="margin:15px 0 0;">
                    <p><?php echo esc_html( $notice['message'] ); ?></p>
                </div>
                <?php endif; ?>
        <?php
    }

    private static function render_wrap_close() {
        echo '</div><!-- .epod-tab-content --></div><!-- .wrap -->';
    }

    // =========================================================================
    // STYLES
    // =========================================================================

    private static function render_styles() {
        static $printed = false;
        if ( $printed ) return;
        $printed = true;
        ?>
        <style>
        /* ---- Layout ---- */
        .epod-wrap            { max-width: 1100px; }
        .epod-tab-content     { background: #fff; border: 1px solid #c3c4c7; border-top: none; padding: 24px 28px; }
        .epod-empty-state     { text-align: center; padding: 60px 20px; color: #666; }
        .epod-empty-state .dashicons { font-size: 52px; width: 52px; height: 52px; color: #c3c4c7; display: block; margin: 0 auto 12px; }
        .epod-info-box        { background: #f0f6fc; border-left: 4px solid #2271b1; padding: 16px 20px; border-radius: 2px; }
        .epod-card            { border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; }

        /* ---- Form Mappings ---- */
        .epod-form-block        { border: 1px solid #c3c4c7; border-radius: 4px; margin-bottom: 20px; background: #fff; overflow: hidden; }
        .epod-block-header      { display: flex; justify-content: space-between; align-items: center; padding: 12px 18px; background: #f6f7f7; border-bottom: 1px solid #c3c4c7; gap: 12px; }
        .epod-block-title       { display: flex; align-items: center; gap: 8px; min-width: 0; }
        .epod-block-icon        { color: #646970; flex-shrink: 0; }
        .epod-form-name-label   { font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .epod-block-meta        { font-size: 12px; color: #646970; white-space: nowrap; }
        .epod-block-actions     { display: flex; gap: 6px; flex-shrink: 0; }
        .epod-block-body        { padding: 20px 22px; }
        .epod-meta-table        { margin-bottom: 0; }
        .epod-meta-table th     { width: 210px; }

        .epod-fields-section    { margin-top: 22px; }
        .epod-fields-header     { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .epod-field-table       { table-layout: fixed; }
        .epod-field-table input[type="text"] { width: 100%; box-sizing: border-box; }
        .epod-field-table select { max-width: 160px; }

        .epod-cat-section       { margin-top: 8px; padding: 10px 12px; border: 1px dashed #aaa; border-radius: 3px; background: #fafafa; }
        .epod-cat-table         { width: 100%; border-collapse: collapse; }
        .epod-cat-table td      { padding: 3px 4px; vertical-align: middle; }
        .epod-cat-table input[type="text"] { width: 155px; }
        .epod-arrow             { padding: 0 8px !important; color: #646970; font-weight: 700; }

        .epod-form-footer       { display: flex; justify-content: space-between; align-items: center; margin-top: 24px; padding-top: 20px; border-top: 1px solid #e2e4e7; }
        .epod-save-btn          { font-size: 14px !important; height: 34px; }
        #epod-add-form .dashicons { vertical-align: middle; margin-top: -2px; }

        /* ---- Authentication ---- */
        .epod-status-card     { display: flex; align-items: flex-start; gap: 16px; padding: 20px 24px; border: 1px solid #c3c4c7; border-radius: 4px; margin-bottom: 20px; }
        .epod-status-label    { font-size: 18px; font-weight: 600; margin-bottom: 6px; }
        .epod-status-details p { margin: 4px 0 0; color: #646970; }
        .epod-auth-buttons    { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 8px; }
        .epod-auth-buttons .button .dashicons { vertical-align: middle; margin-top: -2px; margin-right: 2px; }

        /* ---- Spinner for test btn ---- */
        @keyframes epod-spin { to { transform: rotate(360deg); } }
        .epod-spin { display: inline-block; animation: epod-spin .8s linear infinite; }
        </style>
        <?php
    }

    // =========================================================================
    // MAPPINGS JAVASCRIPT
    // =========================================================================

    private static function render_mappings_js( $form_count, $types ) {
        // Build the <option> HTML for the type select so JS can reuse it
        $type_options = '';
        foreach ( $types as $k => $v ) {
            $type_options .= '<option value="' . esc_attr( $k ) . '">' . esc_html( $v ) . '</option>';
        }
        ?>
        <script>
        (function () {
            'use strict';

            var formCounter = <?php echo (int) $form_count; ?>;
            var TYPE_OPTIONS = <?php echo wp_json_encode( $type_options ); ?>;

            /* ---- DOM builder helpers ---- */

            function buildCatRow(fi, fli, oi) {
                return '<tr>' +
                    '<td><input type="text" name="epod_form_mappings[' + fi + '][field_mappings][' + fli + '][category_options][' + oi + '][label]" placeholder="Submitted value" style="width:155px;" /></td>' +
                    '<td class="epod-arrow">→</td>' +
                    '<td><input type="number" name="epod_form_mappings[' + fi + '][field_mappings][' + fli + '][category_options][' + oi + '][option_id]" placeholder="Podio ID" style="width:80px;" /></td>' +
                    '<td><button type="button" class="button button-small epod-remove-opt">✕</button></td>' +
                    '</tr>';
            }

            function buildFieldRow(fi, fli) {
                return '<tr class="epod-field-row" data-form-index="' + fi + '" data-field-index="' + fli + '" data-opt-counter="0">' +
                    '<td><input type="text" name="epod_form_mappings[' + fi + '][field_mappings][' + fli + '][elementor_id]" placeholder="e.g. email" class="widefat" /></td>' +
                    '<td><input type="text" name="epod_form_mappings[' + fi + '][field_mappings][' + fli + '][podio_external_id]" placeholder="e.g. contact-email" class="widefat" /></td>' +
                    '<td>' +
                        '<select name="epod_form_mappings[' + fi + '][field_mappings][' + fli + '][field_type]" class="epod-type-select">' + TYPE_OPTIONS + '</select>' +
                        '<div class="epod-cat-section" style="display:none;">' +
                            '<p class="description" style="margin:6px 0 4px;">Map each submitted label to its Podio Option ID:</p>' +
                            '<table class="epod-cat-table"><tbody class="epod-cat-tbody"></tbody></table>' +
                            '<button type="button" class="button button-small epod-add-opt" style="margin-top:6px;">+ Add Option</button>' +
                        '</div>' +
                    '</td>' +
                    '<td><button type="button" class="button button-small epod-remove-field" title="Remove">✕</button></td>' +
                    '</tr>';
            }

            function buildFormBlock(fi) {
                return '<div class="epod-form-block" data-form-index="' + fi + '" data-field-counter="0">' +
                    '<div class="epod-block-header">' +
                        '<div class="epod-block-title">' +
                            '<span class="dashicons dashicons-forms epod-block-icon"></span>' +
                            '<strong class="epod-form-name-label">New Form Mapping</strong>' +
                        '</div>' +
                        '<div class="epod-block-actions">' +
                            '<button type="button" class="button epod-toggle-block" aria-expanded="true">Collapse ▲</button>' +
                            '<button type="button" class="button button-link-delete epod-remove-form">Remove</button>' +
                        '</div>' +
                    '</div>' +
                    '<div class="epod-block-body">' +
                        '<table class="form-table epod-meta-table">' +
                            '<tr><th><label>Elementor Form Name</label></th>' +
                            '<td><input type="text" name="epod_form_mappings[' + fi + '][form_name]" class="regular-text epod-form-name-input" placeholder="Exact name from Elementor form widget" />' +
                            '<p class="description">Must match exactly what is set under Elementor → Form widget → Content → Form Name.</p></td></tr>' +
                            '<tr><th><label>Podio App ID</label></th>' +
                            '<td><input type="text" name="epod_form_mappings[' + fi + '][podio_app_id]" class="regular-text" placeholder="e.g. 12345678" />' +
                            '<p class="description">Found in Podio app → wrench icon → Developer → App ID.</p></td></tr>' +
                        '</table>' +
                        '<div class="epod-fields-section">' +
                            '<div class="epod-fields-header">' +
                                '<strong>Field Mappings</strong>' +
                                '<button type="button" class="button button-small epod-add-field">+ Add Field</button>' +
                            '</div>' +
                            '<table class="wp-list-table widefat fixed striped epod-field-table">' +
                                '<thead><tr>' +
                                    '<th style="width:22%;">Elementor Field ID</th>' +
                                    '<th style="width:22%;">Podio External ID</th>' +
                                    '<th>Field Type &amp; Options</th>' +
                                    '<th style="width:42px;"></th>' +
                                '</tr></thead>' +
                                '<tbody class="epod-fields-tbody"></tbody>' +
                            '</table>' +
                        '</div>' +
                    '</div>' +
                    '</div>';
            }

            function appendRow(tbody, html) {
                var tmp = document.createElement('tbody');
                tmp.innerHTML = html;
                tbody.appendChild(tmp.firstElementChild);
            }

            /* ---- Event wiring ---- */

            var container = document.getElementById('epod-form-mappings');

            // Add new form block
            document.getElementById('epod-add-form').addEventListener('click', function () {
                var empty = document.getElementById('epod-empty-state');
                if (empty) empty.style.display = 'none';

                var tmp = document.createElement('div');
                tmp.innerHTML = buildFormBlock(formCounter);
                container.appendChild(tmp.firstElementChild);
                formCounter++;
            });

            // Delegated clicks
            container.addEventListener('click', function (e) {
                var t = e.target;

                if (t.classList.contains('epod-remove-form')) {
                    if (confirm('Remove this form mapping?')) {
                        t.closest('.epod-form-block').remove();
                    }
                    return;
                }

                if (t.classList.contains('epod-toggle-block')) {
                    var body      = t.closest('.epod-form-block').querySelector('.epod-block-body');
                    var collapsed = body.style.display === 'none';
                    body.style.display    = collapsed ? '' : 'none';
                    t.textContent         = collapsed ? 'Collapse ▲' : 'Expand ▼';
                    t.setAttribute('aria-expanded', String(collapsed));
                    return;
                }

                if (t.classList.contains('epod-add-field')) {
                    var block = t.closest('.epod-form-block');
                    var fi    = block.dataset.formIndex;
                    var fli   = parseInt(block.dataset.fieldCounter, 10);
                    block.dataset.fieldCounter = fli + 1;
                    appendRow(block.querySelector('.epod-fields-tbody'), buildFieldRow(fi, fli));
                    return;
                }

                if (t.classList.contains('epod-remove-field')) {
                    t.closest('tr.epod-field-row').remove();
                    return;
                }

                if (t.classList.contains('epod-add-opt')) {
                    var row = t.closest('tr.epod-field-row');
                    var fi  = row.dataset.formIndex;
                    var fli = row.dataset.fieldIndex;
                    var oi  = parseInt(row.dataset.optCounter || '0', 10);
                    row.dataset.optCounter = oi + 1;
                    appendRow(row.querySelector('.epod-cat-tbody'), buildCatRow(fi, fli, oi));
                    return;
                }

                if (t.classList.contains('epod-remove-opt')) {
                    t.closest('tr').remove();
                    return;
                }
            });

            // Show / hide category section on type change
            container.addEventListener('change', function (e) {
                if (e.target.classList.contains('epod-type-select')) {
                    var cat = e.target.closest('td').querySelector('.epod-cat-section');
                    if (cat) cat.style.display = (e.target.value === 'category') ? 'block' : 'none';
                }
            });

            // Live-update the form name label in the header
            container.addEventListener('input', function (e) {
                if (e.target.classList.contains('epod-form-name-input')) {
                    var lbl = e.target.closest('.epod-form-block').querySelector('.epod-form-name-label');
                    if (lbl) lbl.textContent = e.target.value || 'New Form Mapping';
                }
            });
        }());
        </script>
        <?php
    }

    // =========================================================================
    // SANITIZATION
    // =========================================================================

    static function sanitize_mappings( $input ) {
        if ( ! is_array( $input ) ) {
            return [];
        }

        $clean = [];

        foreach ( $input as $form ) {
            if ( empty( $form['form_name'] ) || empty( $form['podio_app_id'] ) ) {
                continue;
            }

            $clean_form = [
                'form_name'      => sanitize_text_field( $form['form_name'] ),
                'podio_app_id'   => sanitize_text_field( $form['podio_app_id'] ),
                'field_mappings' => [],
            ];

            if ( ! empty( $form['field_mappings'] ) && is_array( $form['field_mappings'] ) ) {
                foreach ( $form['field_mappings'] as $fm ) {
                    if ( empty( $fm['elementor_id'] ) || empty( $fm['podio_external_id'] ) ) {
                        continue;
                    }

                    $type     = sanitize_text_field( $fm['field_type'] ?? 'text' );
                    $clean_fm = [
                        'elementor_id'      => sanitize_text_field( $fm['elementor_id'] ),
                        'podio_external_id' => sanitize_text_field( $fm['podio_external_id'] ),
                        'field_type'        => $type,
                        'category_options'  => [],
                    ];

                    if ( $type === 'category' && ! empty( $fm['category_options'] ) && is_array( $fm['category_options'] ) ) {
                        foreach ( $fm['category_options'] as $opt ) {
                            if ( empty( $opt['label'] ) || ! isset( $opt['option_id'] ) ) {
                                continue;
                            }
                            $clean_fm['category_options'][] = [
                                'label'     => sanitize_text_field( $opt['label'] ),
                                'option_id' => absint( $opt['option_id'] ),
                            ];
                        }
                    }

                    $clean_form['field_mappings'][] = $clean_fm;
                }
            }

            $clean[] = $clean_form;
        }

        return $clean;
    }

    // =========================================================================
    // UTILITY
    // =========================================================================

    private static function field_types() {
        return [
            'text'     => 'Text',
            'email'    => 'Email',
            'phone'    => 'Phone',
            'number'   => 'Number',
            'category' => 'Category (dropdown / radio)',
            'date'     => 'Date',
        ];
    }

    private static function page_url( $slug ) {
        return admin_url( 'admin.php?page=' . $slug );
    }

    private static function set_notice( $type, $message ) {
        set_transient( self::NOTICE_KEY . get_current_user_id(), [
            'type'    => $type,
            'message' => $message,
        ], 60 );
    }

    private static function get_notice() {
        $key    = self::NOTICE_KEY . get_current_user_id();
        $notice = get_transient( $key );
        if ( $notice ) {
            delete_transient( $key );
        }
        return $notice ?: null;
    }
}

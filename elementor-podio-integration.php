<?php
/**
 * Plugin Name: Elementor → Podio Integration
 * Description: Sends Elementor form submissions to Podio.
 * Version: 1.3.1
 * Author: Towfique Elahe
 * Author URI: https://towfiqueelahe.com/
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * =========================================================
 * ADMIN SETTINGS PAGE
 * =========================================================
 */
add_action( 'admin_menu', 'epod_add_settings_page' );
add_action( 'admin_init', 'epod_register_settings' );

function epod_add_settings_page() {
    add_options_page(
        'Podio Settings',
        'Podio Integration',
        'manage_options',
        'podio-settings',
        'epod_render_settings_page'
    );
}

function epod_register_settings() {
    register_setting(
        'epod_settings_group',
        'epod_client_id',
        [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]
    );
    
    register_setting(
        'epod_settings_group',
        'epod_client_secret',
        [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]
    );
    
    register_setting(
        'epod_settings_group',
        'epod_app_id',
        [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]
    );
    
    register_setting(
        'epod_settings_group',
        'epod_username',
        [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]
    );
    
    register_setting(
        'epod_settings_group',
        'epod_password',
        [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]
    );
    
    register_setting(
        'epod_settings_group',
        'epod_form_name',
        [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]
    );
    
    register_setting(
        'epod_settings_group',
        'epod_debug_mode',
        [
            'type'              => 'boolean',
            'sanitize_callback' => 'sanitize_text_field',
        ]
    );
    
    register_setting(
        'epod_settings_group',
        'epod_debug_log',
        [
            'type'              => 'array',
            'sanitize_callback' => 'epod_sanitize_debug_log',
        ]
    );
    
    // Store OAuth tokens
    register_setting(
        'epod_settings_group',
        'epod_access_token',
        [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]
    );
    
    register_setting(
        'epod_settings_group',
        'epod_refresh_token',
        [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]
    );
    
    register_setting(
        'epod_settings_group',
        'epod_token_expires',
        [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
        ]
    );
}

function epod_sanitize_debug_log( $input ) {
    if ( ! is_array( $input ) ) {
        return [];
    }
    return array_map( 'sanitize_text_field', $input );
}

function epod_render_settings_page() {
    ?>
<div class="wrap">
    <h1>Podio Integration Settings</h1>

    <form method="post" action="options.php">
        <?php settings_fields( 'epod_settings_group' ); ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="epod_client_id">Podio Client ID</label>
                </th>
                <td>
                    <input type="text" id="epod_client_id" name="epod_client_id"
                        value="<?php echo esc_attr( get_option( 'epod_client_id' ) ); ?>" class="regular-text" />
                    <p class="description">Your Podio Client ID from the API settings.</p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="epod_client_secret">Podio Client Secret</label>
                </th>
                <td>
                    <input type="password" id="epod_client_secret" name="epod_client_secret"
                        value="<?php echo esc_attr( get_option( 'epod_client_secret' ) ); ?>" class="regular-text" />
                    <p class="description">Your Podio Client Secret from the API settings.</p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="epod_app_id">Podio App ID</label>
                </th>
                <td>
                    <input type="text" id="epod_app_id" name="epod_app_id"
                        value="<?php echo esc_attr( get_option( 'epod_app_id' ) ); ?>" class="regular-text" />
                    <p class="description">The ID of the Podio app where items should be created.</p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="epod_username">Podio Username (Email)</label>
                </th>
                <td>
                    <input type="email" id="epod_username" name="epod_username"
                        value="<?php echo esc_attr( get_option( 'epod_username' ) ); ?>" class="regular-text" />
                    <p class="description">Your Podio account email/username.</p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="epod_password">Podio Password</label>
                </th>
                <td>
                    <input type="password" id="epod_password" name="epod_password"
                        value="<?php echo esc_attr( get_option( 'epod_password' ) ); ?>" class="regular-text" />
                    <p class="description">Your Podio account password.</p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="epod_form_name">Target Form Name</label>
                </th>
                <td>
                    <input type="text" id="epod_form_name" name="epod_form_name"
                        value="<?php echo esc_attr( get_option( 'epod_form_name', 'Submit Deal' ) ); ?>"
                        class="regular-text" />
                    <p class="description">Enter the exact name of the Elementor form to process (default: "Submit
                        Deal").</p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="epod_debug_mode">Debug Mode</label>
                </th>
                <td>
                    <input type="checkbox" id="epod_debug_mode" name="epod_debug_mode" value="1"
                        <?php checked( get_option( 'epod_debug_mode' ), 1 ); ?> />
                    <p class="description">Enable debug logging to track API requests and responses.</p>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>

    <!-- Token Status Section -->
    <div class="notice notice-info" style="margin-top: 20px;">
        <h3>Authentication Status</h3>
        <?php
        $access_token = get_option( 'epod_access_token' );
        $refresh_token = get_option( 'epod_refresh_token' );
        $token_expires = get_option( 'epod_token_expires' );
        
        if ( empty( $access_token ) ) {
            echo '<p><strong>Status:</strong> <span style="color: #dc3232;">Not authenticated</span></p>';
            echo '<p>Please save your credentials above and then click the button below to authenticate.</p>';
        } else {
            $current_time = time();
            $expires_in = $token_expires - $current_time;
            $expires_human = human_time_diff( $current_time, $token_expires );
            
            if ( $expires_in > 0 ) {
                echo '<p><strong>Status:</strong> <span style="color: #46b450;">Authenticated</span></p>';
                echo '<p><strong>Token expires in:</strong> ' . $expires_human . '</p>';
            } else {
                echo '<p><strong>Status:</strong> <span style="color: #f0b849;">Token expired</span></p>';
                echo '<p>The access token has expired. Please re-authenticate.</p>';
            }
            
            if ( ! empty( $refresh_token ) ) {
                echo '<p><strong>Refresh token:</strong> Available</p>';
            }
        }
        ?>

        <form method="post" style="margin-top: 15px;">
            <?php wp_nonce_field( 'epod_auth_action', 'epod_auth_nonce' ); ?>
            <?php if ( empty( $access_token ) ) : ?>
            <input type="submit" name="epod_authenticate" class="button button-primary" value="Authenticate with Podio">
            <?php else : ?>
            <input type="submit" name="epod_refresh_token" class="button" value="Refresh Token">
            <input type="submit" name="epod_revoke_token" class="button" value="Revoke Token"
                onclick="return confirm('Are you sure you want to revoke the token?');">
            <?php endif; ?>
        </form>
    </div>

    <?php
    // Handle authentication actions
    if ( isset( $_POST['epod_authenticate'] ) || isset( $_POST['epod_refresh_token'] ) || isset( $_POST['epod_revoke_token'] ) ) {
        if ( wp_verify_nonce( $_POST['epod_auth_nonce'], 'epod_auth_action' ) ) {
            if ( isset( $_POST['epod_authenticate'] ) ) {
                epod_authenticate_with_podio();
            } elseif ( isset( $_POST['epod_refresh_token'] ) ) {
                epod_refresh_access_token();
            } elseif ( isset( $_POST['epod_revoke_token'] ) ) {
                epod_revoke_access_token();
            }
        }
    }
    ?>

    <?php if ( get_option( 'epod_debug_mode' ) ) : ?>
    <div class="notice notice-info" style="margin-top: 20px;">
        <h2 style="margin-top: 0;">Debug Logs</h2>

        <?php
        $debug_log = get_option( 'epod_debug_log', [] );
        if ( ! empty( $debug_log ) ) : 
            $total_logs = count( $debug_log );
            $recent_logs = array_slice( $debug_log, -50 ); // Show last 50 entries
            
            // Summary stats
            $today_count = 0;
            $current_date = date( 'Y-m-d' );
            foreach ( $debug_log as $log ) {
                if ( strpos( $log, '[' . $current_date ) === 0 ) {
                    $today_count++;
                }
            }
            ?>

        <div style="margin-bottom: 15px; padding: 10px; background: #f0f0f1; border-left: 4px solid #2271b1;">
            <strong>Log Summary:</strong>
            <?php echo number_format( $total_logs ); ?> total entries |
            <?php echo number_format( $today_count ); ?> today |
            Showing last <?php echo number_format( count( $recent_logs ) ); ?> entries
        </div>

        <div style="margin-bottom: 15px;">
            <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                <button type="button" class="button" onclick="copyLogs()">Copy Logs</button>
                <button type="button" class="button" onclick="toggleWordWrap()">Toggle Word Wrap</button>
                <button type="button" class="button" onclick="filterLogs('ERROR')">Show Errors Only</button>
                <button type="button" class="button" onclick="filterLogs('WARNING')">Show Warnings Only</button>
                <button type="button" class="button" onclick="filterLogs('ALL')">Show All</button>
            </div>

            <div style="position: relative;">
                <div style="position: absolute; top: 10px; right: 10px; z-index: 10;">
                    <span id="log-count"
                        style="background: #2271b1; color: white; padding: 3px 8px; border-radius: 3px; font-size: 12px;">
                        <?php echo count( $recent_logs ); ?> entries
                    </span>
                </div>

                <div id="log-container" style="
                        background: #1d2327;
                        color: #f0f0f0;
                        padding: 15px;
                        border-radius: 4px;
                        font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Consolas', monospace;
                        font-size: 12px;
                        line-height: 1.5;
                        height: 500px;
                        overflow-y: auto;
                        overflow-x: auto;
                        white-space: nowrap;
                        margin-bottom: 15px;
                        position: relative;
                    ">
                    <?php foreach ( $recent_logs as $index => $log ) : 
                            $log_class = '';
                            if ( strpos( $log, 'ERROR:' ) !== false ) {
                                $log_class = 'log-error';
                            } elseif ( strpos( $log, 'WARNING:' ) !== false ) {
                                $log_class = 'log-warning';
                            } elseif ( strpos( $log, '=== Elementor' ) !== false ) {
                                $log_class = 'log-section';
                            } elseif ( strpos( $log, 'API Request:' ) !== false ) {
                                $log_class = 'log-api-request';
                            } elseif ( strpos( $log, 'Response Code:' ) !== false ) {
                                $log_class = 'log-api-response';
                            }
                        ?>
                    <div class="log-entry <?php echo $log_class; ?>" data-index="<?php echo $index; ?>"
                        style="margin-bottom: 4px; border-left: 3px solid transparent; padding-left: 5px;">
                        <?php echo esc_html( $log ); ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
                <div>
                    <span style="display: inline-flex; align-items: center; margin-right: 15px;">
                        <span
                            style="display: inline-block; width: 12px; height: 12px; background: #dc3232; margin-right: 5px;"></span>
                        <span style="font-size: 12px;">Errors</span>
                    </span>
                    <span style="display: inline-flex; align-items: center; margin-right: 15px;">
                        <span
                            style="display: inline-block; width: 12px; height: 12px; background: #f0b849; margin-right: 5px;"></span>
                        <span style="font-size: 12px;">Warnings</span>
                    </span>
                    <span style="display: inline-flex; align-items: center; margin-right: 15px;">
                        <span
                            style="display: inline-block; width: 12px; height: 12px; background: #00a0d2; margin-right: 5px;"></span>
                        <span style="font-size: 12px;">Section Start</span>
                    </span>
                    <span style="display: inline-flex; align-items: center;">
                        <span
                            style="display: inline-block; width: 12px; height: 12px; background: #46b450; margin-right: 5px;"></span>
                        <span style="font-size: 12px;">API Calls</span>
                    </span>
                </div>

                <div>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="epod_clear_logs" value="1">
                        <?php wp_nonce_field( 'epod_clear_logs_action', 'epod_clear_logs_nonce' ); ?>
                        <input type="submit" class="button button-primary" value="Clear All Logs"
                            onclick="return confirm('Are you sure you want to clear all debug logs?');">
                    </form>
                    <button type="button" class="button" onclick="downloadLogs()" style="margin-left: 10px;">
                        Download Logs
                    </button>
                </div>
            </div>
        </div>

        <style>
        .log-error {
            border-left-color: #dc3232 !important;
            color: #ff6b6b;
            background: rgba(220, 50, 50, 0.1);
        }

        .log-warning {
            border-left-color: #f0b849 !important;
            color: #ffd166;
            background: rgba(240, 184, 73, 0.1);
        }

        .log-section {
            border-left-color: #00a0d2 !important;
            color: #4ecdc4;
            font-weight: bold;
            background: rgba(0, 160, 210, 0.1);
        }

        .log-api-request {
            border-left-color: #46b450 !important;
            color: #88d498;
            background: rgba(70, 180, 80, 0.1);
        }

        .log-api-response {
            border-left-color: #7c3aed !important;
            color: #c4b5fd;
            background: rgba(124, 58, 237, 0.1);
        }

        .log-entry {
            transition: all 0.2s ease;
        }

        .log-entry:hover {
            background: rgba(255, 255, 255, 0.05) !important;
        }
        </style>

        <script>
        function copyLogs() {
            const logContainer = document.getElementById('log-container');
            const text = logContainer.innerText;
            navigator.clipboard.writeText(text).then(() => {
                alert('Logs copied to clipboard!');
            });
        }

        function toggleWordWrap() {
            const logContainer = document.getElementById('log-container');
            logContainer.style.whiteSpace = logContainer.style.whiteSpace === 'nowrap' ? 'pre-wrap' : 'nowrap';
        }

        function filterLogs(type) {
            const entries = document.querySelectorAll('.log-entry');
            let visibleCount = 0;

            entries.forEach(entry => {
                let show = false;

                switch (type) {
                    case 'ERROR':
                        show = entry.classList.contains('log-error');
                        break;
                    case 'WARNING':
                        show = entry.classList.contains('log-warning');
                        break;
                    case 'ALL':
                    default:
                        show = true;
                        break;
                }

                entry.style.display = show ? 'block' : 'none';
                if (show) visibleCount++;
            });

            document.getElementById('log-count').textContent = visibleCount + ' entries';

            // Scroll to top after filtering
            document.getElementById('log-container').scrollTop = 0;
        }

        function downloadLogs() {
            const logContainer = document.getElementById('log-container');
            const text = logContainer.innerText;
            const blob = new Blob([text], {
                type: 'text/plain'
            });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'podio-debug-' + new Date().toISOString().split('T')[0] + '.log';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }

        // Auto-scroll to bottom on page load
        document.addEventListener('DOMContentLoaded', function() {
            const logContainer = document.getElementById('log-container');
            if (logContainer) {
                logContainer.scrollTop = logContainer.scrollHeight;
            }
        });
        </script>

        <?php else : ?>
        <div style="text-align: center; padding: 30px; background: #f0f0f1; border-radius: 4px;">
            <p style="font-size: 16px; color: #666; margin-bottom: 20px;">No debug logs yet.</p>
            <p style="color: #999;">Submit an Elementor form to see debug logs here.</p>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="notice notice-info">
        <h3>Field Mapping</h3>
        <p>Based on your Podio app fields, the following mappings are configured:</p>
        <ul>
            <li><strong>Name</strong> → "title" (text)</li>
            <li><strong>Phone</strong> → "phone" (phone)</li>
            <li><strong>Property Address</strong> → "property-address-or-city-state" (text)</li>
            <li><strong>Asking Price</strong> → "asking-price" (text)</li>
            <li><strong>How often do you do deals?</strong> → "how-often-do-you-do-deals" (category)</li>
            <li><strong>Email</strong> → "email" (email)</li>
            <li><strong>ARV</strong> → "arv-optional" (text)</li>
            <li><strong>Estimated Repairs</strong> → "estimated-repairs-optional-2" (category)</li>
            <li><strong>Deal Type</strong> → "deal-type-optional-2" (category)</li>
            <li><strong>Timeline to Close</strong> → "timeline-to-close-optional-2" (date)</li>
        </ul>
    </div>
</div>
<?php
    // Handle log clearing
    if ( isset( $_POST['epod_clear_logs'] ) && wp_verify_nonce( $_POST['epod_clear_logs_nonce'], 'epod_clear_logs_action' ) ) {
        update_option( 'epod_debug_log', [] );
        echo '<div class="notice notice-success is-dismissible"><p>Debug logs cleared successfully.</p></div>';
    }
}

/**
 * =========================================================
 * DEBUG LOGGING
 * =========================================================
 */
function epod_log( $message ) {
    // Always log to error_log for debugging
    error_log( 'Podio Integration: ' . $message );
    
    if ( get_option( 'epod_debug_mode' ) ) {
        $debug_log = get_option( 'epod_debug_log', [] );
        $debug_log[] = '[' . date( 'Y-m-d H:i:s' ) . '] ' . $message;
        // Keep only last 100 entries
        if ( count( $debug_log ) > 100 ) {
            $debug_log = array_slice( $debug_log, -100 );
        }
        update_option( 'epod_debug_log', $debug_log );
    }
}

/**
 * =========================================================
 * PODIO OAUTH2 AUTHENTICATION
 * =========================================================
 */

/**
 * Authenticate with Podio using password grant
 */
function epod_authenticate_with_podio() {
    $client_id = get_option( 'epod_client_id' );
    $client_secret = get_option( 'epod_client_secret' );
    $username = get_option( 'epod_username' );
    $password = get_option( 'epod_password' );
    
    if ( empty( $client_id ) || empty( $client_secret ) || empty( $username ) || empty( $password ) ) {
        epod_log( 'ERROR: Missing credentials for Podio authentication' );
        add_settings_error( 'epod_settings', 'epod_auth_error', 'Please fill in all Podio credentials (Client ID, Client Secret, Username, and Password) before authenticating.', 'error' );
        return false;
    }
    
    $url = 'https://podio.com/oauth/token';
    
    $args = [
        'headers' => [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ],
        'body' => http_build_query( [
            'grant_type'    => 'password',
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'username'      => $username,
            'password'      => $password,
        ] ),
        'timeout' => 30,
    ];
    
    epod_log( 'Authenticating with Podio OAuth2...' );
    
    $response = wp_remote_post( $url, $args );
    
    if ( is_wp_error( $response ) ) {
        epod_log( 'ERROR: Authentication failed: ' . $response->get_error_message() );
        add_settings_error( 'epod_settings', 'epod_auth_error', 'Authentication failed: ' . $response->get_error_message(), 'error' );
        return false;
    }
    
    $response_code = wp_remote_retrieve_response_code( $response );
    $response_body = wp_remote_retrieve_body( $response );
    $data = json_decode( $response_body, true );
    
    if ( $response_code === 200 && isset( $data['access_token'] ) ) {
        update_option( 'epod_access_token', $data['access_token'] );
        
        if ( isset( $data['refresh_token'] ) ) {
            update_option( 'epod_refresh_token', $data['refresh_token'] );
        }
        
        $expires_in = isset( $data['expires_in'] ) ? $data['expires_in'] : 3600; // Default to 1 hour
        update_option( 'epod_token_expires', time() + $expires_in );
        
        epod_log( 'SUCCESS: Authenticated with Podio. Token expires in ' . $expires_in . ' seconds' );
        add_settings_error( 'epod_settings', 'epod_auth_success', 'Successfully authenticated with Podio! Token will expire in ' . human_time_diff( time(), time() + $expires_in ) . '.', 'success' );
        
        return true;
    } else {
        $error_msg = 'Authentication failed. ';
        if ( isset( $data['error_description'] ) ) {
            $error_msg .= $data['error_description'];
        } elseif ( isset( $data['error'] ) ) {
            $error_msg .= $data['error'];
        }
        
        epod_log( 'ERROR: ' . $error_msg );
        add_settings_error( 'epod_settings', 'epod_auth_error', $error_msg, 'error' );
        
        return false;
    }
}

/**
 * Refresh access token using refresh token
 */
function epod_refresh_access_token() {
    $client_id = get_option( 'epod_client_id' );
    $client_secret = get_option( 'epod_client_secret' );
    $refresh_token = get_option( 'epod_refresh_token' );
    
    if ( empty( $client_id ) || empty( $client_secret ) || empty( $refresh_token ) ) {
        epod_log( 'ERROR: Missing credentials for token refresh' );
        add_settings_error( 'epod_settings', 'epod_refresh_error', 'Missing credentials for token refresh.', 'error' );
        return false;
    }
    
    $url = 'https://podio.com/oauth/token';
    
    $args = [
        'headers' => [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ],
        'body' => http_build_query( [
            'grant_type'    => 'refresh_token',
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'refresh_token' => $refresh_token,
        ] ),
        'timeout' => 30,
    ];
    
    epod_log( 'Refreshing Podio access token...' );
    
    $response = wp_remote_post( $url, $args );
    
    if ( is_wp_error( $response ) ) {
        epod_log( 'ERROR: Token refresh failed: ' . $response->get_error_message() );
        add_settings_error( 'epod_settings', 'epod_refresh_error', 'Token refresh failed: ' . $response->get_error_message(), 'error' );
        return false;
    }
    
    $response_code = wp_remote_retrieve_response_code( $response );
    $response_body = wp_remote_retrieve_body( $response );
    $data = json_decode( $response_body, true );
    
    if ( $response_code === 200 && isset( $data['access_token'] ) ) {
        update_option( 'epod_access_token', $data['access_token'] );
        
        if ( isset( $data['refresh_token'] ) ) {
            update_option( 'epod_refresh_token', $data['refresh_token'] );
        }
        
        $expires_in = isset( $data['expires_in'] ) ? $data['expires_in'] : 3600;
        update_option( 'epod_token_expires', time() + $expires_in );
        
        epod_log( 'SUCCESS: Token refreshed. New token expires in ' . $expires_in . ' seconds' );
        add_settings_error( 'epod_settings', 'epod_refresh_success', 'Token refreshed successfully! New token expires in ' . human_time_diff( time(), time() + $expires_in ) . '.', 'success' );
        
        return true;
    } else {
        $error_msg = 'Token refresh failed. ';
        if ( isset( $data['error_description'] ) ) {
            $error_msg .= $data['error_description'];
        } elseif ( isset( $data['error'] ) ) {
            $error_msg .= $data['error'];
        }
        
        epod_log( 'ERROR: ' . $error_msg );
        add_settings_error( 'epod_settings', 'epod_refresh_error', $error_msg, 'error' );
        
        // Clear tokens if refresh failed
        delete_option( 'epod_access_token' );
        delete_option( 'epod_refresh_token' );
        delete_option( 'epod_token_expires' );
        
        return false;
    }
}

/**
 * Revoke access token
 */
function epod_revoke_access_token() {
    $access_token = get_option( 'epod_access_token' );
    
    if ( empty( $access_token ) ) {
        add_settings_error( 'epod_settings', 'epod_revoke_warning', 'No active token to revoke.', 'warning' );
        return;
    }
    
    // Podio doesn't have a standard token revocation endpoint
    // We'll just clear the stored tokens
    delete_option( 'epod_access_token' );
    delete_option( 'epod_refresh_token' );
    delete_option( 'epod_token_expires' );
    
    epod_log( 'Tokens revoked and cleared from database' );
    add_settings_error( 'epod_settings', 'epod_revoke_success', 'Tokens revoked and cleared from database.', 'success' );
}

/**
 * Get valid access token (checks expiration and refreshes if needed)
 */
function epod_get_valid_access_token() {
    $access_token = get_option( 'epod_access_token' );
    $refresh_token = get_option( 'epod_refresh_token' );
    $token_expires = get_option( 'epod_token_expires' );
    
    // If no access token, try to authenticate
    if ( empty( $access_token ) ) {
        epod_log( 'WARNING: No access token available' );
        return false;
    }
    
    // Check if token is expired or about to expire (within 5 minutes)
    $current_time = time();
    if ( $token_expires && ( $token_expires - $current_time ) < 300 ) {
        epod_log( 'Token expired or about to expire. Attempting refresh...' );
        
        if ( ! empty( $refresh_token ) ) {
            // Refresh token in background
            add_action( 'shutdown', 'epod_refresh_access_token_background' );
            return $access_token; // Return current token, refresh will happen after response
        } else {
            epod_log( 'ERROR: Token expired and no refresh token available' );
            return false;
        }
    }
    
    return $access_token;
}

/**
 * Refresh token in background (non-blocking)
 */
function epod_refresh_access_token_background() {
    epod_refresh_access_token();
}

/**
 * =========================================================
 * PODIO API FUNCTIONS
 * =========================================================
 */

/**
 * Make API request to Podio with proper authentication
 */
function epod_api_request( $endpoint, $method = 'GET', $data = [] ) {
    $access_token = epod_get_valid_access_token();
    
    if ( ! $access_token ) {
        return new WP_Error( 'no_token', 'Podio access token missing or expired' );
    }
    
    $base_url = 'https://api.podio.com';
    $url = $base_url . $endpoint;
    
    $headers = [
        'Authorization' => 'Bearer ' . $access_token,
        'Content-Type'  => 'application/json',
    ];
    
    $args = [
        'headers' => $headers,
        'timeout' => 30,
    ];
    
    if ( in_array( $method, [ 'POST', 'PUT' ] ) && ! empty( $data ) ) {
        $args['body'] = json_encode( $data );
    }
    
    epod_log( "Podio API Request: $method $url" );
    if ( ! empty( $data ) ) {
        epod_log( "Request Data: " . wp_json_encode( $data ) );
    }
    
    switch ( $method ) {
        case 'POST':
            $response = wp_remote_post( $url, $args );
            break;
        case 'PUT':
            $args['method'] = 'PUT';
            $response = wp_remote_request( $url, $args );
            break;
        case 'DELETE':
            $args['method'] = 'DELETE';
            $response = wp_remote_request( $url, $args );
            break;
        default: // GET
            $response = wp_remote_get( $url, $args );
    }
    
    if ( is_wp_error( $response ) ) {
        epod_log( "Podio API Error: " . $response->get_error_message() );
        return $response;
    }
    
    $response_code = wp_remote_retrieve_response_code( $response );
    $response_body = wp_remote_retrieve_body( $response );
    
    epod_log( "Response Code: $response_code" );
    epod_log( "Response Body: $response_body" );
    
    return [
        'code' => $response_code,
        'body' => json_decode( $response_body, true ),
        'raw'  => $response_body,
    ];
}

/**
 * Create item in Podio app
 */
function epod_create_item( $app_id, $fields ) {
    $data = [
        'fields' => $fields,
    ];
    
    epod_log( "Creating item in app $app_id with data: " . wp_json_encode( $data ) );
    
    $result = epod_api_request( "/item/app/$app_id/", 'POST', $data );
    
    return $result;
}

/**
 * =========================================================
 * ELEMENTOR FORM HANDLER - FIXED VERSION
 * =========================================================
 */
add_action( 'elementor_pro/forms/new_record', 'epod_handle_elementor_submission', 20, 2 );

function epod_handle_elementor_submission( $record, $handler ) {
    // Log to PHP error log first for debugging
    error_log( 'Podio Integration: Starting form submission handler' );
    
    epod_log( '=== Elementor Form Submission Started ===' );
    
    /**
     * Get the target form name from settings
     */
    $target_form_name = get_option( 'epod_form_name', 'Submit Deal' );
    
    // Try to get form name
    try {
        $form_name = $record->get_form_settings( 'form_name' );
        epod_log( 'Got form name: ' . $form_name );
        
        if ( $form_name !== $target_form_name ) {
            epod_log( 'Skipping form - name does not match target. Target: ' . $target_form_name . ', Got: ' . $form_name );
            return;
        }
    } catch ( Exception $e ) {
        epod_log( 'Error getting form name: ' . $e->getMessage() );
        return;
    }
    
    /**
     * Check if Podio credentials are configured
     */
    $app_id = get_option( 'epod_app_id' );
    $access_token = epod_get_valid_access_token();
    
    if ( empty( $app_id ) ) {
        $error_msg = 'Podio App ID missing. Please configure it in Settings > Podio Integration.';
        epod_log( 'ERROR: ' . $error_msg );
        return; // Don't break the form submission, just log and return
    }
    
    if ( empty( $access_token ) ) {
        $error_msg = 'Podio authentication failed. Please authenticate in Settings > Podio Integration.';
        epod_log( 'ERROR: ' . $error_msg );
        return; // Don't break the form submission, just log and return
    }
    
    epod_log( 'Processing form for Podio integration...' );
    epod_log( 'Using App ID: ' . $app_id );
    
    /**
     * Get form fields - SAFELY
     */
    $raw_fields = $record->get( 'fields' );
    $fields = [];
    
    if ( ! empty( $raw_fields ) && is_array( $raw_fields ) ) {
        foreach ( $raw_fields as $id => $field ) {
            if ( isset( $field['value'] ) ) {
                $clean_id = str_replace( ['form-field-', 'form_fields[', ']'], '', $id );
                $fields[ $clean_id ] = sanitize_text_field( $field['value'] );
            }
        }
    }
    
    epod_log( 'Form Fields Received: ' . print_r( $fields, true ) );
    
    /**
     * Map Elementor fields to Podio fields
     * IMPORTANT: Update the option IDs based on your actual Podio app!
     */
    $podio_fields = [];
    
    // 1. Name field → "title" (text field)
    if ( ! empty( $fields['name'] ) ) {
        $podio_fields[] = [
            'external_id' => 'title',
            'values' => $fields['name']
        ];
    } elseif ( ! empty( $fields['title'] ) ) {
        $podio_fields[] = [
            'external_id' => 'title',
            'values' => $fields['title']
        ];
    }
    
    // 2. Phone field → "phone" (phone field)
    if ( ! empty( $fields['phone'] ) ) {
        $podio_fields[] = [
            'external_id' => 'phone',
            'values' => [[
                'type' => 'mobile',
                'value' => $fields['phone']
            ]]
        ];
    }
    
    // 3. Property Address field → "property-address-or-city-state" (text field)
    if ( ! empty( $fields['propertyAddress'] ) ) {
        $podio_fields[] = [
            'external_id' => 'property-address-or-city-state',
            'values' => $fields['propertyAddress']
        ];
    }
    
    // 4. Asking Price field → "asking-price" (text field)
    if ( ! empty( $fields['askingPrice'] ) ) {
        $podio_fields[] = [
            'external_id' => 'asking-price',
            'values' => $fields['askingPrice']
        ];
    }
    
    // 5. How often do you do deals? → "how-often-do-you-do-deals" (category field)
    if ( ! empty( $fields['howOften'] ) ) {
        // Map options to Podio option IDs - YOU NEED TO UPDATE THESE!
        $deal_frequency_options = [
            'First deal / learning' => 1, // REPLACE with actual Podio option ID
            '1–2 deals per year' => 2,    // REPLACE with actual Podio option ID
            '1–2 deals per quarter' => 3, // REPLACE with actual Podio option ID
            'Monthly or more' => 4,       // REPLACE with actual Podio option ID
        ];
        
        $selected_option = $fields['howOften'];
        if ( isset( $deal_frequency_options[ $selected_option ] ) ) {
            $podio_fields[] = [
                'external_id' => 'how-often-do-you-do-deals',
                'values' => $deal_frequency_options[ $selected_option ]
            ];
        } else {
            epod_log( "WARNING: Unknown deal frequency option: $selected_option" );
        }
    }
    
    // 6. Email field → "email" (email field)
    if ( ! empty( $fields['email'] ) ) {
        $podio_fields[] = [
            'external_id' => 'email',
            'values' => [[
                'type' => 'other',
                'value' => $fields['email']
            ]]
        ];
    }
    
    // 7. ARV field → "arv-optional" (text field)
    if ( ! empty( $fields['arv'] ) ) {
        $podio_fields[] = [
            'external_id' => 'arv-optional',
            'values' => $fields['arv']
        ];
    }
    
    // 8. Estimated Repairs → "estimated-repairs-optional-2" (category field)
    if ( ! empty( $fields['estimatedRepairs'] ) ) {
        $repair_options = [
            'Turn-key' => 1,      // REPLACE with actual Podio option ID
            'Light Rehab' => 2,   // REPLACE with actual Podio option ID
            'Medium Rehab' => 3,  // REPLACE with actual Podio option ID
            'Full Rehab' => 4,    // REPLACE with actual Podio option ID
        ];
        
        $selected_option = $fields['estimatedRepairs'];
        if ( isset( $repair_options[ $selected_option ] ) ) {
            $podio_fields[] = [
                'external_id' => 'estimated-repairs-optional-2',
                'values' => $repair_options[ $selected_option ]
            ];
        } else {
            epod_log( "WARNING: Unknown repair option: $selected_option" );
        }
    }
    
    // 9. Deal Type → "deal-type-optional-2" (category field)
    if ( ! empty( $fields['dealType'] ) ) {
        $deal_type_options = [
            'Assignment' => 1,        // REPLACE with actual Podio option ID
            'Double Close' => 2,      // REPLACE with actual Podio option ID
            'Novation' => 3,          // REPLACE with actual Podio option ID
            'Creative Finance' => 4,  // REPLACE with actual Podio option ID
            'Unsure' => 5,            // REPLACE with actual Podio option ID
        ];
        
        $selected_option = $fields['dealType'];
        if ( isset( $deal_type_options[ $selected_option ] ) ) {
            $podio_fields[] = [
                'external_id' => 'deal-type-optional-2',
                'values' => $deal_type_options[ $selected_option ]
            ];
        } else {
            epod_log( "WARNING: Unknown deal type option: $selected_option" );
        }
    }
    
    // 10. Timeline to Close → "timeline-to-close-optional-2" (date field)
    if ( ! empty( $fields['closeTimeline'] ) ) {
        $date_value = epod_format_date_for_podio( $fields['closeTimeline'] );
        if ( $date_value ) {
            $podio_fields[] = [
                'external_id' => 'timeline-to-close-optional-2',
                'values' => [
                    'start' => $date_value,
                    'end' => $date_value
                ]
            ];
        }
    }
    
    /**
     * Create item in Podio - ASYNCHRONOUSLY to not block form submission
     */
    if ( ! empty( $podio_fields ) ) {
        epod_log( 'Creating Podio item with ' . count( $podio_fields ) . ' fields' );
        
        // Schedule async processing to avoid blocking form submission
        add_action( 'shutdown', function() use ( $app_id, $podio_fields ) {
            epod_process_podio_submission( $app_id, $podio_fields );
        });
    } else {
        epod_log( 'WARNING: No fields to send to Podio' );
    }
    
    epod_log( '=== Elementor Form Submission Completed ===' );
}

/**
 * Process Podio submission asynchronously
 */
function epod_process_podio_submission( $app_id, $podio_fields ) {
    epod_log( 'Starting async Podio submission...' );
    
    $result = epod_create_item( $app_id, $podio_fields );
    
    if ( ! is_wp_error( $result ) ) {
        if ( in_array( $result['code'], [ 200, 201 ] ) ) {
            $item_id = $result['body']['item_id'] ?? null;
            if ( $item_id ) {
                epod_log( '✅ SUCCESS: Created Podio item with ID: ' . $item_id );
            } else {
                epod_log( 'WARNING: Item created but no item_id in response' );
            }
        } else {
            $error_msg = 'Failed to create Podio item. Status: ' . $result['code'];
            if ( isset( $result['body']['error_description'] ) ) {
                $error_msg .= ' - ' . $result['body']['error_description'];
            }
            if ( isset( $result['body']['error'] ) ) {
                $error_msg .= ' - ' . $result['body']['error'];
            }
            epod_log( 'ERROR: ' . $error_msg );
            
            // If it's an authentication error, try to refresh token
            if ( $result['code'] === 401 && isset( $result['body']['error'] ) && $result['body']['error'] === 'expired_token' ) {
                epod_log( 'Token expired during submission, attempting refresh...' );
                epod_refresh_access_token();
            }
        }
    } else {
        epod_log( 'ERROR: Podio API request failed: ' . $result->get_error_message() );
    }
    
    epod_log( '=== Async Podio Submission Completed ===' );
}

/**
 * Format date for Podio (YYYY-MM-DD HH:MM:SS)
 */
function epod_format_date_for_podio( $date_string ) {
    if ( empty( $date_string ) ) {
        return '';
    }
    
    $timestamp = strtotime( $date_string );
    if ( $timestamp === false ) {
        epod_log( 'WARNING: Could not parse date: ' . $date_string );
        return '';
    }
    
    return date( 'Y-m-d 12:00:00', $timestamp );
}

/**
 * =========================================================
 * CLEANUP FUNCTION
 * =========================================================
 */
register_deactivation_hook( __FILE__, 'epod_cleanup' );

function epod_cleanup() {
    // Remove debug logs on deactivation
    delete_option( 'epod_debug_log' );
    // Optionally clear tokens on deactivation
    // delete_option( 'epod_access_token' );
    // delete_option( 'epod_refresh_token' );
    // delete_option( 'epod_token_expires' );
}
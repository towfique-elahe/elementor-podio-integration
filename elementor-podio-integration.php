<?php
/**
 * Plugin Name: Elementor → Podio Integration
 * Description: Sends Elementor form submissions to Podio.
 * Version: 1.1
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
        'epod_app_token',
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
                    <label for="epod_app_token">Podio App Token</label>
                </th>
                <td>
                    <input type="password" id="epod_app_token" name="epod_app_token"
                        value="<?php echo esc_attr( get_option( 'epod_app_token' ) ); ?>" class="regular-text" />
                    <p class="description">Your Podio App Token.</p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="epod_form_name">Target Form Name</label>
                </th>
                <td>
                    <input type="text" id="epod_form_name" name="epod_form_name"
                        value="<?php echo esc_attr( get_option( 'epod_form_name', 'New Form' ) ); ?>"
                        class="regular-text" />
                    <p class="description">Enter the name of the Elementor form to process (default: "New Form").</p>
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
 * PODIO API FUNCTIONS
 * =========================================================
 */

/**
 * Get Podio Access Token
 */
function epod_get_access_token() {
    $client_id = get_option( 'epod_client_id' );
    $client_secret = get_option( 'epod_client_secret' );
    $app_token = get_option( 'epod_app_token' );
    
    if ( empty( $client_id ) || empty( $client_secret ) ) {
        epod_log( 'WARNING: Missing Podio Client ID or Secret' );
    }
    
    if ( empty( $app_token ) ) {
        epod_log( 'ERROR: Missing Podio App Token' );
        return false;
    }
    
    // Using App Token directly for simplicity
    return $app_token;
}

/**
 * Make API request to Podio
 */
function epod_api_request( $endpoint, $method = 'GET', $data = [] ) {
    $access_token = epod_get_access_token();
    
    if ( ! $access_token ) {
        return new WP_Error( 'no_token', 'Podio access token missing' );
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
        epod_log( "Request Data: " . json_encode( $data ) );
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
 * Get Podio app fields
 */
function epod_get_app_fields( $app_id ) {
    $result = epod_api_request( "/item/app/$app_id/field/" );
    
    if ( ! is_wp_error( $result ) && $result['code'] === 200 ) {
        return $result['body'];
    }
    
    return false;
}

/**
 * Create item in Podio app
 */
function epod_create_item( $app_id, $fields ) {
    $data = [
        'fields' => $fields,
    ];
    
    epod_log( "Creating item in app $app_id with data: " . json_encode( $data ) );
    
    $result = epod_api_request( "/item/app/$app_id/", 'POST', $data );
    
    return $result;
}

/**
 * Get category option ID
 */
function epod_get_category_option_id( $app_id, $field_external_id, $option_label ) {
    if ( empty( $option_label ) ) {
        return null;
    }
    
    // First, get the field definition
    $field_result = epod_api_request( "/item/field/$field_external_id" );
    
    if ( ! is_wp_error( $field_result ) && $field_result['code'] === 200 ) {
        $field = $field_result['body'];
        
        if ( isset( $field['config']['settings']['options'] ) ) {
            foreach ( $field['config']['settings']['options'] as $option ) {
                if ( strcasecmp( $option['text'], $option_label ) === 0 ) {
                    return $option['id'];
                }
            }
        }
    }
    
    epod_log( "WARNING: Could not find option ID for '$option_label' in field '$field_external_id'" );
    return null;
}

/**
 * =========================================================
 * ELEMENTOR FORM HANDLER - UPDATED WITH CORRECT MAPPINGS
 * =========================================================
 */
add_action( 'elementor_pro/forms/process', 'epod_handle_elementor_submission', 10, 2 );

function epod_handle_elementor_submission( $record, $handler ) {
    epod_log( '=== Elementor Form Submission Started ===' );
    
    /**
     * Get the target form name from settings
     */
    $target_form_name = get_option( 'epod_form_name', 'New Form' );
    
    // Try to get form name
    try {
        $current_form_name = $record->get_form_settings( 'form_name' );
        epod_log( 'Got form name: ' . $current_form_name );
        
        if ( $current_form_name !== $target_form_name ) {
            epod_log( 'Skipping form - name does not match target' );
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
    $app_token = get_option( 'epod_app_token' );
    
    if ( empty( $app_id ) || empty( $app_token ) ) {
        $error_msg = 'Podio App ID or Token missing. Please configure it in Settings > Podio Integration.';
        epod_log( 'ERROR: ' . $error_msg );
        error_log( $error_msg );
        return;
    }
    
    epod_log( 'Processing form for Podio integration...' );
    epod_log( 'Using App ID: ' . $app_id );
    
    /**
     * Get form fields
     */
    $fields = [];
    foreach ( $record->get( 'fields' ) as $id => $field ) {
        $clean_id = str_replace( 'form-field-', '', $id );
        $fields[ $clean_id ] = sanitize_text_field( $field['value'] );
    }
    
    epod_log( 'Form Fields Received: ' . print_r( $fields, true ) );
    
    /**
     * Map Elementor fields to Podio fields using your actual Podio External IDs
     */
    $podio_fields = [];
    
    // 1. Name field → "title" (text field)
    if ( ! empty( $fields['name'] ) ) {
        $podio_fields[] = [
            'external_id' => 'title',
            'values' => $fields['name']
        ];
    }
    
    // 2. Phone field → "phone" (phone field)
    if ( ! empty( $fields['field_69c49a7'] ) ) {
        $podio_fields[] = [
            'external_id' => 'phone',
            'values' => [[
                'type' => 'mobile',
                'value' => $fields['field_69c49a7']
            ]]
        ];
    }
    
    // 3. Property Address field → "property-address-or-city-state" (text field)
    if ( ! empty( $fields['field_2bd5f34'] ) ) {
        $podio_fields[] = [
            'external_id' => 'property-address-or-city-state',
            'values' => $fields['field_2bd5f34']
        ];
    }
    
    // 4. Asking Price field → "asking-price" (text field)
    if ( ! empty( $fields['field_e709b60'] ) ) {
        $podio_fields[] = [
            'external_id' => 'asking-price',
            'values' => $fields['field_e709b60']
        ];
    }
    
    // 5. How often do you do deals? → "how-often-do-you-do-deals" (category field)
    // Note: This needs to be mapped to the option ID in Podio
    if ( ! empty( $fields['field_7a0c673'] ) ) {
        // You'll need to map these options to Podio option IDs
        $deal_frequency_options = [
            'First deal / learning' => 1, // Replace with actual Podio option ID
            '1–2 deals per year' => 2,    // Replace with actual Podio option ID
            '1–2 deals per quarter' => 3, // Replace with actual Podio option ID
            'Monthly or more' => 4,       // Replace with actual Podio option ID
        ];
        
        $selected_option = $fields['field_7a0c673'];
        $option_id = isset( $deal_frequency_options[ $selected_option ] ) ? $deal_frequency_options[ $selected_option ] : null;
        
        if ( $option_id ) {
            $podio_fields[] = [
                'external_id' => 'how-often-do-you-do-deals',
                'values' => $option_id
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
    if ( ! empty( $fields['field_c20bd4a'] ) ) {
        $podio_fields[] = [
            'external_id' => 'arv-optional',
            'values' => $fields['field_c20bd4a']
        ];
    }
    
    // 8. Estimated Repairs → "estimated-repairs-optional-2" (category field)
    if ( ! empty( $fields['field_99d4db5'] ) ) {
        $repair_options = [
            'Turn-key' => 1,      // Replace with actual Podio option ID
            'Light Rehab' => 2,   // Replace with actual Podio option ID
            'Medium Rehab' => 3,  // Replace with actual Podio option ID
            'Full Rehab' => 4,    // Replace with actual Podio option ID
        ];
        
        $selected_option = $fields['field_99d4db5'];
        $option_id = isset( $repair_options[ $selected_option ] ) ? $repair_options[ $selected_option ] : null;
        
        if ( $option_id ) {
            $podio_fields[] = [
                'external_id' => 'estimated-repairs-optional-2',
                'values' => $option_id
            ];
        } else {
            epod_log( "WARNING: Unknown repair option: $selected_option" );
        }
    }
    
    // 9. Deal Type → "deal-type-optional-2" (category field)
    if ( ! empty( $fields['field_ee4e4e4'] ) ) {
        $deal_type_options = [
            'Assignment' => 1,        // Replace with actual Podio option ID
            'Double Close' => 2,      // Replace with actual Podio option ID
            'Novation' => 3,          // Replace with actual Podio option ID
            'Creative Finance' => 4,  // Replace with actual Podio option ID
            'Unsure' => 5,            // Replace with actual Podio option ID
        ];
        
        $selected_option = $fields['field_ee4e4e4'];
        $option_id = isset( $deal_type_options[ $selected_option ] ) ? $deal_type_options[ $selected_option ] : null;
        
        if ( $option_id ) {
            $podio_fields[] = [
                'external_id' => 'deal-type-optional-2',
                'values' => $option_id
            ];
        } else {
            epod_log( "WARNING: Unknown deal type option: $selected_option" );
        }
    }
    
    // 10. Timeline to Close → "timeline-to-close-optional-2" (date field)
    if ( ! empty( $fields['field_827b0cf'] ) ) {
        $date_value = epod_format_date_for_podio( $fields['field_827b0cf'] );
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
    
    // 11. Upload Contract/Photos → Podio file field (need to know the external_id)
    // Note: Your Podio app doesn't seem to have a file field in the screenshot
    // If you have one, add it here with the correct external_id
    
    /**
     * Create item in Podio
     */
    if ( ! empty( $podio_fields ) ) {
        epod_log( 'Creating Podio item with ' . count( $podio_fields ) . ' fields' );
        epod_log( 'Podio fields data: ' . json_encode( $podio_fields ) );
        
        $result = epod_create_item( $app_id, $podio_fields );
        
        if ( ! is_wp_error( $result ) ) {
            if ( in_array( $result['code'], [ 200, 201 ] ) ) {
                $item_id = $result['body']['item_id'] ?? null;
                if ( $item_id ) {
                    epod_log( '✅ SUCCESS: Created Podio item with ID: ' . $item_id );
                    
                    // Add success response
                    $handler->add_response_data( true, [
                        'message' => 'Your submission has been sent to Podio successfully!',
                    ]);
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
                
                // Add error response
                $handler->add_error( 'podio_error', 'There was an error submitting to Podio. Please try again.' );
            }
        } else {
            epod_log( 'ERROR: Podio API request failed: ' . $result->get_error_message() );
            $handler->add_error( 'podio_error', 'There was an error connecting to Podio. Please try again.' );
        }
    } else {
        epod_log( 'WARNING: No fields to send to Podio' );
    }
    
    epod_log( '=== Elementor Form Submission Completed ===' );
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
}
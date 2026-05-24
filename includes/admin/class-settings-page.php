<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers the Settings → Podio Integration page.
 * Only API credentials live here. Everything else is on the top-level admin page.
 */
class EPOD_Settings_Page {

    static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
    }

    static function register_menu() {
        add_options_page(
            'Podio API Credentials',
            'Podio Integration',
            'manage_options',
            'podio-credentials',
            [ __CLASS__, 'render' ]
        );
    }

    static function register_settings() {
        $string_opts = [
            'epod_client_id',
            'epod_client_secret',
            'epod_username',
            'epod_password',
            'epod_debug_mode',
        ];

        foreach ( $string_opts as $opt ) {
            register_setting( 'epod_credentials_group', $opt, [
                'sanitize_callback' => 'sanitize_text_field',
            ] );
        }

        // Tokens are managed programmatically but must be registered to prevent
        // WordPress from stripping them on options.php save.
        register_setting( 'epod_credentials_group', 'epod_access_token',  [ 'sanitize_callback' => 'sanitize_text_field' ] );
        register_setting( 'epod_credentials_group', 'epod_refresh_token', [ 'sanitize_callback' => 'sanitize_text_field' ] );
        register_setting( 'epod_credentials_group', 'epod_token_expires', [ 'sanitize_callback' => 'absint' ] );
    }

    static function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $logs_url = admin_url( 'admin.php?page=podio-integration-logs' );
        $auth_url = admin_url( 'admin.php?page=podio-integration-auth' );
        ?>
        <div class="wrap">
            <h1>Podio API Credentials</h1>
            <p class="description">
                Enter your Podio API credentials below. You can find or create them at
                <a href="https://podio.com/settings/api" target="_blank" rel="noopener noreferrer">podio.com/settings/api</a>.
                After saving, go to <a href="<?php echo esc_url( $auth_url ); ?>">Podio Integration → Authentication</a>
                to connect your account.
            </p>

            <?php settings_errors( 'epod_credentials_group' ); ?>

            <form method="post" action="options.php">
                <?php settings_fields( 'epod_credentials_group' ); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="epod_client_id">Client ID</label></th>
                        <td>
                            <input type="text" id="epod_client_id" name="epod_client_id"
                                   value="<?php echo esc_attr( get_option( 'epod_client_id' ) ); ?>"
                                   class="regular-text" autocomplete="off" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="epod_client_secret">Client Secret</label></th>
                        <td>
                            <input type="password" id="epod_client_secret" name="epod_client_secret"
                                   value="<?php echo esc_attr( get_option( 'epod_client_secret' ) ); ?>"
                                   class="regular-text" autocomplete="new-password" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="epod_username">Podio Account Email</label></th>
                        <td>
                            <input type="email" id="epod_username" name="epod_username"
                                   value="<?php echo esc_attr( get_option( 'epod_username' ) ); ?>"
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="epod_password">Podio Account Password</label></th>
                        <td>
                            <input type="password" id="epod_password" name="epod_password"
                                   value="<?php echo esc_attr( get_option( 'epod_password' ) ); ?>"
                                   class="regular-text" autocomplete="new-password" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="epod_debug_mode">Debug Mode</label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="epod_debug_mode" name="epod_debug_mode"
                                       value="1" <?php checked( get_option( 'epod_debug_mode' ), '1' ); ?> />
                                Enable verbose logging
                            </label>
                            <p class="description">
                                Logs all API requests and responses to
                                <a href="<?php echo esc_url( $logs_url ); ?>">Podio Integration → Debug Logs</a>.
                                Disable on production once everything works.
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button( 'Save Credentials' ); ?>
            </form>

            <div style="margin-top:10px; padding:15px 20px; background:#f0f6fc; border-left:4px solid #2271b1; border-radius:2px;">
                <p style="margin:0;">
                    <strong>Next step:</strong> After saving, go to
                    <a href="<?php echo esc_url( $auth_url ); ?>"><strong>Podio Integration → Authentication</strong></a>
                    to connect your account and obtain an access token.
                </p>
            </div>
        </div>
        <?php
    }
}

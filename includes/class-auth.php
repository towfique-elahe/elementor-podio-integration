<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EPOD_Auth {

    private static $last_error = '';

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    static function get_last_error() {
        return self::$last_error;
    }

    static function authenticate() {
        $creds = self::get_credentials();
        if ( ! $creds ) {
            self::$last_error = 'Missing API credentials. Go to Settings → Podio Integration to enter them.';
            EPOD_Logger::log( 'ERROR: ' . self::$last_error );
            return false;
        }

        EPOD_Logger::log( 'Authenticating with Podio (password grant)...' );

        $response = wp_remote_post( 'https://podio.com/oauth/token', [
            'headers' => [ 'Content-Type' => 'application/x-www-form-urlencoded' ],
            'body'    => http_build_query( [
                'grant_type'    => 'password',
                'client_id'     => $creds['client_id'],
                'client_secret' => $creds['client_secret'],
                'username'      => $creds['username'],
                'password'      => $creds['password'],
            ] ),
            'timeout' => 30,
        ] );

        return self::handle_token_response( $response, 'Authentication' );
    }

    static function refresh() {
        $client_id     = get_option( 'epod_client_id' );
        $client_secret = get_option( 'epod_client_secret' );
        $refresh_token = get_option( 'epod_refresh_token' );

        if ( empty( $client_id ) || empty( $client_secret ) || empty( $refresh_token ) ) {
            self::$last_error = 'Missing credentials or refresh token for token refresh.';
            EPOD_Logger::log( 'ERROR: ' . self::$last_error );
            return false;
        }

        EPOD_Logger::log( 'Refreshing Podio access token...' );

        $response = wp_remote_post( 'https://podio.com/oauth/token', [
            'headers' => [ 'Content-Type' => 'application/x-www-form-urlencoded' ],
            'body'    => http_build_query( [
                'grant_type'    => 'refresh_token',
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'refresh_token' => $refresh_token,
            ] ),
            'timeout' => 30,
        ] );

        $result = self::handle_token_response( $response, 'Token refresh' );

        if ( ! $result ) {
            delete_option( 'epod_access_token' );
            delete_option( 'epod_refresh_token' );
            delete_option( 'epod_token_expires' );
        }

        return $result;
    }

    static function revoke() {
        delete_option( 'epod_access_token' );
        delete_option( 'epod_refresh_token' );
        delete_option( 'epod_token_expires' );
        EPOD_Logger::log( 'Access token revoked and cleared.' );
    }

    /**
     * Returns the current access token if valid, or null.
     * Schedules a background refresh when the token is about to expire.
     *
     * @return string|null
     */
    static function get_valid_token() {
        $token         = get_option( 'epod_access_token' );
        $refresh_token = get_option( 'epod_refresh_token' );
        $expires       = (int) get_option( 'epod_token_expires' );

        if ( empty( $token ) ) {
            EPOD_Logger::log( 'WARNING: No access token stored.' );
            return null;
        }

        // Token expires within 5 minutes → refresh in background after response
        if ( $expires && ( $expires - time() ) < 300 ) {
            if ( ! empty( $refresh_token ) ) {
                EPOD_Logger::log( 'Token expiring soon — scheduling background refresh.' );
                add_action( 'shutdown', [ __CLASS__, 'refresh_background' ] );
            } else {
                EPOD_Logger::log( 'ERROR: Token expired and no refresh token available.' );
                return null;
            }
        }

        return $token;
    }

    static function refresh_background() {
        self::refresh();
    }

    /**
     * Returns a structured status array for display in the admin UI.
     *
     * @return array{state: string, label: string, color: string, expires_in?: int, has_refresh?: bool}
     */
    static function get_status() {
        $token   = get_option( 'epod_access_token' );
        $expires = (int) get_option( 'epod_token_expires' );
        $refresh = get_option( 'epod_refresh_token' );

        if ( empty( $token ) ) {
            return [
                'state' => 'unauthenticated',
                'label' => 'Not Authenticated',
                'color' => '#dc3232',
            ];
        }

        $expires_in = $expires ? $expires - time() : 0;

        if ( $expires_in <= 0 ) {
            return [
                'state'       => 'expired',
                'label'       => 'Token Expired',
                'color'       => '#f0b849',
                'has_refresh' => ! empty( $refresh ),
            ];
        }

        if ( $expires_in < 3600 ) {
            return [
                'state'       => 'expiring',
                'label'       => 'Expiring Soon',
                'color'       => '#f0b849',
                'expires_in'  => $expires_in,
                'has_refresh' => ! empty( $refresh ),
            ];
        }

        return [
            'state'       => 'active',
            'label'       => 'Authenticated',
            'color'       => '#46b450',
            'expires_in'  => $expires_in,
            'expires_at'  => $expires,
            'has_refresh' => ! empty( $refresh ),
        ];
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private static function get_credentials() {
        $creds = [
            'client_id'     => get_option( 'epod_client_id' ),
            'client_secret' => get_option( 'epod_client_secret' ),
            'username'      => get_option( 'epod_username' ),
            'password'      => get_option( 'epod_password' ),
        ];

        foreach ( $creds as $v ) {
            if ( empty( $v ) ) {
                return null;
            }
        }

        return $creds;
    }

    private static function handle_token_response( $response, $context ) {
        if ( is_wp_error( $response ) ) {
            self::$last_error = $context . ' failed: ' . $response->get_error_message();
            EPOD_Logger::log( 'ERROR: ' . self::$last_error );
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code === 200 && isset( $data['access_token'] ) ) {
            update_option( 'epod_access_token', $data['access_token'] );

            if ( isset( $data['refresh_token'] ) ) {
                update_option( 'epod_refresh_token', $data['refresh_token'] );
            }

            $expires_in = isset( $data['expires_in'] ) ? (int) $data['expires_in'] : 3600;
            update_option( 'epod_token_expires', time() + $expires_in );

            EPOD_Logger::log( "SUCCESS: {$context} succeeded. Token expires in {$expires_in}s." );
            return true;
        }

        $detail         = $data['error_description'] ?? $data['error'] ?? 'Unknown error';
        self::$last_error = "{$context} failed (HTTP {$code}): {$detail}";
        EPOD_Logger::log( 'ERROR: ' . self::$last_error );
        return false;
    }
}

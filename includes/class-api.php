<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EPOD_API {

    const BASE_URL = 'https://api.podio.com';

    /**
     * Make an authenticated request to the Podio API.
     *
     * @param  string $endpoint  e.g. '/item/app/12345/'
     * @param  string $method    GET | POST | PUT | DELETE
     * @param  array  $data      Request body for POST/PUT
     * @return array|WP_Error    ['code' => int, 'body' => array, 'raw' => string]
     */
    static function request( $endpoint, $method = 'GET', $data = [] ) {
        $token = EPOD_Auth::get_valid_token();

        if ( ! $token ) {
            return new WP_Error( 'no_token', 'Podio access token is missing or expired.' );
        }

        $url  = self::BASE_URL . $endpoint;
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 30,
        ];

        if ( in_array( $method, [ 'POST', 'PUT' ], true ) && ! empty( $data ) ) {
            $args['body'] = wp_json_encode( $data );
        }

        EPOD_Logger::log( "API {$method} {$endpoint}" );

        if ( ! empty( $data ) ) {
            EPOD_Logger::log( 'Payload: ' . wp_json_encode( $data ) );
        }

        switch ( $method ) {
            case 'POST':
                $response = wp_remote_post( $url, $args );
                break;
            case 'PUT':
                $args['method'] = 'PUT';
                $response       = wp_remote_request( $url, $args );
                break;
            case 'DELETE':
                $args['method'] = 'DELETE';
                $response       = wp_remote_request( $url, $args );
                break;
            default:
                $response = wp_remote_get( $url, $args );
        }

        if ( is_wp_error( $response ) ) {
            EPOD_Logger::log( 'API error: ' . $response->get_error_message() );
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        EPOD_Logger::log( "Response {$code}: {$body}" );

        return [
            'code' => $code,
            'body' => json_decode( $body, true ),
            'raw'  => $body,
        ];
    }

    /**
     * Create a new item in a Podio app.
     *
     * @param  string $app_id
     * @param  array  $fields  Podio field values array
     * @return array|WP_Error
     */
    static function create_item( $app_id, $fields ) {
        EPOD_Logger::log( "Creating item in Podio app {$app_id}" );
        return self::request( "/item/app/{$app_id}/", 'POST', [ 'fields' => $fields ] );
    }
}

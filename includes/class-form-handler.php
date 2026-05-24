<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EPOD_Form_Handler {

    static function init() {
        add_action( 'elementor_pro/forms/new_record', [ __CLASS__, 'handle' ], 20, 2 );
    }

    // =========================================================================
    // ENTRY POINT
    // =========================================================================

    static function handle( $record, $handler ) {
        error_log( '[Podio Integration] Form submission hook fired.' );
        EPOD_Logger::log( '=== Form Submission Received ===' );

        $form_name = self::get_form_name( $record );
        if ( $form_name === null ) {
            return;
        }

        $mapping = self::find_mapping( $form_name );
        if ( ! $mapping ) {
            EPOD_Logger::log( "No mapping configured for form '{$form_name}' — skipping." );
            return;
        }

        $token  = EPOD_Auth::get_valid_token();
        $app_id = $mapping['podio_app_id'];

        if ( ! $token ) {
            EPOD_Logger::log( 'ERROR: Not authenticated with Podio.' );
            return;
        }

        if ( empty( $app_id ) ) {
            EPOD_Logger::log( "ERROR: No Podio App ID configured for form '{$form_name}'." );
            return;
        }

        $values       = self::extract_values( $record );
        $podio_fields = self::build_fields( $mapping['field_mappings'], $values );

        if ( empty( $podio_fields ) ) {
            EPOD_Logger::log( 'WARNING: No fields mapped — nothing to send to Podio.' );
            return;
        }

        EPOD_Logger::log( count( $podio_fields ) . " field(s) mapped → app {$app_id}" );

        // Submit asynchronously after the response is sent to avoid blocking the form UX
        add_action( 'shutdown', function () use ( $app_id, $podio_fields ) {
            self::submit( $app_id, $podio_fields );
        } );
    }

    // =========================================================================
    // ASYNC SUBMISSION
    // =========================================================================

    static function submit( $app_id, $podio_fields ) {
        EPOD_Logger::log( 'Submitting to Podio...' );

        $result = EPOD_API::create_item( $app_id, $podio_fields );

        if ( is_wp_error( $result ) ) {
            EPOD_Logger::log( 'ERROR: ' . $result->get_error_message() );
            return;
        }

        if ( in_array( $result['code'], [ 200, 201 ], true ) ) {
            $item_id = $result['body']['item_id'] ?? null;
            EPOD_Logger::log( 'SUCCESS: Item created' . ( $item_id ? " (ID: {$item_id})" : '' ) . '.' );
        } else {
            $err = $result['body']['error_description'] ?? $result['body']['error'] ?? 'Unknown error';
            EPOD_Logger::log( "ERROR: HTTP {$result['code']} — {$err}" );

            if ( $result['code'] === 401 ) {
                EPOD_Logger::log( 'Token may be expired — attempting refresh.' );
                EPOD_Auth::refresh_background();
            }
        }

        EPOD_Logger::log( '=== Podio Submission Complete ===' );
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private static function get_form_name( $record ) {
        try {
            $name = $record->get_form_settings( 'form_name' );
            EPOD_Logger::log( "Form name: '{$name}'" );
            return $name;
        } catch ( Exception $e ) {
            EPOD_Logger::log( 'Could not read form name: ' . $e->getMessage() );
            return null;
        }
    }

    private static function find_mapping( $form_name ) {
        $mappings = get_option( 'epod_form_mappings', [] );

        foreach ( $mappings as $m ) {
            if ( $m['form_name'] === $form_name ) {
                return $m;
            }
        }

        return null;
    }

    /**
     * Pull all field values out of the Elementor record, indexed by both
     * the raw key (e.g. 'form-field-email') and the cleaned key (e.g. 'email').
     */
    private static function extract_values( $record ) {
        $values = [];
        $fields = $record->get( 'fields' );

        if ( ! is_array( $fields ) ) {
            return $values;
        }

        foreach ( $fields as $id => $field ) {
            if ( ! isset( $field['value'] ) ) {
                continue;
            }

            $clean            = str_replace( [ 'form-field-', 'form_fields[', ']' ], '', $id );
            $values[ $id ]    = $field['value'];
            $values[ $clean ] = $field['value'];
        }

        EPOD_Logger::log( 'Submitted field keys: ' . implode( ', ', array_unique( array_keys( $values ) ) ) );
        return $values;
    }

    private static function build_fields( $field_mappings, $values ) {
        $podio_fields = [];

        foreach ( $field_mappings as $fm ) {
            $el_id  = $fm['elementor_id'];
            $pod_id = $fm['podio_external_id'];
            $type   = $fm['field_type'] ?? 'text';
            $value  = $values[ $el_id ] ?? null;

            if ( $value === null || $value === '' ) {
                EPOD_Logger::log( "  '{$el_id}' not in submission — skipping." );
                continue;
            }

            $value     = sanitize_text_field( $value );
            $podio_val = self::convert_value( $value, $type, $fm['category_options'] ?? [], $el_id );

            if ( $podio_val !== null ) {
                $podio_fields[] = [
                    'external_id' => $pod_id,
                    'values'      => $podio_val,
                ];
                EPOD_Logger::log( "  '{$el_id}' → '{$pod_id}' [{$type}]" );
            }
        }

        return $podio_fields;
    }

    /**
     * Convert a raw string value to the correct Podio field value shape.
     *
     * @return mixed  Value to pass in the 'values' key, or null to skip.
     */
    private static function convert_value( $value, $type, $cat_options, $el_id ) {
        switch ( $type ) {
            case 'text':
            case 'number':
                return $value;

            case 'email':
                return [ [ 'type' => 'other', 'value' => $value ] ];

            case 'phone':
                return [ [ 'type' => 'mobile', 'value' => $value ] ];

            case 'date':
                $ts = strtotime( $value );
                if ( $ts === false ) {
                    EPOD_Logger::log( "WARNING: Could not parse date '{$value}' for field '{$el_id}'." );
                    return null;
                }
                $fmt = date( 'Y-m-d H:i:s', $ts );
                return [ 'start' => $fmt, 'end' => $fmt ];

            case 'category':
                foreach ( $cat_options as $opt ) {
                    if ( $opt['label'] === $value ) {
                        return (int) $opt['option_id'];
                    }
                }
                EPOD_Logger::log( "WARNING: No option_id mapped for category value '{$value}' in field '{$el_id}'." );
                return null;

            default:
                return $value;
        }
    }
}

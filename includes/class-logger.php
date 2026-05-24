<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EPOD_Logger {

    const OPTION_KEY  = 'epod_debug_log';
    const MAX_ENTRIES = 200;

    static function log( $message ) {
        error_log( '[Podio Integration] ' . $message );

        if ( ! get_option( 'epod_debug_mode' ) ) {
            return;
        }

        $log   = get_option( self::OPTION_KEY, [] );
        $log[] = '[' . current_time( 'Y-m-d H:i:s' ) . '] ' . $message;

        if ( count( $log ) > self::MAX_ENTRIES ) {
            $log = array_slice( $log, -self::MAX_ENTRIES );
        }

        update_option( self::OPTION_KEY, $log, false );
    }

    static function get( $limit = 100 ) {
        return array_slice( get_option( self::OPTION_KEY, [] ), -$limit );
    }

    static function count_all() {
        return count( get_option( self::OPTION_KEY, [] ) );
    }

    static function clear() {
        update_option( self::OPTION_KEY, [], false );
    }
}

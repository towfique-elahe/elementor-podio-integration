<?php
/**
 * Plugin Name: Elementor → Podio Integration
 * Description: Sends Elementor form submissions to Podio with dynamic multi-form field mapping.
 * Version: 2.0
 * Author: Towfique Elahe
 * Author URI: https://towfiqueelahe.com/
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'EPOD_VERSION',    '2.0' );
define( 'EPOD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EPOD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once EPOD_PLUGIN_DIR . 'includes/class-logger.php';
require_once EPOD_PLUGIN_DIR . 'includes/class-auth.php';
require_once EPOD_PLUGIN_DIR . 'includes/class-api.php';
require_once EPOD_PLUGIN_DIR . 'includes/class-form-handler.php';
require_once EPOD_PLUGIN_DIR . 'includes/admin/class-settings-page.php';
require_once EPOD_PLUGIN_DIR . 'includes/admin/class-admin-page.php';

add_action( 'plugins_loaded', 'epod_init' );

function epod_init() {
    EPOD_Settings_Page::init();
    EPOD_Admin_Page::init();
    EPOD_Form_Handler::init();
}

register_deactivation_hook( __FILE__, function () {
    delete_option( 'epod_debug_log' );
} );

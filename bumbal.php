<?php

/**
 *
 * @link              http://www.bumbal.eu
 * @since             1.0.0
 * @package           Bumbal
 *
 * @wordpress-plugin
 * Plugin Name:       Bumbal
 * Plugin URI:        http://www.bumbal.eu
 * Description:       Bumbal connector for WooCommerce
 * Version:           1.0.0
 * Author:            Bumbal
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       bumbal
 * Domain Path:       /languages
 * 
 *
 * Woo: 12345:342928dfsfhsf8429842374wdf4234sfd
 * WC requires at least: 2.2
 * WC tested up to: 5.0
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 */
define( 'BUMBAL_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-bumbal-activator.php
 */
function activate_bumbal() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-bumbal-activator.php';
	Bumbal_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-bumbal-deactivator.php
 */
function deactivate_bumbal() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-bumbal-deactivator.php';
	Bumbal_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_bumbal' );
register_deactivation_hook( __FILE__, 'deactivate_bumbal' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-bumbal.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_bumbal() {

	$plugin = new Bumbal();
	$plugin->run();

}

/**
 * Detect plugin. For use on Front End only.
 */
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

if(is_plugin_active('woocommerce/woocommerce.php')) {
	run_bumbal();
}

// if(in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
// 	run_bumbal();
// }
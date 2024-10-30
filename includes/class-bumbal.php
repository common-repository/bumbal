<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       http://www.bumbal.eu
 * @since      1.0.0
 *
 * @package    Bumbal
 * @subpackage Bumbal/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Bumbal
 * @subpackage Bumbal/includes
 * @author     Bumbal <it@bumbal.eu>
 */
class Bumbal 
{

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Bumbal_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() 
	{
		if ( defined( 'BUMBAL_VERSION' ) ) {
			$this->version = BUMBAL_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'bumbal';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Bumbal_Loader. Orchestrates the hooks of the plugin.
	 * - Bumbal_i18n. Defines internationalization functionality.
	 * - Bumbal_Admin. Defines all hooks for the admin area.
	 * - Bumbal_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() 
	{

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-bumbal-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-bumbal-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-bumbal-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-bumbal-public.php';

		$this->loader = new Bumbal_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Bumbal_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() 
	{

		$plugin_i18n = new Bumbal_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() 
	{
		$plugin_admin = new Bumbal_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		// Load settings class for showing custom settings
		$this->loader->add_filter('woocommerce_get_settings_pages', $plugin_admin, 'bumbal_add_settings_page');

		// Add information for the WooCommerce order page
		$this->loader->add_action('woocommerce_admin_order_data_after_shipping_address', 
								   $plugin_admin, 'bumbal_show_status', 10, 1 );

		// Action for sending order to Bumbal
		$this->loader->add_filter('woocommerce_order_actions', $plugin_admin, 'bumbal_send_order_again_action');
								   
		// Add Bumbal status to list of status options
		$this->loader->add_action('init', $plugin_admin, 'bumbal_register_error_status');
		$this->loader->add_filter('wc_order_statuses', $plugin_admin, 'bumbal_add_error_status', 10, 1);

		// Add product field for delivery days ahead
		$this->loader->add_action('woocommerce_product_options_shipping', $plugin_admin, 'bumbal_add_product_field');
		$this->loader->add_action('woocommerce_admin_process_product_object', $plugin_admin, 'bumbal_add_product_field_save', 10, 1);
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() 
	{

		$plugin_public = new Bumbal_Public( $this->get_plugin_name(), $this->get_version() );

        //when the bumbal_instance or the bumbal_apikey is empty, none of the hooks should be triggered
        if (get_option('bumbal_instance') || get_option('bumbal_apikey')) {
            $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
            $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

            // Send order information to Bumbal
            // Get the status whereby we need to send the order
            if(get_option('bumbal_status_send') == 'completed') {
                $this->loader->add_action('woocommerce_order_status_completed', $plugin_public, 'bumbal_set_activity');
            }
			elseif(get_option('bumbal_status_send') == 'custom'){
                $this->loader->add_action(get_option('bumbal_custom_send_activity_hook'), $plugin_public, 'bumbal_set_activity');
            }
            else {
                // Default we send it with status processing
                $this->loader->add_action('woocommerce_order_status_processing', $plugin_public, 'bumbal_set_activity');
            }

			// Cancel, delete, update order
            $this->loader->add_action('woocommerce_order_status_cancelled', $plugin_public, 'bumbal_cancel_activity');
            $this->loader->add_action('wp_trash_post', $plugin_public, 'bumbal_delete_activity');
			$this->loader->add_action('woocommerce_order_action_bumbal', $plugin_public, 'bumbal_update_order');

            if(empty(trim(get_option('bumbal_timeslot_hook')))) {
                $this->loader->add_filter('woocommerce_thankyou', $plugin_public, 'bumbal_get_time_slots', intval(get_option('bumbal_timeslot_position', 10)), 1);
            }
            else {
                $this->loader->add_filter(get_option('bumbal_timeslot_hook'), $plugin_public, 'bumbal_get_time_slots', intval(get_option('bumbal_timeslot_position', 10)), 1);
            }


            // Submit form
            $this->loader->add_action('admin_post_bumbal_send_time_slot', $plugin_public, 'bumbal_set_time_slot');

            // Ajax handler for submit form
            $this->loader->add_action('wp_ajax_nopriv_bumbal_send_time_slot', $plugin_public, 'bumbal_set_time_slot_ajax');
            $this->loader->add_action('wp_ajax_bumbal_send_time_slot', $plugin_public, 'bumbal_set_time_slot_ajax');

            // Add new endpoint for receiving webhooks
            $this->loader->add_action('rest_api_init', $plugin_public, 'bumbal_create_endpoint');

            // Overwrite custom fields from ACF plug-in
            if(get_option('bumbal_ACF_plugin') == 'yes') {
                add_filter('acf/settings/remove_wp_meta_box', '__return_false');
            }

            // Check if the datamapper plug-in is active
            if(!is_plugin_active('bumbal-datamapper/bumbal-datamapper.php') || get_option('bumbal_external_datamapper') == 'no') {
                // Plug-in is NOT active so we load the default datamapper
                // Or they don't like to use the plug-in. So we use default.
                $this->loader->add_filter('bumbal_convert', $plugin_public, 'self::bumbal_convert');
            }
        }
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() 
	{
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() 
	{
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Bumbal_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() 
	{
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() 
	{
		return $this->version;
	}

}

<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://www.bumbal.eu
 * @since      1.0.0
 *
 * @package    Bumbal
 * @subpackage Bumbal/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Bumbal
 * @subpackage Bumbal/admin
 * @author     Remy <remy@bumbal.eu>
 */
class Bumbal_Admin 
{

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) 
	{

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() 
	{

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/bumbal-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() 
	{

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/bumbal-admin.js', array( 'jquery' ), $this->version, false );

	}

    /**
	 * Add a Bumbal config tab to the settings
	 *
	 * @since    1.0.0
	 */
    public function bumbal_add_settings_page($settings) 
	{
        $settings[] = include_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-bumbal-settings.php';
        return $settings;
    }

    /**
	 * Add shipping time field on product shipping tab
	 *
	 * @since    1.0.0
	 */
    public function bumbal_add_product_field() 
	{
        global $woocommerce, $post;
        // Custom Product Text Field
        woocommerce_wp_text_input([
            'id' => 'bumbal_shipping_time',
            'label' => __('Bumbal shipping time', 'bumbal'),
            'custom_attributes' => ['min' => 0],
            'type' => 'number',
            'style' => 'width: 100px;',
            'desc_tip' => true,
            'description' => __('The total days that are showed here determines the earliest delivery time. If 5 is showed here it means that the order is gonne be shipped in 5 days.', 'bumbal'),
        ]);
    }

    /**
	 * Save the shipping time field from public function bumbal_add_product_field()
     * 
	 * @since    1.0.0
	 */
    public function bumbal_add_product_field_save($product) 
	{
        if(isset($_POST['bumbal_shipping_time'])) {
            $product->update_meta_data('bumbal_shipping_time', sanitize_text_field($_POST['bumbal_shipping_time']));
        }
    }


    /**
	 * Show Bumbal status information on the order page
	 *
	 * @since    1.0.0
	 */
	public function bumbal_show_status($order) 
	{
        // Only show information if the order is send to Bumbal
        if($order->get_meta('BumbalAPIresponse')) {
            $oBumbal = json_decode($order->get_meta('BumbalAPIresponse'));

            if($order->get_meta('BumbalStatus')) {
                $iBumbalStatus = $order->get_meta('BumbalStatus');
                $sBumbalStatus = $this->bumbal_get_activity_string($iBumbalStatus);
            }
            if($order->get_meta('BumbalRoute')) {
                $sRoute = $order->get_meta('BumbalRoute');
            }

            require_once 'partials/bumbal-show-status.php';
        }
    }

	/**
	 * Show 'Send to Bumbal' on order page as action
	 * @param array $actions
	 * @return array 
	 * 
	 * @since 	1.0.0
	 */
	public function bumbal_send_order_again_action(array $actions) : array 
	{
		$actions['bumbal'] = __('Send to Bumbal', 'bumbal');

		return $actions;
	}

    /**
	 * Add a Bumbal Error status to the status options
	 *
	 * @since    1.0.0
	 */
    public function bumbal_add_error_status($order_statuses) 
	{
        $order_statuses['wc-bumbal-error'] = _x( 'Bumbal error', 'Order Status', '' );
        return $order_statuses;
    }

    /**
	 * Register the error status
	 *
	 * @since    1.0.0
	 */
    public function bumbal_register_error_status() 
	{
        register_post_status( 'wc-bumbal-error', array(
            'label'                     => 'Bumbal',
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop( 'Bumbal error %s', 'Bumbal error %s' )
        ));
    }
    
	/**
	 * Returns string format of activity type
	 * @param integer $status_id
	 * @return string
	 *
	 * @since    1.0.0
	 */
	private function bumbal_get_activity_string($status_id) 
	{
		$mapping = [
			3 => __('Activity planned', 'bumbal'),
			4 => __('Activity in progress', 'bumbal'),
			9 => __('Activity executed', 'bumbal'),
			20 => __('Activity incomplete', 'bumbal'),
			21 => __('Activity new', 'bumbal'),
			22 => __('Activity accepted', 'bumbal'),
			28 => __('Activity cancelled', 'bumbal'),
			39 => __('Activity awaiting', 'bumbal'),
		];

		if(array_key_exists($status_id, $mapping)) {
			return $mapping[$status_id];
		}
	}
}

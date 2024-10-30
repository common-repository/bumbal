<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       http://www.bumbal.eu
 * @since      1.0.0
 *
 * @package    Bumbal
 * @subpackage Bumbal/public
 */

/**
 * @package    Bumbal
 * @subpackage Bumbal/public
 * @author     Bumbal <it@bumbal.eu>
 */
class Bumbal_Public 
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
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) 
	{

		$this->plugin_name = $plugin_name;
		$this->version = $version;

		// Instansiate helper class with all our static functions
		$this->helper = include_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-bumbal-helper.php';
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() 
	{
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/bumbal-public.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() 
	{
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/bumbal-public.js', array( 'jquery' ), $this->version, true );

		wp_localize_script($this->plugin_name, 'bumbal_ajax_object', [
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('bumbal-ajax-nonce'),
		]);
	}

	/**
	 * AJAX handler 
	 * API-call to Bumbal for processing the preffered timeslot
	 * 
	 * @return void
	 *
	 * @since    1.0.0
	 */
	public function bumbal_set_time_slot_ajax() 
	{
		// Check if nonce is valid
		if(!wp_verify_nonce($_POST['bumbal_ajax_nonce'], 'bumbal_set_time_slot_ajax')) {
			wp_die('Not possible');
		}

		//$_POST['timeslot_to_'.$_POST['timeslot'][0]];

		// Get order object
        $oOrder = wc_get_order((int)sanitize_key($_POST['order']));
		if(!$oOrder) {
			wp_die('Can not find order in WooCommerce');
		}
		// Check if keys are the same
		if(sanitize_key($_POST['key']) != sanitize_key($oOrder->get_order_key())) {
			wp_die('Order key not valid');
		}

		// Send body to Bumbal API
		$response = $this->helper::bumbal_send_api_call(
						   'planner/auto_plan', 
						   $this->helper::create_autoplan_body(sanitize_key($_POST['timeslot'][0]), sanitize_key($_POST['bumbal'])));

		if(is_wp_error($response)) {
			_e('Can not connect to Bumbal API', 'bumbal');
			$oOrder->add_order_note(sprintf(__('Failed to book timeslot. Wordpress error: %s.', 'bumbal'), $response->get_error_message()));
			wp_die();
		}
		else {
			// Save response in meta data
			update_post_meta(sanitize_key($_POST['order']), 'BumbalTimeSlotResponse', $response['body']);

			$oCallback = json_decode($response['body']);

			if((int)$oCallback->code === 200) {
				// In the future these meta data updates are handled by the webhook
				// Update BumbalStatus meta data
				update_post_meta(sanitize_key($_POST['order']), 'BumbalStatus', 3);
				// Save bumbal route
				update_post_meta(sanitize_key($_POST['order']), 'BumbalRoute', $oCallback->additional_data->affected_activities[0]->route_nr);

				if(empty(get_option('bumbal_timeslot_thankyou_text'))) {
					_e('Thank you, we have booked your timeslot.', 'bumbal');
				}
				else {
                    echo esc_textarea(trim(get_option('bumbal_timeslot_thankyou_text')));
				}

				$oOrder->add_order_note(__('Timeslot succesfully booked by client.', 'bumbal'));
			}
			else {
				_e('Sorry, we where not able to book your timeslot.', 'bumbal');
				$oOrder->add_order_note(sprintf(__('Failed to book timeslot. API responded with %s. Check BumbalTimeSlotResponse meta data.', 'bumbal'), $oCallback->code));
			}
			wp_die();
		}
	}

	/**
	 * API-call to Bumbal for retrieving available time slots
	 * 
	 * @param integer $order_id
	 * @return object timeslots
	 * @since    1.0.0
	 */
	private function bumbal_retrieve_time_slot($bumbal_id)
	{
		$response = $this->helper::bumbal_send_api_call('planner/check-availability', $this->helper::create_timeslot_body($bumbal_id));

		if(is_wp_error($response)) {
			return $response;
		}
		else {
			return json_decode($response['body']);
		}
	}

	/**
	 * Get available timeslots from Bumbal
	 * @param integer $order_id
	 * @return void
	 *
	 * @since    1.0.0
	 */
	public function bumbal_get_time_slots($order_id) 
	{
		if(get_option('bumbal_timeslots_form') != "yes") {
			return;
		}

		$order = new WC_order($order_id);

		if($order->get_meta('BumbalAPIresponse')) {
			// Get Bumbal ID from meta data
			$oBumbal = json_decode($order->get_meta('BumbalAPIresponse'));

			if($oBumbal->code === 200) {
				$sBumbalId = $oBumbal->additional_data->activity_info->id;

				// Retrieve activity
				$aActivityResponse = $this->bumbal_retrieve_activity($sBumbalId);

				if(is_wp_error($aActivityResponse)) {
					$order->add_order_note(sprintf(__('Failed to cancel order. Wordpress error: %s.', 'bumbal'), $aActivityResponse->get_error_message()));
					_e('Can not connect to Bumbal API', 'bumbal');
				}
				elseif((int)$aActivityResponse['response']['code'] !== 200) {
					// Create object from body
					$oActivity = json_decode($aActivityResponse['body']);
					// Error handling when we get something else than 200 back from API					
					printf(__('Error code (%s) when retrieving timeslots.', 'bumbal'), $oActivity->code);
					$order->add_order_note(sprintf(__('Failed to retrieve timeslot, API responded with: %s. 
														Check BumbalRetrieveTimeSlotError meta data.', 'bumbal'), $oActivity->code));
					update_post_meta($order_id, 'BumbalRetrieveTimeSlotError', '( '.$oActivity->code.') '.$oActivity->message);
				}
				else {
					// Create object from body
					$oActivity = json_decode($aActivityResponse['body']);
					
					// Check if timeslot is set
					if($oActivity->items[0]->date_time_from) {
						_e('Thank you, we already received your preferred timeslot.', 'bumbal');
					}
					else {
						// Timeslot is not yet set, so we retrieve these
						$oTimeSlotResponse = $this->bumbal_retrieve_time_slot($sBumbalId, $order);

						if(is_wp_error($oTimeSlotResponse)) {
							_e('Can not connect to Bumbal API', 'bumbal');
							$order->add_order_note(sprintf(__('Failed to book timeslot. Wordpress error: %s.', 'bumbal'), $oTimeSlotResponse->get_error_message()));
						}
						elseif((int)$oTimeSlotResponse->code === 200){
							// Timslots are retrieved so we can delete this error
							delete_post_meta($order_id, 'BumbalRetrieveTimeSlotError');

							$aTimeSlots = $oTimeSlotResponse->additional_data->available_timewindows;
							// Parse time slots
							require_once 'partials/bumbal-public-time-slot.php';
						}
						else {
							printf(__('Can not retrieve timeslots: %s.', 'bumbal'), $oTimeSlotResponse->message);
							$order->add_order_note(sprintf(__('Failed to retrieve timeslot, API responded with: %s. 
															Check BumbalRetrieveTimeSlotError meta data.', 'bumbal'), $oTimeSlotResponse->code));
							update_post_meta($order_id, 'BumbalRetrieveTimeSlotError', '( '.$oTimeSlotResponse->code.') '.$oTimeSlotResponse->message);
						}
					}	
				}
			}
		}
	}

	/**
	 * Send order package to Bumbal
	 * @param integer $order_id
	 * @return void
	 *
	 * @since    1.0.0
	 */
	public function bumbal_set_activity($order_id) 
	{
		$isMultiDay = (get_option('bumbal_multiday_active') == 'yes') ? True : False;

        $body = apply_filters('bumbal_convert', $order_id);

		// If multi-day is active we need to update some information
		if($isMultiDay && !empty($body)){
			$body = $this->helper::update_to_multi_day($body, 1);
		}

		// Bumbal_convert returns false if the order doesn't need to go to Bumbal
		if($body && !empty($body)) {
			// Set endpoint
			$sBumbalEndpoint = 'activity/set';

			// Send body to Bumbal API
			$response = $this->helper::bumbal_send_api_call($sBumbalEndpoint, $body);

			// Get order
			$oOrder = wc_get_order($order_id);

			// If an WP_error is returned
			if(is_wp_error($response)){
				// We only give this error once. If client decides to set the status by hand we're not gonna trigger the errors again.
				if(!$oOrder->get_meta('BumbalApiError')) {
					$this->bumbal_connect_error($oOrder, 'update', 'BumbalApiError', $response->get_error_message());
				}
			}
			else {
				// // Store response in MetaData
				update_post_meta($order_id, 'BumbalAPIresponse', $response['body']);
				update_post_meta($order_id, 'BumbalAPIcall', json_encode($body));	
				
				// Create object from JSON response
				$oCallback = json_decode($response['body']);

				if((int)$oCallback->code === 200) {
					// If we have a BumbalApiError in the meta_data we delete this piece of data
					// This is because the order is send again and we didn't get a BumbalApiError
					if($oOrder->get_meta('BumbalApiError')) {
						delete_post_meta($order_id, 'BumbalApiError');
						
						// Send invite message if the option for sending an invite message is on
						if(get_option('bumbal_send_invite_after_error') == 'yes') {
							$aPayload = $this->helper->create_invite_body($oCallback->additional_data->activity_info->id);
							$this->helper::bumbal_send_api_call('communication/trigger-message', $aPayload);
							$oOrder->add_order_note(__('Invite message triggered.', 'bumbal'));
						}
					}
					// Update notes and meta
					update_post_meta($order_id, 'BumbalStatus', 22);
					$sNote = sprintf(__('Order successfully send to Bumbal (%s)', 'bumbal'), $oCallback->additional_data->activity_info->nr);
					$oOrder->add_order_note($sNote);

					// If the option for multi-day is on we create a new activity as multi-day
					if($isMultiDay) {
						$aMultiDay = apply_filters('bumbal_convert', $order_id);
						$aMultiDay = $this->helper::update_to_multi_day($aMultiDay, 2);

						// Set activity
						$multiResponse = $this->helper::bumbal_send_api_call('activity/set', $aMultiDay);
						
						// Update meta and notes
						update_post_meta($order_id, 'MultiDayResponse', $multiResponse['body']);
						update_post_meta($order_id, 'MultiDayCall', json_encode($aMultiDay));
						$sNote = sprintf(__('Multiday order send to Bumbal (%s)', 'bumbal'), json_decode($multiResponse['body'])->additional_data->activity_info->nr);
						$oOrder->add_order_note($sNote);
					}
				}
				else {
					// Set status to failed
					$oOrder->update_status('wc-bumbal-error', 
					__('Bumbal API responded with failed. Check BumbalAPIresponse meta data.', 'bumbal'));
				}
			}

		}
	}

    /**
     * Cancel order in Bumbal
     * @param integer $order_id
     * @return void
     *
     */
	public function bumbal_cancel_activity($order_id)
    {
		// Get order
		$oOrder = wc_get_order($order_id);

		// If the order can not be found we stop the delete action because it's propably not a order
		if(!$oOrder) {
			return;
		}

		 // Send body to Bumbal API
        $response = $this->helper::bumbal_send_api_call('activity/set', $this->helper::create_cancel_body($order_id));

        // Returns false if there is an error
        if(is_wp_error($response)){
            // Set order status to Bumbal error and set status to failed
            $this->bumbal_connect_error($oOrder, 'cancel', 'BumbalCancelOrderError', $response->get_error_message());
        }
        else {
            $oCallback = json_decode($response['body']);

            if((int)$oCallback->code === 200) {
                // If we have a BumbalApiError in the meta_data we delete this piece of data
                if($oOrder->get_meta('BumbalCancelOrderError')) {
                    delete_post_meta($order_id, 'BumbalCancelOrderError');
                }

				// Cancel multi-day activity if present
				if($oOrder->get_meta('MultiDayResponse')) {
					$oMultiActivity = $this->helper->return_activity_from_metadata($oOrder, 'MultiDayResponse');
					$aBody = $this->helper::create_cancel_body($order_id);
					// Change the link id in body for multi day activity
					$aBody['links'][0]['provider_name'] = $aBody['links'][0]['provider_name'].'-multi-'.$oMultiActivity->assignment_sequence_nr;
					$this->helper::bumbal_send_api_call('activity/set', $aBody);
				}

                // Update notes and meta
                update_post_meta($order_id, 'BumbalStatus', 28);
                $sNote = sprintf(__('Order is cancelled in Bumbal', 'bumbal'));
                $oOrder->add_order_note($sNote);
            }
            else {
                // Set status to failed
                $oOrder->update_status('wc-bumbal-error',
                    __('Bumbal API responded with failed. Check BumbalCancelOrderError meta data.', 'bumbal'));

                update_post_meta($order_id, 'BumbalCancelOrderError', $response['body']);
            }
        }
    }

    /**
     * Delete order in Bumbal
     * @param integer
     * @return void
     */
    public function bumbal_delete_activity($order_id) 
	{
        // Get order
        $oOrder = wc_get_order($order_id);

		// If the order can not be found we stop the delete action in this plug-in. It's propably something else.
		if(!$oOrder) {
			return;
		}

        //Get activity ID
        $iActivityId = $this->helper::get_activity_from_metadata($oOrder);

        //When an activity is found, it can be deleted
        if ($iActivityId) {
            $response = $this->helper::bumbal_send_api_call('activity/' . $iActivityId, null, 'DELETE');

            // Returns false if there is an error
            if(is_wp_error($response)){
                // Set order status to Bumbal error and set status to failed
                $this->bumbal_connect_error($oOrder, 'delete', 'BumbalDeleteOrderError', $response->get_error_message());
            }
            else {
                $oCallback = json_decode($response['body']);

                if((int)$oCallback->code === 200) {
                    // If we have a BumbalApiError in the meta_data we delete this piece of data
                    if($oOrder->get_meta('BumbalDeleteOrderError')) {
                        delete_post_meta($order_id, 'BumbalDeleteOrderError');
                    }

					// Delete multi-day activity if present
					if($oOrder->get_meta('MultiDayResponse')) {
						$iMulti = $this->helper::get_activity_from_metadata($oOrder, 'MultiDayResponse');
						// Delete activity
						$response = $this->helper::bumbal_send_api_call('activity/' . $iMulti, null, 'DELETE');
					}

                    // Update notes and meta - activity in Bumbal is set to cancelled when it is deleted
                    update_post_meta($order_id, 'BumbalStatus', 28);
                    $sNote = sprintf(__('Order is deleted in Bumbal', 'bumbal'));
                    $oOrder->add_order_note($sNote);
                }
                else {
                    // Set status to failed
                    $oOrder->update_status('wc-bumbal-error',
                        __('Bumbal API responded with failed. Check BumbalDeleteOrderError meta data.', 'bumbal'));

                    update_post_meta($order_id, 'BumbalDeleteOrderError', $response['body']);
                }
            }
        }
    }

    /**
     * Updates the post meta to the Bumbal connect error message and sets message to failed. The same error occurs for delete, cancel and update.
     * @param $oOrder
     * @param $sAction
     * @param $sErrorfield
	 * @param $message
     */
    private function bumbal_connect_error($oOrder, $sAction, $sErrorfield, $message) 
	{
        // Set order status to Bumbal error
        update_post_meta($oOrder->get_id(), $sErrorfield, $message);

        $sActionTranslation = null;
        switch ($sAction) {
            case 'delete':
                $sActionTranslation = __('delete', 'bumbal');
                break;
            case 'cancel':
                $sActionTranslation = __('cancel', 'bumbal');
                 break;
            case 'update':
                $sActionTranslation = __('update', 'bumbal');
				break;
			default:
				$sActionTranslation = __('set', 'bumbal');
			
        }

        // Set status to failed
        $oOrder->update_status('wc-bumbal-error', sprintf(__('Could not %s order in Bumbal. Check %s in meta_data.', 'bumbal'), $sActionTranslation, $sErrorfield));
    }

	/**
	 * Handler for 'Send to Bumbal' on order page as action
	 * @param WC_Order $order
	 * 
	 * @since 	1.0.0.
	 */
	public function bumbal_update_order($oOrder) 
	{
		$this->bumbal_set_activity($oOrder->get_id());
	}

	/**
	 * Retrieve bumbal activity
	 * 
	 * @param integer $id Bumbal intern ID
	 * @return object
	 *
	 * @since    1.0.0
	 */
	private function bumbal_retrieve_activity($id) 
	{
		return $this->helper::bumbal_send_api_call('activity', $this->helper::create_retrieve_body($id), 'PUT');
	}

	/**
	 * Creates endpoint for incoming webhooks
	 *
	 * @since    1.0.0
	 */
	public function bumbal_create_endpoint() 
	{
		register_rest_route( 'bumbal/v1', '/activity', array(
			// By using this constant we ensure that when the WP_REST_Server changes our readable endpoints will work as intended.
			'methods'  => WP_REST_Server::ALLMETHODS,
			// Here we register our callback. The callback is fired when this endpoint is matched by the WP_REST_Server class.
			'callback' => [$this, 'bumbal_set_activity_status'],
			// We set the permission for the callback public
			'permission_callback' => '__return_true'
		) );
	}

	/**
	 * Processes the incoming webhook and set the status id
	 *
	 * @since    1.0.0
	 */
	public function bumbal_set_activity_status() 
	{
		// Check if we have input
		if(!isset($_GET['target_id']) && (!isset($_POST['target_id']))) {
			return $this->helper::bumbal_rest_response('bumbal_no_activity_fail',
											   'No Bumbal ID found.',
											   	400);
		}

		// We accept GET and POST if we would like to trigger webhooks manually 
		if(isset($_GET['target_id']) && is_numeric($_GET['target_id'])) {
			$mTarget = sanitize_key($_GET['target_id']);
		}
		if(isset($_POST['target_id']) && is_numeric($_POST['target_id'])) {
			$mTarget = sanitize_key($_POST['target_id']);
		}

		// Bumbal webhook system send target_id as an array
		if(is_array($mTarget)) {
			foreach($mTarget as $iTarget) {
				// Validate GET-value
				if(!filter_var($iTarget, FILTER_VALIDATE_INT)) {
					return $this->helper::bumbal_rest_response('bumbal_activity_not_all_valid',
														'Bumbal ID is not valid.',
															400);
				}
				else {
					$mTarget = $iTarget;
				}
			}
		} 
		else {
			// Validate GET-value
			if(!filter_var($mTarget, FILTER_VALIDATE_INT)) {
				return $this->helper::bumbal_rest_response('bumbal_activity_not_valid',
													'Bumbal ID is not valid.',
														400);
			}
		}

		// Retrieve activity
		$aActivityResponse = $this->bumbal_retrieve_activity($mTarget);

		// If there is an Wordpress order returned from the API-call
		if(is_wp_error($aActivityResponse)) {
			return $this->helper::bumbal_rest_response('bumbal_no_api_connection',
												'Unable to connect to Bumbal API',
												400);
		}

		$oActivity = json_decode($aActivityResponse['body']);

		if($aActivityResponse['response']['code'] !== 200) {
			return $this->helper::bumbal_rest_response('bumbal_returns_api_error',
												'Bumbal API returned '.$aActivityResponse['response']['code'],
												400);
		}

		// Check if there is an activity in Bumbal
		if(count($oActivity->items) == 0) {
			return $this->helper::bumbal_rest_response('bumbal_retrieve_activity_fail',
											   'Can not find Bumbal activity with this ID',
											   400);
		}
		// Sanatize variable name
		$oActivity = $oActivity->items[0];

		// Retrieve order ID from API response
		$iOrderId = $this->helper->retrieve_order_id($oActivity);
		if(!$iOrderId) {
			return $this->helper::bumbal_rest_response('bumbal_order_id_fail',
											   'Can not find WooCommerce order ID in Bumbal',
											   400);
		}

		// Check if we can find this order in WooCommerce
		if(!wc_get_order($iOrderId)) {
			return $this->helper::bumbal_rest_response('bumbal_woocommerce_id_fail',
												'Can not find WooCommerce order ID',
												400);
		}

		// Check if we have a status id
		if($oActivity->status_id) {	
			// Update meta data
			update_post_meta($iOrderId, 'BumbalStatus', $oActivity->status_id);

			if(get_option('bumbal_status_change')) {
				// If activity is finished (=9) we change the status
				if($oActivity->status_id == 9) {
					$oOrder = wc_get_order($iOrderId);
					$oOrder->update_status('wc-completed',
										   __('Orders has been completed by Bumbal.', 'bumbal'));
				}
			}

		}

		// Check if we have route number
		if($oActivity->route_nr) {
			// Update route meta data
			update_post_meta($iOrderId, 'BumbalRoute', $oActivity->route_nr);
		}

		// Return succes
		return $this->helper::bumbal_rest_response('bumbal_activity_updated',
										   'WooCommerce order succesfull updated',
										   200);
	}

	/**
	 * Create activity body message. Can be overwritten by other plug-ins.
	 * 
	 * @param integer $order_id 
	 * @return array
	 *
	 * @since    1.0.0
	 */
	public static function bumbal_convert($order_id) 
	{
        $order = new WC_order($order_id);

		// Extract shipping instance ID
		$mShipping = @array_shift($order->get_shipping_methods());
		if($mShipping) {
			$iShippingInstanceID = $mShipping->get_instance_id();

			// Check if this order has the right shipping instance ID for sending to Bumbal
			if(get_option('bumbal_shipping_instance_'.$iShippingInstanceID) == 'no'){
				return NULL;
			}
		}

        // Create array for storing packagelines
        $aPackageLines = array();
		$aShippingClasses = array();
		$sBumbalPackageline = '';

		// Storage for days ahead
		$iDaysAhead = 0;

        // Loop through items
        foreach($order->get_items() as $item) {
            // Get product
            $oProduct = $item->get_product();

			if(is_a($oProduct, 'WC_Product_Variation')) {
				$iProductID = $oProduct->get_parent_id();
			}
			else {
				$iProductID = $oProduct->get_id();
			}

			// Update days ahead if it's greater than we already have
			if(get_post_meta($iProductID, 'bumbal_shipping_time', True) > $iDaysAhead) {
				$iDaysAhead = get_post_meta($iProductID, 'bumbal_shipping_time', True);
			}

			$aShippingClasses[] = $oProduct->get_shipping_class_id();

			// Create packageline
            $aPackageLine = [
                "nr" => (empty(trim($oProduct->get_sku()))) ? $oProduct->get_id() : $oProduct->get_sku(),
                "nr_of_packages" => $item->get_quantity(),
                "action_type_name" => "outbound",
                "description" => $oProduct->get_name(),
            ];

			// Create capacity if exists
			if($oProduct->get_weight() || $oProduct->get_length() || $oProduct->get_width() || $oProduct->get_height()) {
				// Create volume if exists
				if($oProduct->get_length() && $oProduct->get_width() && $oProduct->get_height()) {
					$volume = [
						"capacity_type_name" => "volume",
						"capacity_value_uom_name" => "cm3",
						"unit_values_uom_name" => "cm",
						"unit_values" => [
							[
								"name" => "length",
								"value" => $oProduct->get_length(),
							],
							[	
								"name" => "width",
								"value" => $oProduct->get_width(),
							],
							[
								"name" => "height",
								"value" => $oProduct->get_height()
							]
						]
					];

					$aPackageLine['capacities'][] = $volume;
				}
				// Create weight if exists
				if($oProduct->get_weight()) {
					$weight = [
                        "capacity_type_name" => "weight",
                        "capacity_value" => ($oProduct->get_weight() * $item->get_quantity()),
                        "capacity_value_uom_name" => "kg"
					];

					$aPackageLine['capacities'][] = $weight;
				}				
			}
			
			// We store product variation information in the meta_data			
			if($item->get_variation_id()) {
				$oVariation = new WC_Product_Variation($item->get_variation_id());
				$sProductVariation = $oVariation->get_attribute_summary();
				$aPackageLine['meta_data'] = [
					[
						"name" => "Product variation",
						"value" => $sProductVariation,
					]
				];

				// Concatenate string for sending with driver notes
				$sBumbalPackageline .= $item->get_quantity().'x '.$oProduct->get_name().' '.$sProductVariation.' ~ ';
			}
			else {
				// Concatenate string for sending with driver notes
				$sBumbalPackageline .= $item->get_quantity().'x '.$oProduct->get_name().' ~ ';
			}

			// Put packageline in Packagelines array
			$aPackageLines[] = $aPackageLine;
        }   

		// If the shippingclasses are ignored, we don't have to check if there are any packagelines 
		// !== 'yes => false 
		if(get_option('bumbal_ignore_shippingclass') !== 'yes') {
			// We check the shipping classes
			$aYes = array();
			foreach($aShippingClasses as $iShippingClass) {
				if(get_option('bumbal_shipping_class_'.$iShippingClass) == 'yes') {
					// There is a packageline who can be send to Bumbal
					$aYes[] = $iShippingClass;
				}
			}

			if(count($aYes) == 0) {
				return NULL; 
			} 
		}	

        // Create body message
        $body = [
			"activity_type_id" => 2,
            "links" => [
                [
                    "link_id" => $order->get_id(),
                    "provider_name" => "WooCommerce"
                ]
            ],
            "reference" => 'WC '.$order->get_order_number(),
			"earliest_delivery_date" => date('Y/m/d', strtotime('+'.$iDaysAhead.' days')),
            "address" => [
                "name_1" => $order->get_billing_first_name(),
                "name_2" => $order->get_billing_last_name(),
                "emails" => [
                    [
                        "email" => $order->get_billing_email(),
                        "primary" => true
                    ]
                ],
                "phone_nrs" => [
					[
						"nr" => $order->get_billing_phone(),
						"primary" => True
					]
				],
                "street_1" => $order->get_shipping_address_1(),
				"street_2" => $order->get_shipping_address_2(),
                "zipcode" => $order->get_shipping_postcode(),
                "city" => $order->get_shipping_city(),
                "iso_country" => $order->get_shipping_country(),
				"contact_person" => $order->get_formatted_billing_full_name(),
            ],
			"meta_data" => [
				[
					"name" => "token",
					"value" => $order->get_order_key(),
				],
				[
					"name" => "WC_id",
					"value" => $order->get_id(),
				],
				[
					"name" => "WC_order",
					"value" => $order->get_order_number(),
				]				
			],
            "communication" => [
                "saywhen" => false,
                "bumbal" => false,
                "send_invite" => (get_option('bumbal_send_invite') == 'yes') ? true : false,
                "send_reminder" => (get_option('bumbal_send_reminder') == 'yes') ? true : false,
                "send_pref_confirmation" => (get_option('bumbal_send_confirmation') == 'yes') ? true : false,
                "send_planned" => (get_option('bumbal_send_planned') == 'yes') ? true : false,
                "send_eta" => (get_option('bumbal_send_eta') == 'yes') ? true : false,
                "send_executed" => (get_option('bumbal_send_executed') == 'yes') ? true : false,
                "send_cancelled" => (get_option('bumbal_send_cancelled') == 'yes') ? true : false,
                "email" => $order->get_billing_email(),
                "phone_nr" => $order->get_billing_phone(),
            ],
            "package_lines" => $aPackageLines,
        ];

		// Check if we have shopname defined in the Bumbal configuration
		if(!empty(trim(get_option('bumbal_shop_name')))) {
			$body['links'][0]['provider_reference'] = get_option('bumbal_shop_name');
		}

		// When it needs to be shipped to a company we only send the company details
		if(!empty($order->get_shipping_company())) {
			$body['address']['name_1'] = $order->get_shipping_company();
			unset($body['address']['name_2']);
		}

		if(get_option('bumbal_send_packagelines_as_notes') == 'yes') {
			$body['notes'][] = [
				"note_category_id" => 1,
				"title" => "packagelines",
				"content" => $sBumbalPackageline
			];
		}

		// Check if there are notes
		if($order->get_customer_note()) {
			$body["notes"][] = [
					"note_category_id" => 1,
					"title" => "customer notes",
					"content" => $order->get_customer_note(),
			];
		}

        return $body;
	}
}

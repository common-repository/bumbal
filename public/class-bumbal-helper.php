<?php
/**
 * Bumbal helper class
 */
class Bumbal_Helper 
{
	/**
	 * Creates body for retrieving possible timeslots from Bumbal
	 * @param int $bumbal_id
	 * @return array
	 */
    public static function create_timeslot_body(int $bumbal_id) : array 
	{
        return [
            'data' => [
				'activity' => [
					'id' => $bumbal_id,
				],
			],
			'options' => [
				'synchronous' => True,
				'include_proposed_plan_times' => True,
				'apply_cut_off_times' => True,
			],
			'filters' => [
				'max_nr_of_days_with_availability' => 5
			]  
        ];
    }

    /**
	 * Returns the payload for trigging a send_invite message on a bumbal ID
	 * @param integer $id               bumbal id
	 * @return array
	 *
	 * @since    1.0.0
	 */
    public static function create_invite_body($iID) : array 
	{
        return [
			'activityId' => $iID,
			'messageType' => 'send_invite',
			'checkPrefrence' => False,
		];
    }

    /**
	 * Returns WooCommerce order id from bumbal activity object
	 * @param object $oActivity
	 * @return integer
	 *
	 * @since    1.0.0
	 */
	public static function retrieve_order_id($oActivity) 
	{
		// Loop through meta data
		foreach($oActivity->meta_data as $oMetaData) {
			if($oMetaData->name == 'WC_id') {
				return $oMetaData->value;
			}
		}
		return false;
	}

    /**
	 * Returns JSON response on Bumbal WooCommerce endpoint
	 * @param string $code
	 * @param string $message
	 * @param integer $status
	 * @return WP_REST_response
	 *
	 * @since    1.0.0
	 */
	public static function bumbal_rest_response($code, $message, $status) : WP_REST_response
	{
		return new WP_REST_response([
			'code' => $code,
			'message' => $message,
			'data' => [
				'status' => $status,
			]
		]);
	}

    /**
     * Get Bumbal ID from meta-data
     * @param $oOrder
     * @param string $sFieldName
     * @return string Activity ID
     */
    public static function get_activity_from_metadata($oOrder, $sFieldName = 'BumbalAPIresponse') 
	{
        $sMessage = json_decode($oOrder->get_meta($sFieldName));
        return $sMessage->additional_data->activity_info->id;
    }

	/**
	 * Returns full activity information from meta data
	 * @param $oOrder
	 * @param string $sFieldName
	 * @return array
	 */
	public static function return_activity_from_metadata($oOrder, $sFieldName = 'BumbalAPIresponse') 
	{
		return json_decode($oOrder->get_meta($sFieldName))->additional_data->activity_info;
	}

    /**
     * Cancel order in Bumbal
     * @param integer $iOrderId
     * @return array
     */
    public static function create_cancel_body($iOrderId) : array 
	{
        $body = [
            'status_id' => 28,
            'links' => [
                [
                    'link_id' => $iOrderId,
                    'provider_name' => 'WooCommerce'
                ]
            ] 
        ];

		// Check if we have shopname defined in the Bumbal configuration
		if(!empty(trim(get_option('bumbal_shop_name')))) {
			// We set a provider reference to handle multiple WooCommerce shops on 1 Bumbal environment
			$body['links'][0]['provider_reference'] = get_option('bumbal_shop_name');
		}

		return $body;
    }

    /**
     * Create retrieve activity body
     * @param integer $iID               Bumbal ID
     * @return array
     */
    public static function create_retrieve_body($iID) : array 
	{
        return [
            'filters' => [
				'id' => [$iID],
			],
			'options' => [
				'include_meta_data' => True,
				'include_route_info' => True,
			],
			'offset' => 0, 
        ];
    }

    /**
     * Create auto-plan body
     * @param string $key               Availability key
     * @param integer $id               Bumbal ID to be planned.
     */
    public static function create_autoplan_body($key, $id) {
        return [
			'data' => [
				'availability_key' => $key,
			],
			'options' => [
				'synchronous' => True,
				'respond_after_apply_planning' => False,
			],
			'filters' =>[
				'activity' => [
					'id' => $id,
				]
			]
        ];
    }

    /**
	 * Send api call to Bumbal
	 * @param string $url
	 * @param array $body
	 * @param string $method
	 * @return object
	 *
	 * @since    1.0.0
	 */
    public static function bumbal_send_api_call($endpoint, $body, $method = 'POST') {
		return wp_remote_post(self::get_bumbal_url() . $endpoint, [
			'headers' => [
				'Accept' => 'application/json',
				'content-Type' => 'application/json',
				'ApiKey' => get_option('bumbal_apikey'),
			],
			'method' => $method,
			'body' => json_encode($body),
			'timeout' => 45,
		]);
	}

    /**
     * Returns the URL that should be used for API calls. When the Bumbal instance field has a domain, we use it. If not, we add 'freightlive.eu'
     * @return string				URL as a string
     */
    private static function get_bumbal_url() {
        if(strpos(get_option('bumbal_instance'), '.')) {
            return 'https://'.get_option('bumbal_instance').'/api/v2/'; 
        } else {
            return 'https://'.get_option('bumbal_instance').'.freightlive.eu/api/v2/'; 
        } 
    }

	/**
	 * Updates an activity body message to an multi-day activity
	 * @param array $aBody					Body message for activity/set 
	 * @param int $iSequence				Sequence in the assignment
	 */
	public static function update_to_multi_day($aBody, $iSequence) {
		$aBody['assignment'] = [
			'id' => $aBody['links'][0]['link_id'],
			'multi_day' => True
		];
		$aBody['assignment_sequence_nr'] = $iSequence;

		// If the sequence number is larger than 1 we know that it's a follow-up activity
		if($iSequence > 1) {
			$aBody['links'][0]['provider_name'] = $aBody['links'][0]['provider_name'].'-multi-'.$iSequence;
			$aBody['activity_type_id'] = 1;
		
			// Add the timeslots +1 day
			if(get_option('bumbal_timeslots_form') !== 'yes') {
				if(isset($aBody['earliest_delivery_date_time'])) {
					$aBody['earliest_delivery_date_time'] = date('Y-m-d', strtotime($aBody['earliest_delivery_date_time'].' +1 days')).' '.self::check_timeslot(get_option('bumbal_multiday_start_time'), '09:00');
				}
				if(isset($aBody['latest_delivery_date_time'])) {
					$aBody['latest_delivery_date_time'] = date('Y-m-d', strtotime($aBody['latest_delivery_date_time'].' +1 days')).' '.self::check_timeslot(get_option('bumbal_multiday_end_time'), '18:00');	
				}
				if(isset($aBody['earliest_delivery_date'])) {
					$aBody['earliest_delivery_date'] = date('Y-m-d', strtotime($aBody['earliest_delivery_date'].' +1 days'));	
				}

				// Set timeslots if present
				if(isset($aBody['earliest_delivery_date_time']) && isset($aBody['latest_delivery_date_time'])) {
					$aBody['time_slots'] = [
						[
							'date_time_from' => $aBody['earliest_delivery_date_time'],
							'date_time_to' => $aBody['latest_delivery_date_time']
						]
					];
				}
			}

			// Update communcation settings based on the config
			$aBody['communication'] = [
				'saywhen' => false,
                'bumbal' => false,
                'send_invite' => (get_option('bumbal_multiday_send_invite') == 'yes') ? true : false,
                'send_reminder' => (get_option('bumbal_multiday_send_reminder') == 'yes') ? true : false,
                'send_pref_confirmation' => (get_option('bumbal_multiday_send_confirmation') == 'yes') ? true : false,
                'send_planned' => (get_option('bumbal_multiday_send_planned') == 'yes') ? true : false,
                'send_eta' => (get_option('bumbal_multiday_send_eta') == 'yes') ? true : false,
                'send_executed' => (get_option('bumbal_multiday_send_executed') == 'yes') ? true : false,
                'send_cancelled' => (get_option('bumbal_multiday_send_cancelled') == 'yes') ? true : false,
                'email' => $aBody['communication']['email'],
                'phone_nr' => $aBody['communication']['phone_nr'],
			];

		}
		
		return $aBody;
	}

	/**
	 * Checks if timeslot is valid and returns the value or returns the default value
	 * @param string $value				Value to be checked
	 * @param string $default			Default value to be returned if validations fails
	 * @return string
	 */
	private static function check_timeslot($value, $default) {
		if(preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/', $value)) {
			return $value;
		}
		else {
			return $default;
		}
	}
}

return new Bumbal_Helper;
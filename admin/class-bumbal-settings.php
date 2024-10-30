<?php
/**
 * Settings class
 *
 * @since 1.0.0
 */
class Bumbal_WC_Settings extends WC_Settings_Page 
{    
    /**
     * Setup settings class
     *
     * @since  1.0
     */
    public function __construct() 
    {
        $this->id    = 'bumbal';
        $this->label = 'Bumbal';
        
        add_filter( 'woocommerce_settings_tabs_array',        [$this, 'add_settings_page'], 20 );
        add_action( 'woocommerce_settings_' . $this->id,      [$this, 'output']);
        add_action( 'woocommerce_settings_save_' . $this->id, [$this, 'save']);
        add_action( 'woocommerce_sections_' . $this->id,      [$this, 'output_sections']);
    }    
    
    /**
     * Get sections
     *
     * @return array
     */
    public function get_sections() 
    {		
        $sections = [
            'connection' => __( 'Connection', 'bumbal' ),
            'preferences' => __( 'Preferences', 'bumbal' ),
            'communication' => __('Communication', 'bumbal'),
        ];
        
        return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
    }    

    /**
     * Get settings array
     *
     * @since 1.0.0
     * @param string $current_section Optional. Defaults to empty string.
     * @return array of settings
     */
    public function get_settings( $current_section = '' ) 
    {
        switch($current_section) {
            case 'connection':
                $settings = self::connection_settings();
                break;
            case 'preferences':
                $settings = self::preference_settings();
                break;
            case 'communication':
                $settings = self::communication_settings();
                break;
            default:
                $settings = self::connection_settings();
        }		
    
        /**
         * Filter MyPlugin Settings
         *
         * @since 1.0.0
         * @param array $settings Array of the plugin settings
         */
        return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings, $current_section );
    }

    /**
     * Returns configuration settings
     * 
     * @since 1.0.0
     * @return array of settings
     */
    private static function connection_settings() : array 
    {
        $settings = [
            [
                'name' => __('Bumbal connection settings', 'bumbal'),
                'type' => 'title',
                'desc' => __('All the settings for making a connection with Bumbal.', 'bumbal'),
                'id' => 'bumbal_connection',
            ],[
                'name' => __('Instance name', 'bumbal'),
                'type' => 'text',
                'desc' => __('Instance name for Bumbal.', 'bumbal'),
                'desc_tip' => true,
                'id' => 'bumbal_instance',
            ],[
                'name' => __('API-key', 'bumbal'),
                'type' => 'text',
                'desc' => __('API-key for Bumbal.', 'bumbal'),
                'desc_tip' => true,
                'id' => 'bumbal_apikey',
            ],[
                'name' => __('Shop name', 'bumbal'),
                'type' => 'text',
                'desc' => __('When using multiple Woocommerce shops for the same Bumbal environment we need to identify what order is from what shop. WARNING: changing this field could result in duplicate activities in Bumbal, please contact support before doing this.', 'bumbal'),
                'desc_tip' => true,
                'id' => 'bumbal_shop_name',
            ],[
                'name' => __('Status for sending', 'bumbal'),
                'type' => 'select',
                'desc' => __('Select the order status whereby the order is sent to Bumbal.', 'bumbal'),
                'desc_tip' => true,
                'id' => 'bumbal_status_send',
                'options' => [
                    'processing' => __('Processing', 'bumbal'),
                    'completed' => __('Completed', 'bumbal'),
                    'custom' => __('Custom', 'bumbal')
                ]
            ],[
                'name' => __('Custom hook for sending', 'bumbal'),
                'type' => 'text',
                'desc' => __('Only used when \'Custom\' is chosen by \'Status for sending\'', 'bumbal'),
                'desc_tip' => true,
                'id' => 'bumbal_custom_send_activity_hook' 
            ],[
                'type' => 'sectionend',
                'id' => 'bumbal_connection',
            ]
        ];

        // Check if shipping zones are enabled
        if( class_exists( 'WC_Shipping_Zones' ) ) {
            $settings[] = array(
                'name' => __('Bumbal shipping method settings', 'bumbal'),
                'type' => 'title',
                'desc' => __('Select all the shipping methods whereby the order is sent to Bumbal.', 'bumbal'),
                'id' => 'bumbal_shipping_zones',
            );

            // Create empty array where we store all the shipping methods
            $aShippingMethods = [];

            // Loop through shipping zones
            $allZones = WC_Shipping_Zones::get_zones();

            foreach($allZones as $zone) {
                $settings[] = array(
                    'name' => $zone['zone_name'],
                    'type' => 'title',
                    'id' => 'bumbal_shipping_zones_'.$zone['zone_name'],
                );

                foreach($zone['shipping_methods'] as $method) {
                    $settings[] =  array(
                        'name' => $method->title,
                        'type' => 'checkbox',
                        'id' => 'bumbal_shipping_instance_'.$method->instance_id,
                    );
                    // Save method ID in array later to be used by self::bumbal_clean_up_shipping_options
                    $aShippingMethods[$method->instance_id] = $method->title;
                }

                // Section the individual shipping zone off
                $settings[] = array(
                    'type' => 'sectionend',
                    'id' => 'bumbal_shipping_zones_'.$zone['zone_name'],
                ); 
            }

            // Section the shipping zone wrapper off
            $settings[] = array(
                'type' => 'sectionend',
                'id' => 'bumbal_shipping_zones',
            );
        }

        // Shipping classes
        $settings[] = array(
            'name' => __('Bumbal shipping classes settings', 'bumbal'),
            'type' => 'title',
            'desc' => __('Select all the shipping classes whereby the order is sent to Bumbal.', 'bumbal'),
            'id' => 'bumbal_shipping_classes',
        );

        // Get all shipping classes
        $aShippingClasses = get_terms( array('taxonomy' => 'product_shipping_class', 'hide_empty' => false ) );

        // Create empty array where we store all the shipping classes
        $aClasses = [];

        foreach($aShippingClasses as $oShippingClass) {
            $settings[] =  array(
                'type' => 'checkbox',
                'id' => 'bumbal_shipping_class_'.$oShippingClass->term_id,
            );
            // Save shipping classes ID in array later to be used by self::bumbal_clean_up_shipping_options
            $aClasses[$oShippingClass->term_id] = $oShippingClass->name;
        }

        $settings[] = array(
            'type' => 'sectionend',
            'id' => 'bumbal_shipping_classes',
        );

        // Clean-up shipping options. 
        // We do this here because we already have the shipping classes and - zones in memory
        self::bumbal_clean_up_shipping_options($aShippingMethods, $aClasses);

        return $settings;
    }

    /**
     * Returns preference settings
     * 
     * @since 1.0.0
     * @return array of settings
     */
    private static function preference_settings() 
    {
        $settings = [
            [
                'name' => __('Bumbal preference settings', 'bumbal'),
                'type' => 'title',
                'desc' => __('All the specific preference settings for the Bumbal plug-in.', 'bumbal'),
                'id' => 'bumbal_preferences', 
            ]
        ];

        // Give option for datamapper plug-in if active
        if(is_plugin_active('bumbal-datamapper/bumbal-datamapper.php')) {
            $settings[] = [
                'name' => __('Use datamapper plug-in', 'bumbal'),
                'type' => 'checkbox',
                'desc_tip' => true,
                'desc' => __('When checking this checkbox you will not use the default datamapper but you will use the custom made datamapper in the \'Bumbal Datamapper\' plugin.<br>
                              <br> 
                              <b>Be very carefull when checking this checkbox. If there is no datamapper present this could result in errors!</b>', 'bumbal'),
                'id' => 'bumbal_external_datamapper',
            ];
        }

        $settings = array_merge($settings, [
            [
                'name' => __('Change status', 'bumbal'),
                'type' => 'checkbox',
                'desc_tip' => __('Change the order status to \'Completed\' when order is finished in Bumbal.', 'bumbal'),
                'id' => 'bumbal_status_change',
            ], [
                'name' => __('Shipping classes', 'bumbal'),
                'type' => 'checkbox',
                'desc_tip' => __('Ignore shipping classes for sending to Bumbal', 'bumbal'),
                'id' => 'bumbal_ignore_shippingclass',
            ], [
                'name' => __('Send products as notes', 'bumbal'),
                'type' => 'checkbox',
                'desc_tip' => __('By sending the packagelines as driver notes, it\'s possible to print a detailed route in Bumbal.', 'bumbal'),
                'id' => 'bumbal_send_packagelines_as_notes',
            ], [
                'name' => __('Overwrite ACF plug-in', 'bumbal'),
                'type' => 'checkbox',
                'desc_tip' => __('<i>Advanced Custom Fields</i> hides the metadata boxes on orders. By selecting this checkbox that function is overwritten.', 'bumbal'),
                'id' => 'bumbal_ACF_plugin',
            ], [
                'name' => __('Send invite after error', 'bumbal'),
                'type' => 'checkbox',
                'desc_tip' => __('Send an invite message for scheduling a timeslot when an order is send manual after an error occured.', 'bumbal'),
                'id' => 'bumbal_send_invite_after_error',
            ],[
                'type' => 'sectionend',
                'id' => 'bumbal_preferences',
            ],
        ]);
        
        // Timeslot pereferences
        $settings = array_merge($settings, [
            [
                'name' => __('Bumbal timeslot settings', 'bumbal'),
                'type' => 'title',
                'desc' => __('Timeslot settings.', 'bumbal'),
                'id' => 'bumbal_timeslot_preferences', 
            ],[
                'name' => __('Timeslots form', 'bumbal'),
                'type' => 'checkbox',
                'desc_tip' => __('Show customer the timeslot form during the checkout process', 'bumbal'),
                'id' => 'bumbal_timeslots_form',
            ],[
                'name' => __('Timeslot time', 'bumbal'),
                'type' => 'checkbox',
                'desc_tip' => __('Show customer the time from the timeslots.', 'bumbal'),
                'id' => 'bumbal_timeslots_show_time',
            ],[
                'name' => __('Timeslot hook', 'bumbal'),
                'type' => 'text',
                'desc' => __('Define the filter hook where the timeslots will be shown. Leave empty for default (woocommerce_thankyou).', 'bumbal'),
                'desc_tip' => true,
                'id' => 'bumbal_timeslot_hook'
            ],[
                'name' => __('Timeslot position', 'bumbal'),
                'type' => 'number',
                'desc' => __('Define the priority of the timeslots. A lower numbers means that the timeslots are higher on the page.', 'bumbal'),
                'desc_tip' => true,
                'id' => 'bumbal_timeslot_position'
            ],[
                'name' => __('Timeslot text', 'bumbal'),
                'type' => 'textarea',
                'desc' => __('Define the text that has to be shown above the timeslots form. Leave empty for default.', 'bumbal'),
                'desc_tip' => true,
                'id' => 'bumbal_timeslot_text'
            ],[
                'name' => __('Timeslot successfully chosen message', 'bumbal'),
                'type' => 'textarea',
                'desc' => __('Define the text that has to be shown when timeslots have been succesfully chosen. Leave empty for default.', 'bumbal'),
                'desc_tip' => true,
                'id' => 'bumbal_timeslot_thankyou_text'
            ],[
                'type' => 'sectionend',
                'id' => 'bumbal_timeslot_preferences',
            ]
        ]);

        // Multi-day activities
        $settings = array_merge($settings, [
            [
                'name' => __('Multi-day assignment settings', 'bumbal'),
                'type' => 'title',
                'desc' => __('With multi-day assignment an extra activity is created as pick-up.', 'bumbal'),
                'id' => 'bumbal_multiday',
            ], [
                'name' => __('Activate multi-day assignment', 'bumbal'),
                'type' => 'checkbox',
                'id' => 'bumbal_multiday_active',
            ],[
                'name' => __('Start timewindow pick-up', 'bumbal'),
                'type' => 'time',
                'desc' => __('When the option \'Timeslot form\' is not in use, the pick-up activity get\'s this start time as timeslot.', 'bumbal'),
                'desc_tip' => true,
                'id' => 'bumbal_multiday_start_time',
            ],[
                'name' => __('End timewindow pick-up', 'bumbal'),
                'type' => 'time',
                'desc' => __('When the option \'Timeslot form\' is not in use, the pick-up activity get\'s this end time as timeslot.', 'bumbal'),
                'desc_tip' => true,
                'id' => 'bumbal_multiday_end_time',
            ], [
                'type' => 'sectionend',
                'id' => 'bumbal_multiday',
            ]
        ]);
        
        return $settings;
    }

    /**
     * Returns communication
     * 
     * @since 1.0.0
     * @return array of settings
     */
    private static function communication_settings() 
    {
        $settings = [
            [
                'name' => __('Bumbal communication settings', 'bumbal'),
                'type' => 'title',
                'desc' => __('All the settings for sending communication to Bumbal.', 'bumbal'),
                'id' => 'bumbal_communication',
            ], [
                'name' => __('Send invite', 'bumbal'),
                'type' => 'checkbox',
                'desc_tip' => __('Send an invite message for scheduling a timeslot when the order is send to Bumbal.', 'bumbal'),
                'id' => 'bumbal_send_invite',
            ], [
                'name' => __('Send reminder', 'bumbal'),
                'type' => 'checkbox',
                'desc_tip' => __('Send a reminder when no timeslot is booked yet.', 'bumbal'),
                'id' => 'bumbal_send_reminder',
            ], [
                'name' => __('Send confirmation', 'bumbal'),
                'type' => 'checkbox',
                'desc_tip' => __('Send a confirmation message when a timesloot is booked.', 'bumbal'),
                'id' => 'bumbal_send_confirmation', 
            ], [
                'name' => __('Send plannend', 'bumbal'),
                'type' => 'checkbox',
                'desc_tip' => __('Send a message when the activity is planned on a route.', 'bumbal'),
                'id' => 'bumbal_send_planned',
            ], [
                'name' => __('Send ETA', 'bumbal'),
                'type' => 'checkbox',
                'desc_tip' => __('send an estimated time of arrival message.', 'bumbal'),
                'id' => 'bumbal_send_eta',
            ], [
                'name' => __('Send finished', 'bumbal'),
                'type' => 'checkbox',
                'desc_tip' => __('Send a message when the activity is finished.', 'bumbal'),
                'id' => 'bumbal_send_executed',
            ], [
                'name' => __('Send cancelled', 'bumbal'),
                'type' => 'checkbox',
                'desc_tip' => __('Send a message when the activity is cancelled.', 'bumbal'),
                'id' => 'bumbal_send_cancelled', 
            ], [
                'type' => 'sectionend',
                'id' => 'bumbal_communication',
            ]
        ];

        if(get_option('bumbal_multiday_active') == 'yes'){
            $settings = array_merge($settings, [
                [
                    'name' => __('Multi-day assignment communication settings', 'bumbal'),
                    'type' => 'title',
                    'desc' => __('Select what communication settings are used for the pick-up in the multi-day assignments.', 'bumbal'),
                    'id' => 'bumbal_multiday_communication',
                ], [
                    'name' => __('Send invite', 'bumbal'),
                    'type' => 'checkbox',
                    'desc_tip' => __('Send an invite message for scheduling a timeslot when the order is send to Bumbal.', 'bumbal'),
                    'id' => 'bumbal_multiday_send_invite',
                ], [
                    'name' => __('Send reminder', 'bumbal'),
                    'type' => 'checkbox',
                    'desc_tip' => __('Send a reminder when no timeslot is booked yet.', 'bumbal'),
                    'id' => 'bumbal_multiday_send_reminder',
                ], [
                    'name' => __('Send confirmation', 'bumbal'),
                    'type' => 'checkbox',
                    'desc_tip' => __('Send a confirmation message when a timesloot is booked.', 'bumbal'),
                    'id' => 'bumbal_multiday_send_confirmation', 
                ], [
                    'name' => __('Send plannend', 'bumbal'),
                    'type' => 'checkbox',
                    'desc_tip' => __('Send a message when the activity is planned on a route.', 'bumbal'),
                    'id' => 'bumbal_multiday_send_planned',
                ], [
                    'name' => __('Send ETA', 'bumbal'),
                    'type' => 'checkbox',
                    'desc_tip' => __('send an estimated time of arrival message.', 'bumbal'),
                    'id' => 'bumbal_multiday_send_eta',
                ], [
                    'name' => __('Send finished', 'bumbal'),
                    'type' => 'checkbox',
                    'desc_tip' => __('Send a message when the activity is finished.', 'bumbal'),
                    'id' => 'bumbal_multiday_send_executed',
                ], [
                    'name' => __('Send cancelled', 'bumbal'),
                    'type' => 'checkbox',
                    'desc_tip' => __('Send a message when the activity is cancelled.', 'bumbal'),
                    'id' => 'bumbal_multiday_send_cancelled', 
                ], [
                    'type' => 'sectionend',
                    'id' => 'bumbal_multiday_communication',
                ]
            ]);
        }

        return $settings;
    } 
    
    /**
     * Output the settings
     *
     * @since 1.0.0.
     */
    public function output() 
    {		
        global $current_section;
        
        $settings = $this->get_settings( $current_section );
        WC_Admin_Settings::output_fields( $settings );
    }		
    
    /**
     * Save settings
        *
        * @since 1.0
        */
    public function save() 
    {		
        global $current_section;
        
        $settings = $this->get_settings( $current_section );
        WC_Admin_Settings::save_fields( $settings );
    }

    /**
	 * If an shipping option is deleted in WooCommerce the Bumbal plugin doesn't automatically delete the corresponding option
     * This function is called when there is a refresh on the Bumbal plug-in settings page and cleans up any left-over options
	 * @param array $aShippingMethods
     * @param array $aShippingClasses
	 * @return void
	 *
	 * @since    1.0.0
	 */
    private static function bumbal_clean_up_shipping_options(array $aShippingMethods, array $aShippingClasses) : void
    {
        // Loop through all options
        foreach(wp_load_alloptions() as $sOption => $sValue) {
            // Get only shipping instance options
            if(strpos($sOption, 'bumbal_shipping_instance_' ) === 0) {                
                // Get the shipping instance ID
                $iShippingInstance = self::bumbal_explode_shipping_option($sOption);
                if(!array_key_exists($iShippingInstance, $aShippingMethods)) {
                    // Delete the option because shipping method is not here anymore
                    delete_option($sOption);
                }
            }
            // Get only shipping class options
            if(strpos($sOption, 'bumbal_shipping_class_') === 0){
                $iShippingClass = self::bumbal_explode_shipping_option($sOption);
                if(!array_key_exists($iShippingClass, $aShippingClasses)) {
                    delete_option($sOption);
                }
            }
        }
    }

    /**
	 * Returns ID on shipping option
	 * @param string $sOption
 	 * @return integer
	 *
	 * @since    1.0.0
	 */
    private static function bumbal_explode_shipping_option($sOption) {
        // Explode string to get to the id
        $aShipping = explode('_', $sOption);
        // Get the last in the array = ID
        return end($aShipping);
    }

}

return new Bumbal_WC_Settings;
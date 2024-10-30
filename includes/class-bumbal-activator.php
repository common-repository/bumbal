<?php

/**
 * Fired during plugin activation
 *
 * @link       http://www.bumbal.eu
 * @since      1.0.0
 *
 * @package    Bumbal
 * @subpackage Bumbal/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Bumbal
 * @subpackage Bumbal/includes
 * @author     Bumbal <it@bumbal.eu>
 */
class Bumbal_Activator 
{

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() 
	{
		// Set timeslot position to default value 10
		update_option('bumbal_timeslot_position', 10);
	}

}

<?php
setlocale (LC_TIME, "Dutch");
/**
 * Provide a public-facing view for parsing timeslots
 *
 *
 * @link       http://www.bumbal.eu
 * @since      1.0.0
 *
 * @package    Bumbal
 * @subpackage Bumbal/public/partials
 */
 ?>
<div id="bumbal-timeslot-wrapper"><!-- Begin Wrapper --> 
    <p>
        <u class="bumbal_timeslot_title">
            <?php
            if(empty(get_option('bumbal_timeslot_text'))) {
                _e('Schedule your appointment:', 'bumbal');
            } else {
                echo esc_attr(trim(get_option('bumbal_timeslot_text')));
            }
            ?>
        </u>
    </p>
    <!-- Show Alert/Response/Etc -->
    <div class="alert">

    </div>
    <form id="bumbal-timeslot-form" method="POST">
        <!-- Hidden Inputs -->
        <input type="hidden" name="action" value="bumbal_send_time_slot" />
        <input type="hidden" name="bumbal" value="<?php echo esc_attr($sBumbalId); ?>" />
        <input type="hidden" name="order" value="<?php echo esc_attr($order->get_id()); ?>" />
        <input type="hidden" name="key" value="<?php echo esc_attr($order->get_order_key()); ?>" />
        <?php
        wp_nonce_field('bumbal_set_time_slot_ajax', 'bumbal_ajax_nonce');

        //Visible inputs

        // Loop through all the timeslots
        foreach($aTimeSlots as $oTimeSlot) {
            // Format the timeslots
            $oTimeSlot->date_time_from = strtotime($oTimeSlot->date_time_from);
            $oTimeSlot->date_time_to = strtotime($oTimeSlot->date_time_to);

            // If the option to show times in the timeslot form is checked, we include the timeslot-include-times-form and if not we include the form that shows days only instead.
            include (get_option('bumbal_timeslots_show_time') == 'yes') ? 'bumbal-public-timeslot-include-times.php' : 'bumbal-public-timeslot-days-only.php';

        }
        ?>
        <input type="submit" value="<?php _e('Confirm', 'bumbal') ?>" />
    </form>
</div><!-- End Wrapper -->
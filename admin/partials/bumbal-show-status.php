<?php

/**
 * Provide a admin area view for the plugin
 *
 *
 * @link       http://www.bumbal.eu
 * @since      1.0.0
 *
 * @package    Bumbal
 * @subpackage Bumbal/admin/partials
 */
?>
<h3>Bumbal</h3>

<p>
    <strong><?php _e('Number', 'bumbal'); ?></strong><br>    
    <?php
    if($oBumbal) {
        if($oBumbal->code === 200) {
            echo esc_attr($oBumbal->additional_data->activity_info->nr);
        }
        else {
            _e('API error', 'bumbal');
        }
    }
    else {
        _e('Unknown', 'bumbal');
    }
    ?>
</p>

<p>
    <strong><?php _e('Status', 'bumbal'); ?></strong><br>
    <?php
    if(isset($sBumbalStatus)) {
        echo esc_attr($sBumbalStatus);
    }
    else {
        _e('Unknown', 'bumbal');
    }
    ?>
</p>

<?php
if(isset($sRoute)) {
    echo wp_kses('<p><strong>'.esc_attr_e('Route', 'bumbal').'</strong><br>'.
          $sRoute
          .'</p>', array('p' => array(), 'strong' => array(), 'br' => array()));
}




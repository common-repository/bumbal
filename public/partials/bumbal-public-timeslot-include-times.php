<section class="bumbal-timeslot-section">
    <header class="bumbal-timeslot-header">
        <?php echo esc_textarea(ucwords(strftime('%A %e %B %Y', $oTimeSlot->date_time_from))); ?>
    </header>

    <main class="bumbal-timeslot-main">
        <input type="checkbox" id="<?php echo esc_attr($oTimeSlot->key); ?>" name="timeslot[]" value="<?php echo esc_attr($oTimeSlot->key); ?>">
        <label for="<?php echo esc_attr($oTimeSlot->key); ?>">
            <?php printf(__('From <b>%s</b> to <b>%s</b>', 'bumbal'), strftime('%H:%M', $oTimeSlot->date_time_from), strftime('%H:%M', $oTimeSlot->date_time_to)); ?>
            <?php 
            if(!empty($oTimeSlot->follow_up_time_slots)) { 
                ?>
                <br />
                <i><?php printf(__('Follow up %s', 'bumbal'), current($oTimeSlot->follow_up_time_slots)->date_time_from) ?></i>

                <?php 
            } 
            ?>
        </label>
    </main>
</section>
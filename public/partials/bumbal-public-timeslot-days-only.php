<section class="bumbal-timeslot-section">
    <main class="bumbal-timeslot-main">
        <input type="checkbox" id="<?php echo esc_attr($oTimeSlot->key); ?>" name="timeslot[]" value="<?php echo esc_attr($oTimeSlot->key); ?>">
        <label for="<?php echo esc_attr($oTimeSlot->key); ?>">
            <?php echo esc_textarea(ucwords(strftime('%A %e %B %Y', $oTimeSlot->date_time_from))); ?><br />
            <?php 
            if(!empty($oTimeSlot->follow_up_time_slots)) { 
                ?>
                    <i><?php printf(__('Follow up %s', 'bumbal'), substr(current($oTimeSlot->follow_up_time_slots)->date_time_from, 0, 10)) ?></i>
                <?php
            }
            ?>
        </label>
    </main>
</section>
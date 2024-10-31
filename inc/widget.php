<?php

class PrimeAdsWidget extends \WP_Widget {
    function __construct() {
        $widget_ops = array('classname' => 'widget_primeads', 'description' => 'Prime Ads');
        parent::__construct('primeads', 'Prime Ads', $widget_ops);
    }

    function widget( $args, $instance ) {
        extract($args);
        $id = empty($instance['id']) ? 0 : $instance['id'];

        echo $before_widget;
        echo do_shortcode('[primeads pos=' . $id . ']');
        echo $after_widget;
    }

    function update( $new_instance, $old_instance ) {
        $instance = $old_instance;
        $instance['id'] = strip_tags($new_instance['id']);
        return $instance;
    }

    function form( $instance ) {
        $id = empty( $instance['id'] ) ? '' : trim($instance['id']);
        ?>
            <p><label for="<?php echo $this->get_field_id('id'); ?>">Идентификатор позиции (число):</label>
            <input class="widefat" id="<?php echo $this->get_field_id('id'); ?>" name="<?php echo $this->get_field_name('id'); ?>" type="text" value="<?php echo esc_attr($id); ?>" /></p>
        <?php
    }
}

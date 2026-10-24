<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Classic WordPress widget (Appearance → Widgets) wrapping [adult-team-widget-N],
 * for sidebars / footers / any widget area the theme offers.
 */
class Allstars_Adult_Team_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'aat_team_widget',
            'Adult Team — Up next & latest',
            array( 'description' => 'Compact card for one adult team: up next, latest result and league position (from FA Full-Time).' )
        );
    }

    public function widget( $args, $instance ) {
        $slot = (int) ( $instance['slot'] ?? 0 );
        if ( ! $slot ) return;
        echo $args['before_widget'];
        if ( ! empty( $instance['title'] ) ) {
            echo $args['before_title'] . esc_html( apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base ) ) . $args['after_title'];
        }
        echo Allstars_Adult_Teams::render_widget( $slot, array() );
        echo $args['after_widget'];
    }

    public function form( $instance ) {
        $slot  = (int) ( $instance['slot'] ?? 0 );
        $title = (string) ( $instance['title'] ?? '' );
        $slots = Allstars_Adult_Teams::get_slots();
        ?>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">Widget title (optional)</label>
            <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'slot' ) ); ?>">Team</label>
            <select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'slot' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'slot' ) ); ?>">
                <option value="0">— choose —</option>
                <?php foreach ( $slots as $n => $s ) : ?>
                <option value="<?php echo (int) $n; ?>"<?php selected( $n, $slot ); ?>><?php echo (int) $n; ?>: <?php echo esc_html( $s['label'] ); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p class="description">Teams are set up under <strong>Adult Teams</strong> in the dashboard.</p>
        <?php
    }

    public function update( $new_instance, $old_instance ) {
        return array(
            'slot'  => (int) ( $new_instance['slot'] ?? 0 ),
            'title' => sanitize_text_field( $new_instance['title'] ?? '' ),
        );
    }
}

add_action( 'widgets_init', function() {
    register_widget( 'Allstars_Adult_Team_Widget' );
} );

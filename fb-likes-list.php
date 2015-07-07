<?php
/*
Plugin Name: Facebook Likes List
Plugin URI: http://andrewnorcross.com/plugins/fb-likes-list/
Description: Retrieves and stored Facebook like counts and lists popular
Version: 1.0.5
Author: Andrew Norcross
Author URI: http://andrewnorcross.com

	Copyright 2013 Andrew Norcross

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// Start up the engine
class FB_Likes_List
{

	/**
	 * This is our constructor
	 *
	 * @return FB_Likes_List
	 */
	public function __construct() {
		add_action( 'wp',                               array( $this, 'grab_count'      )           );
		add_action( 'widgets_init',                     array( $this, 'register_widget' )           );
	}

	/**
	 * grab recent like count for post
	 *
	 * @return FB_Likes_List
	 */
	public function grab_count() {

		// do not load on admin or not on singular
		if ( is_admin() || ! is_singular( 'post' ) ) {
			return;
		}

		// fetch the global post object
		global $post;

		// bail without the object
		if ( empty( $post ) || ! is_object( $post ) ) {
			return;
		}

		// get the link
		$link   = get_permalink( $post->ID );

		// make the call
		$call   = 'https://graph.facebook.com/fql?q=SELECT%20like_count%20FROM%20link_stat%20WHERE%20url=%27' . urlencode( $link ) . '%27';

		// do the call
		$rmget  = wp_remote_get( $call, array( 'sslverify' => false ) );

		// error. bail.
		if( is_wp_error( $rmget ) ) {
			return false;
		}

		// parse return values
		$return = json_decode( $rmget['body'] );

		// bail with no decoded
		if ( empty( $return ) || empty( $fb_return->data ) || empty( $fb_return->data[0] ) || empty( $fb_return->data[0]->like_count ) ) {
			return false;
		}

		// get the count
		$count  = $fb_return->data[0]->like_count;

		// update the count
		update_post_meta( $post->ID, '_fb_like', $count );
	}

	/**
	 * register our custom widgets
	 *
	 * @return void
	 *
	 * @since 1.0
	 */
	public function register_widget() {
		register_widget( 'FB_Like_List_Widget' );
	}

/// end class
}

// Instantiate our class
$FB_Likes_List = new FB_Likes_List();

/**
 * grab recent like count for post
 *
 * @return FB_Likes_List
*/

class FB_Like_List_Widget extends WP_Widget {

	/**
	 * [__construct description]
	 */
	function __construct() {
		$widget_ops = array( 'classname' => 'fb_like_list', 'description' => __( 'Displays posts based on FB like count' ) );
		parent::__construct( 'fb_like_list', __( 'FB Like List' ), $widget_ops );
		$this->alt_option_name = 'fb_like_list';
	}

	/**
	 * [widget description]
	 * @param  [type] $args     [description]
	 * @param  [type] $instance [description]
	 * @return [type]           [description]
	 */
	function widget( $args, $instance ) {

		// get our count
		$count  = ! empty( $instance['count'] ) ? absint( $instance['count'] ) : 5;

		// set the item args
		$args   = array(
			'post_type'         => 'post',
			'posts_per_page'    => absint( $count ),
			'order'             => 'DESC',
			'orderby'           => 'meta_value_num',
			'meta_key'          => '_fb_like',
			'post_status'       => 'publish',
			'meta_query'        => array(
				array(
					'key'       => '_fb_like',
					'value'     => 0,
					'type'      => 'numeric',
					'compare'   => '>'
				)
			),
		);

		// get the items
		$items  = get_posts( $args );

		// bail without items
		if ( empty( $items ) ) {
			return;
		}

		// check for showing the total
		$showt  = isset( $instance['number'] ) ? $instance['number'] : false;

		// now do it
		echo $args['before_widget'];

		// set the title
		$title  = empty( $instance['title'] ) ? '' : apply_filters( 'widget_title', $instance['title'] );

		// output the title
		if ( ! empty( $title ) ) { echo $args['before_title'] . $title . $args['after_title']; };

		// output the list
		echo '<ul>';

		// loop the items
		foreach ( $items as $item ) {

			// get the items for each link
			$link   = get_permalink( $item->ID );
			$title  = get_the_title( $item->ID );
			$total  = get_post_meta( $item->ID, '_fb_like', true );

			// the
			$viewt  = ! empty( $showt ) ? ' (' . absint( $total ) . ')' : '';

			// the link
			echo '<li><a href="' . esc_url( $link ) . '">' . esc_attr( $title ) . $viewt . '</a></li>';
		}

		// close the list
		echo '</ul>';

		// close the widget
		echo $args['after_widget'];
	}

	/** @see WP_Widget::update */
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		// set and sanitize the variables
		$instance['title']  = sanitize_text_field( $new_instance['title'] );
		$instance['count']  = absint( $new_instance['count'] );
		$instance['number'] = ! empty( $new_instance['number'] ) ? true : false;

		// return the instance
		return $instance;
	}

	/** @see WP_Widget::form */
	function form( $instance ) {

		// set the items
		$title  = ! empty( $instance['title'] ) ? esc_attr( $instance['title'] ) : 'Popular Posts';
		$count  = ! empty( $instance['count'] ) ? absint( $instance['count'] ) : 5;
		$number = ! empty( $instance['number'] ) ? true : false;
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Widget Title:' ); ?>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" /></label>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'count' ); ?>"><?php _e( 'Post Count:' ); ?>
			<input class="widefat" id="<?php echo $this->get_field_id( 'count' ); ?>" name="<?php echo $this->get_field_name( 'count' ); ?>" type="text" value="<?php echo $count; ?>" /></label>
		</p>
		<p>
			<input class="checkbox" type="checkbox" <?php checked( $number ); ?> id="<?php echo $this->get_field_id( 'number' ); ?>" name="<?php echo $this->get_field_name( 'number' ); ?>" />
			<label for="<?php echo $this->get_field_id( 'number' ); ?>"><?php _e( 'Display like count?' ); ?></label>
		</p>
	<?php }

} // class

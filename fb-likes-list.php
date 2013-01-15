<?php
/*
Plugin Name: Facebook Likes List
Plugin URI: http://andrewnorcross.com/plugins/fb-likes-list/
Description: Retrieves and stored Facebook like counts and lists popular
Version: 1.0.4
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
		add_action		( 'wp', 		array( $this, 'grab_count'		) 			);
	}

	/**
	 * grab recent like count for post
	 *
	 * @return FB_Likes_List
	 */

	public function grab_count() {

		// do not load on admin
		if ( is_admin() )
			return;

		// check type and bail on non-single posts
		if ( !is_singular( 'post' ) )
			return;

		// ok. we're on a single post. now get moving
        $args = array(
            'sslverify' => false
            );

		global $post;

		$fb_check	= get_permalink( $post->ID );
		$fb_call	= 'https://graph.facebook.com/fql?q=SELECT%20like_count%20FROM%20link_stat%20WHERE%20url=%27'.urlencode($fb_check).'%27';

		$response	= wp_remote_get( $fb_call, $args );

		// error. bail.
		if( is_wp_error( $response ) )
			return;

		// parse return values
		$fb_return	= json_decode($response['body']);
		$fb_like	= $fb_return->data[0]->like_count;

		update_post_meta( $post->ID, '_fb_like', $fb_like );

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

class fb_like_list_widget extends WP_Widget {
	function fb_like_list_widget() {
		$widget_ops = array( 'classname' => 'fb_like_list', 'description' => 'Displays posts based on FB like count' );
		$this->WP_Widget( 'fb_like_list', 'FB Like List', $widget_ops );
	}

	function widget( $args, $instance ) {
		extract( $args, EXTR_SKIP );
		echo $before_widget;
		$title = empty($instance['title']) ? '' : apply_filters('widget_title', $instance['title']);
		if ( !empty( $title ) ) { echo $before_title . $title . $after_title; };

			$count = !empty( $instance['count'] ) ? absint( $instance['count'] ) : 5;

			$fbposts = new WP_Query( array (
				'post_type'			=> 'post',
				'posts_per_page'	=> $count,
				'order'				=> 'DESC',
				'orderby'			=> 'meta_value_num',
				'meta_key'			=> '_fb_like',
				'post_status'		=> 'publish',
				'meta_query'		=> array(
					array(
						'key'		=> '_fb_like',
						'value'		=> 0,
						'type'		=> 'numeric',
						'compare'	=> '>'
					)
				),
				'no_found_rows'		=> true,
			));

			if ($fbposts->have_posts()) :
			$show_total = isset( $instance['number'] ) ? $instance['number'] : false;
			echo '<ul>';
			while ($fbposts->have_posts()) : $fbposts->the_post();
				// begin single items
				global $post;
				$link		= get_permalink($post->ID);
				$title		= get_the_title($post->ID);
				$total		= get_post_meta($post->ID, '_fb_like', true );

				echo '<li>';
				echo '<a href="'.$link.'">';
				echo ''.$title.'';
				if ( $show_total )
					echo ' ('.$total.')';
				echo '</a>';
				echo '</li>';
			// end each item
			endwhile;
			wp_reset_postdata();
			echo '</ul>';
			endif;

		echo $after_widget;
		?>

        <?php }

    /** @see WP_Widget::update */
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title']	= strip_tags($new_instance['title']);
		$instance['count']	= (int) $new_instance['count'];
		$instance['number']	= (bool) $new_instance['number'];

		return $instance;
	}

    /** @see WP_Widget::form */
    function form($instance) {
		$title	= isset( $instance['title'] )	? esc_attr( $instance['title'] ) : 'Popular Posts';
		$count	= isset( $instance['count'] )	? absint( $instance['count'] ) : 5;
		$number	= isset( $instance['number'] )	? (bool) $instance['number'] : false;
        ?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Widget Title:'); ?>
			<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" /></label>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('count'); ?>"><?php _e('Post Count:'); ?>
			<input class="widefat" id="<?php echo $this->get_field_id('count'); ?>" name="<?php echo $this->get_field_name('count'); ?>" type="text" value="<?php echo esc_attr($count); ?>" /></label>
		</p>
		<p>
			<input class="checkbox" type="checkbox" <?php checked( $number ); ?> id="<?php echo $this->get_field_id( 'number' ); ?>" name="<?php echo $this->get_field_name( 'number' ); ?>" />
			<label for="<?php echo $this->get_field_id( 'number' ); ?>"><?php _e( 'Display like count?' ); ?></label>
		</p>
		<?php }

} // class

add_action( 'widgets_init', create_function( '', "register_widget('fb_like_list_widget');" ) );

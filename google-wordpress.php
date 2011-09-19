<?php
/*
Plugin Name: Google+ WordPress
Plugin URI: http://sutherlandboswell.com
Description: Display your latest Google+ posts in your sidebar.
Author: Sutherland Boswell
Author URI: http://sutherlandboswell.com
Version: 0.1
License: GPL2
*/
/*  Copyright 2011 Sutherland Boswell  (email : sutherland.boswell@gmail.com)

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

// Set Default Options

register_activation_hook(__FILE__,'google_wordpress_activate');
register_deactivation_hook(__FILE__,'google_wordpress_deactivate');

function google_wordpress_activate() {
	add_option('google_wordpress_api_key','');
}

function google_wordpress_deactivate() {
	delete_option('google_wordpress_api_key');
}

// Stream Widget

class Google_WordPress extends WP_Widget 
{

	function Google_WordPress() 
	{
		$widget_ops = array('classname' => 'google_wordpress_widget', 'description' => 'Show your recent Google+ activity');
		$this->WP_Widget('google_wordpress', 'Google+ Stream', $widget_ops);
	}
	
	function getLatestGooglePosts($user_id, $max_results) {
	    $api_key = get_option('google_wordpress_api_key');
	    $request = "https://www.googleapis.com/plus/v1/people/$user_id/activities/public?key=$api_key&maxResults=$max_results";
	    $response = wp_remote_get( $request );
	    if( is_wp_error( $response ) ) {
	       echo 'Something went wrong';
	    } else {
	       $response = json_decode($response['body']);
	       return $response;
	    }
	}

	function widget($args, $instance) 
	{
		extract($args);

		echo $before_widget;
		$title = strip_tags($instance['title']);
		echo $before_title . $title . $after_title;
		
		if (get_option('google_wordpress_api_key')=='') echo "Visit the Google+ WordPress settings page and enter your API key";
		else {
			$results = $this->getLatestGooglePosts($instance['user_id'], $instance['max_results']);
	
			foreach ($results->items as $item) {
				echo "<div>";
				if ($item->object->content != "") echo "<p>" . $item->object->content . "</p>";
				if ($item->object->attachments) foreach ($item->object->attachments as $attachment) {
					if ($attachment->objectType == 'article') {
						echo "<p><a href=\"" . $attachment->url . "\">" . $attachment->displayName . "</a></p><p>";
						if ($item->object->attachments[1]->objectType == 'photo') echo "<p  style=\"float:left; margin-right: 10px;\"><img src=\"" . $item->object->attachments[1]->image->url . "\"></p>";
						echo $attachment->content . "</p>";
						break;
					}
					if ($attachment->objectType == 'photo') echo "<p style=\"float:left; margin-right:10px;\"><a href=\"" . $attachment->url . "\"><img src=\"" . $attachment->image->url . "\"></a></p>";
					if ($attachment->objectType == 'video') echo "<p>" . $attachment->displayName . "</p><div id=\"video-" . $item->id . "\"><img src=\"" . $attachment->image->url . "\" onclick=\"document.getElementById('video-" . $item->id . "').innerHTML='<embed src=\'" . $attachment->url . "\'></embed>'\"></div>";
				}
				if ($item->object->plusoners->totalItems != 0) {
					echo "<p style=\"clear:both;text-align:right;\">+" . $item->object->plusoners->totalItems . "</p>";
				}
				echo "</div><hr style=\"clear:both;\">";
			}
		}

		echo $after_widget;
	}
	
	function update($new_instance, $old_instance) 
	{
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['user_id'] = trim(strip_tags($new_instance['user_id']));
		$instance['max_results'] = trim(strip_tags($new_instance['max_results']));

		return $instance;
	}

	function form($instance) 
	{
		$instance = wp_parse_args((array)$instance, array('title' => 'Google+', 'max_results' => 5));
		$title = strip_tags($instance['title']);
		$user_id = strip_tags($instance['user_id']);
		$max_results = strip_tags($instance['max_results']);
?>
			<p><label for="<?php echo $this->get_field_id('title'); ?>">Title: <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo attribute_escape($title); ?>" /></label></p>
			<p><label for="<?php echo $this->get_field_id('user_id'); ?>">User ID: <input class="widefat" id="<?php echo $this->get_field_id('user_id'); ?>" name="<?php echo $this->get_field_name('user_id'); ?>" type="text" value="<?php echo attribute_escape($user_id); ?>" /></label></p>
			<p><label for="<?php echo $this->get_field_id('max_results'); ?>">Number of posts to show: <input class="widefat" id="<?php echo $this->get_field_id('max_results'); ?>" name="<?php echo $this->get_field_name('max_results'); ?>" type="text" value="<?php echo attribute_escape($max_results); ?>" /></label></p>
<?php
	}

}

add_action('widgets_init', 'RegisterGoogleWordPressWidget');

function RegisterGoogleWordPressWidget() {
	register_widget('Google_WordPress');
}

// Admin Page

add_action('admin_menu', 'google_wordpress_menu');

function google_wordpress_menu() {

  add_options_page('Google+ WordPress Options', 'Google+ WordPress', 'manage_options', 'google-wordpress-options', 'google_wordpress_options');

}

function google_wordpress_options() {

  if (!current_user_can('manage_options'))  {
    wp_die( __('You do not have sufficient permissions to access this page.') );
  }

?>

<div class="wrap">

	<div id="icon-plugins" class="icon32"></div><h2>Google+ WordPress</h2>
	
	<h3>Getting Started</h3>
	
	<p>Before using this plugin you need to get an API key from Google. Visit the <a href="https://code.google.com/apis/console/">Google API site</a>, make sure the Google+ API is switched on under services, then under API Access copy your API key from "Simple API Access."</p>
	
	<div id="icon-options-general" class="icon32"></div><h2>Options</h2>

	<form method="post" action="options.php">
	<?php wp_nonce_field('update-options'); ?>

	<h3>Settings</h3>
	
	<table class="form-table">
	
	<tr valign="top">
	<th scope="row">API Key</th> 
	<td><fieldset><legend class="screen-reader-text"><span>API Key</span></legend> 
	<input name="google_wordpress_api_key" type="text" id="google_wordpress_api_key" value="<?php echo get_option('google_wordpress_api_key'); ?>" />
	</fieldset></td> 
	</tr>
	
	</table>
	
	<input type="hidden" name="action" value="update" />
	<input type="hidden" name="page_options" value="google_wordpress_api_key" />
	
	<p class="submit">
	<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
	</p>
	
	</form>

</div>

<?php

}

?>
<?php
/*
Plugin Name: BB Web Hooks
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
Description: Web hooks for bbPress: sets up URLs that bbPress will get notified on every new post or reply.
Version: 0.1
Author: Juri Hudolejev
Author URI: http://google.com/?q=juri+hudolejev
License: GPLv2
*/

define('BBWH_OPTION_NEW_REPLY_WEBHOOK_URL', 'bbwh_reply_added_webhook_url');
define('BBWH_OPTION_NEW_TOPIC_WEBHOOK_URL', 'bbwh_topic_added_webhook_url');
define('BBWH_OPTION_SETTINGS_PAGE_NAME', 'bbwh-settings');

add_action('admin_menu', 'bbwh_add_menu');
add_action('bbp_new_reply', 'bbwh_handle_new_reply', 10, 5);
add_action('bbp_new_topic', 'bbwh_handle_new_topic', 10, 4);

add_filter("plugin_action_links_" . plugin_basename(__FILE__), 'bbwh_add_settings_link');



/*
 * Userspace functions
 */

function bbwh_handle_new_reply($reply_id, $topic_id, $forum_id, $anonymous_data, $user_id) {
	$webhook_url = get_option(BBWH_OPTION_NEW_REPLY_WEBHOOK_URL);
	if (!$webhook_url) {
		return;
	}

	$webhook_url = bbwh_populate_webhook_url($webhook_url, $forum_id, $topic_id, $reply_id, $user_id);

	file_get_contents($webhook_url);
}

function bbwh_handle_new_topic($topic_id, $forum_id, $anonymous_data, $user_id) {
	$webhook_url = get_option(BBWH_OPTION_NEW_TOPIC_WEBHOOK_URL);
	if (!$webhook_url) {
		return;
	}

	$webhook_url = bbwh_populate_webhook_url($webhook_url, $forum_id, $topic_id, 0, $user_id);

	file_get_contents($webhook_url);
}

function bbwh_populate_webhook_url($webhook_url, $forum_id, $topic_id, $reply_id, $user_id) {
	$webhook_url = str_replace('{forum_id}', (int) $forum_id, $webhook_url);

	$topic = bbp_get_topic($topic_id);
	$webhook_url = str_replace('{topic_id}', (int) $topic_id, $webhook_url);
	$webhook_url = str_replace('{topic_title}', urlencode($topic->post_title), $webhook_url);
	$webhook_url = str_replace('{topic_url}', urlencode($topic->guid), $webhook_url);

	$user = get_userdata($user_id);
	$webhook_url = str_replace('{user_id}', (int) $user_id, $webhook_url);
	$webhook_url = str_replace('{user_login}', urlencode($user->user_login), $webhook_url);
	$webhook_url = str_replace('{user_name}', urlencode($user->display_name), $webhook_url);

	if ($reply_id) {
		$reply = bbp_get_reply($reply_id);
		$webhook_url = str_replace('{reply_id}', (int) $reply_id, $webhook_url);
		$webhook_url = str_replace('{reply_url}', urlencode($reply->guid), $webhook_url);
		$post_content = bbwh_get_clipped_content($reply_id);
	} else {
		$post_content = bbwh_get_clipped_content($topic_id);
	}
	$webhook_url = str_replace('{content}', urlencode($post_content), $webhook_url);

	return $webhook_url;
}

function bbwh_get_clipped_content($post_id, $max_length = 1000, $clipped_str = '... [clipped]') {
	$post = get_post($post_id);
	$post_content = $post->post_content;

	$clipped_str_length = strlen($clipped_str);

	if (strlen($post_content) + $clipped_str_length > $max_length) {
		$post_content = substr($post_content, 0, $max_length - $clipped_Str_length) . $clipped_str;
	}

	return $post_content;
}



/*
 * Plugin settings
 */

function bbwh_add_settings_link($links) { 
	$settings_link = '<a href="' . admin_url("options-general.php?page=" . BBWH_OPTION_SETTINGS_PAGE_NAME) . '">' . __('Settings') . '</a>'; 
	array_unshift($links, $settings_link);
	return $links; 
}

function bbwh_add_menu() {
	add_options_page('BB Web Hooks Settings', 'BB Web Hooks', 'manage_options', BBWH_OPTION_SETTINGS_PAGE_NAME, 'bbwh_show_options_page');
}

function bbwh_show_options_page() {
	if (!current_user_can('manage_options')) {
		wp_die(__('You do not have sufficient permissions to access this page.'));
	}

	$options = array(
		BBWH_OPTION_NEW_REPLY_WEBHOOK_URL => '',
		BBWH_OPTION_NEW_TOPIC_WEBHOOK_URL => '',
	);

	$is_updated = false;
	foreach ($options as $key => &$value) {
		if (isset($_POST[$key])) {
			$result = update_option($key, $_POST[$key]);
			if ($result) $is_updated = true;
			$value = $_POST[$key];
		} else {
			$value = get_option($key);
		}
	}
?>

<?php if ($is_updated): ?>
<div class="updated"><p><strong><?php _e('Settings saved.'); ?></strong></p></div>
<?php endif; ?>

<div class="wrap">
	<?php screen_icon(); ?>
	<h2><?php _e('BB Integration Settings'); ?></h2>

	<form name="bbwh_settings_form" method="POST" action="">
	<table>
	<tr>
		<td colspan="2"><h3><?php _e("Web hook URLs"); ?></h3></td>
	</tr>
	<tr>
		<td style="vertical-align:top"><?php _e("New topic:"); ?></td>
		<td>
			<textarea name="<?php echo BBWH_OPTION_NEW_TOPIC_WEBHOOK_URL; ?>"><?php echo $options[BBWH_OPTION_NEW_TOPIC_WEBHOOK_URL]; ?></textarea><br />
			<i><?php _e("Leave empty to disable"); ?></i>
		</td>
	</tr>
	<tr>
		<td style="vertical-align:top"><?php _e("New reply:"); ?></td>
		<td>
			<textarea name="<?php echo BBWH_OPTION_NEW_REPLY_WEBHOOK_URL; ?>"><?php echo $options[BBWH_OPTION_NEW_REPLY_WEBHOOK_URL]; ?></textarea><br />
			<i><?php _e("Leave empty to disable"); ?></i>
		</td>
	</tr>
	<tr>
		<td colspan="2"><input type="submit" name="submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" /></td>
	</tr>
	</table>
	</form>
</div>

<?php
}


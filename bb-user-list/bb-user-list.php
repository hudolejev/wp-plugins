<?php
/*
Plugin Name: BB User List
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
Description: User list tweaks for bbPress: topic and reply counts added.
Version: 0.1
Author: Juri Hudolejev
Author URI: http://google.com/?q=juri+hudolejev
License: GPLv2
*/

// Based on https://gist.github.com/643240



add_action('manage_users_columns', 'bbul_add_custom_column_headers');
add_action('manage_users_custom_column', 'bbul_add_custom_columns', 10, 3);



function bbul_add_custom_column_headers($column_headers) {
	$column_headers['topic_count'] = __('Topics');
	$column_headers['reply_count'] = __('Replies');
	return $column_headers;
}

function bbul_add_custom_columns($column_value, $column_name, $user_id) {
	$counts = bbul_get_custom_post_counts();

	if ($column_name == 'reply_count') {
		$column_value = (int) $counts[$user_id]['reply'];
		if ($column_value > 0) {
			$column_value = '<a href="' . admin_url("edit.php?post_type=reply&amp;author=$user_id") . '">' . $column_value . '</a>';
		}
	} elseif ($column_name == 'topic_count') {
		$column_value = (int) $counts[$user_id]['topic'];
		if ($column_value > 0) {
			$column_value = '<a href="' . admin_url("edit.php?post_type=topic&amp;author=$user_id") . '">' . $column_value . '</a>';
		}
	} else {
		$column_value = 0;
	}

	return $column_value;
}

function bbul_get_custom_post_counts() {
	global $wpdb;

	static $counts;

	if (isset($counts)) {
		return $counts;
	}

	$sql = <<<SQL
SELECT
	post_type,
	post_author,
	COUNT(*) AS post_count
FROM {$wpdb->posts}
WHERE
	post_type IN ('reply', 'topic')
	AND post_status IN ('pending', 'publish')
GROUP BY post_type, post_author
SQL;
	$posts = $wpdb->get_results($sql);

	$counts = array();
	foreach($posts as $post) {
		$counts[$post->post_author][$post->post_type] = $post->post_count;
	}

	return $counts;
}


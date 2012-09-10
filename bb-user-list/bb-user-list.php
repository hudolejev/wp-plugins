<?php
/*
Plugin Name: BB User List
Plugin URI: https://github.com/hudolejev/wp-plugins/tree/master/bb-user-list
Description: User list tweaks for bbPress: topic and reply counts added.
Version: 0.2
Author: Juri Hudolejev
Author URI: http://google.com/?q=juri+hudolejev
License: GPLv2
*/

// Based on https://gist.github.com/643240


add_action('admin_head', 'bbul_style_custom_columns');
add_action('pre_user_query', 'bbul_pre_user_query');

add_filter('manage_users_columns', 'bbul_add_custom_column_headers');
add_filter('manage_users_custom_column', 'bbul_add_custom_columns', 10, 3);
add_filter('manage_users_sortable_columns', 'bbul_make_custom_column_headers_sortable');



function bbul_pre_user_query($query) {
	if ($query->query_vars['orderby'] == 'reply_count') {
		$query->query_from = <<<SQL
FROM wp_users
LEFT OUTER JOIN (
	SELECT post_author, COUNT(*) AS reply_count
	FROM wp_posts
	WHERE post_type = 'reply' AND post_status IN ('pending', 'publish')
	GROUP BY post_author
) p ON (wp_users.ID = p.post_author)
INNER JOIN wp_usermeta ON (wp_users.ID = wp_usermeta.user_id)
SQL;
		$query->query_orderby = "ORDER BY " . $query->query_vars['orderby'] . " " . $query->query_vars['order'];
	} elseif ($query->query_vars['orderby'] == 'topic_count') {
		$query->query_from = <<<SQL
FROM wp_users
LEFT OUTER JOIN (
	SELECT post_author, COUNT(*) AS topic_count
	FROM wp_posts
	WHERE post_type = 'topic' AND post_status IN ('pending', 'publish')
	GROUP BY post_author
) p ON (wp_users.ID = p.post_author)
INNER JOIN wp_usermeta ON (wp_users.ID = wp_usermeta.user_id)
SQL;
		$query->query_orderby = "ORDER BY " . $query->query_vars['orderby'] . " " . $query->query_vars['order'];
	}

	return $query;
}

function bbul_add_custom_column_headers($column_headers) {
	$column_headers['topic_count'] = __('Topics');
	$column_headers['reply_count'] = __('Replies');
	return $column_headers;
}

function bbul_make_custom_column_headers_sortable($column_headers) {
	$column_headers['posts'] = 'post_count';
	$column_headers['topic_count'] = 'topic_count';
	$column_headers['reply_count'] = 'reply_count';
	return $column_headers;
}

function bbul_style_custom_columns() {
?>
<!--BB User List plugin styles-->
<style type="text/css">
.column-reply_count, .column-topic_count {
	text-align: center; /* as in .num */
	width: 10%; /* as in .column-posts */
}
</style>
<!--/BB User List plugin styles-->
<?php
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


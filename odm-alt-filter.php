<?php

/**
 * Plugin Name: odm-alt-filter
 * Description: Adds a filter to the gallery to allow for filtering by missing alt tags
 * Version: 2023.08.14
 */

defined('WPINC') || die();

const ODM_FIELD_NAME = 'filter_img_alt';
const ODM_IMAGE_MIME_TYPES = array('image/jpeg', 'image/gif', 'image/png', 'image/bmp', 'image/tiff', 'image/x-icon', 'image/webp');

function odm_is_on_media_admin_page()
{
	$is_on_page = false;

	if (!function_exists('get_current_screen')) {
		require_once ABSPATH . '/wp-admin/includes/screen.php';
	}

	if (is_admin() && !empty($screen = get_current_screen())) {
		$is_on_page = ($screen->base == 'upload');
	}

	return $is_on_page;
}

function odm_get_filter_options()
{
	return array(
		'no-filter' => '-- IMG ALT Text --',
		'only-with-alt' => 'Images with ALT',
		'only-without-alt' => 'Images missing ALT',
	);
}

function odm_get_filter_from_query_args()
{
	$selected_filter_option = null;
	$valid_filter_options = odm_get_filter_options();

	if (array_key_exists(ODM_FIELD_NAME, $_GET)) {
		$selected_filter_option = sanitize_text_field($_GET[ODM_FIELD_NAME]);
	}

	if (empty($selected_filter_option) || !array_key_exists($selected_filter_option, $valid_filter_options)) {
		$selected_filter_option = 'no-filter';
	}

	return $selected_filter_option;
}

function odm_render_drop_down_filter_options()
{
	if (!odm_is_on_media_admin_page()) {
		return; // We're not on the list-view media page in the back-end.
	}

	printf('<select name="%s">', esc_attr(ODM_FIELD_NAME));

	$selected_filter_option = odm_get_filter_from_query_args();
	$valid_filter_options = odm_get_filter_options();
	foreach ($valid_filter_options as $value => $label) {
		$props = ($selected_filter_option == $value) ? 'selected' : '';
		printf('<option value="%s" %s>%s</option>', esc_attr($value), $props, esc_html($label));
	}

	echo '</select>';
}
add_action('restrict_manage_posts', 'odm_render_drop_down_filter_options');

// function odm_pre_get_posts($query)
// {
// 	if (!odm_is_on_media_admin_page() || !$query->is_main_query()) {
// 		return;
// 	}

// 	$selected_filter_option = odm_get_filter_from_query_args();
// 	if (empty($selected_filter_option) || $selected_filter_option == 'no-filter') {
// 		return;
// 	}

// 	$img_alt_meta_query = null;
// 	if ($selected_filter_option == 'only-with-alt') {
// 		$img_alt_meta_query = array('key' => '_wp_attachment_image_alt', 'compare' => 'EXISTS');
// 	} elseif ($selected_filter_option == 'only-without-alt') {
// 		$img_alt_meta_query = array('key' => '_wp_attachment_image_alt', 'compare' => 'NOT EXISTS');
// 	}

// 	if ($img_alt_meta_query) {
// 		$meta_query = $query->get('meta_query') ?? array('relation' => 'AND');
// 		$meta_query[] = $img_alt_meta_query;
// 		$query->set('meta_query', $meta_query);
// 		$query->set('post_mime_type', ODM_IMAGE_MIME_TYPES);
// 	}
// }

function odm_pre_get_posts($query)
{
	if (!odm_is_on_media_admin_page()) {
		// We're not on the list-view media page in the back-end.
		return;
	}

	if (!$query->is_main_query()) {
		// We're not on the main query, so don't do anything.
		return;
	}

	$selected_filter_option = odm_get_filter_from_query_args();

	if (empty($selected_filter_option)) {
		// The user hasn't chosen an IMG ALT Text filter option.
		return;
	}

	if ($selected_filter_option == 'no-filter') {
		// The user has selected the "no-filter" option, so don't do anything.
		return;
	}

	$img_alt_meta_query = null;

	if ($selected_filter_option == 'only-with-alt') {
		$img_alt_meta_query = array(
			'key' => '_wp_attachment_image_alt',
			'compare' => 'EXISTS',
		);
	} elseif ($selected_filter_option == 'only-without-alt') {
		$img_alt_meta_query = array(
			'key' => '_wp_attachment_image_alt',
			'compare' => 'NOT EXISTS',
		);
	}

	if (!empty($img_alt_meta_query)) {
		$meta_query = $query->get('meta_query');

		// Ensure that meta_query is an array
		if (!is_array($meta_query)) {
			$meta_query = array();
		}

		$meta_query[] = $img_alt_meta_query;

		// Set the query's "meta_query" to our filter.
		$query->set('meta_query', $meta_query);

		// We also want to only include posts (attachments) that have an image
		// in "post_mime_type" like this:
		$query->set('post_mime_type', ODM_IMAGE_MIME_TYPES);
	}
}

add_action('pre_get_posts', 'odm_pre_get_posts');

function odm_admin_enqueue_scripts($hook_suffix)
{
	if (odm_is_on_media_admin_page()) {
		$base_uri = plugin_dir_url(__FILE__);
		$version = '1.0';

		wp_enqueue_style('odm-admin', $base_uri . 'assets/odm-alt-filter-admin.css', null, $version);
		wp_enqueue_script('odm-admin-js', $base_uri . 'assets/odm-alt-filter-admin.js', array(), $version, true);
		wp_localize_script('odm-admin-js', 'odmAltFilter', array('nonce' => wp_create_nonce('save_odm_alt_text_nonce')));
	}
}
add_action('admin_enqueue_scripts', 'odm_admin_enqueue_scripts', 10, 1);

function odm_manage_media_columns($columns)
{
	$columns['alt-text-status'] = 'ALT Text';
	return $columns;
}
add_filter('manage_media_columns', 'odm_manage_media_columns');

function odm_manage_media_custom_column($column_name, $post_id)
{
	if ($column_name != 'alt-text-status') {
		return;
	}

	$alt_text = trim(strval(get_post_meta($post_id, '_wp_attachment_image_alt', true)));
	printf('<input type="text" data-post-id="%d" class="odm-alt-text-input" value="%s" />', $post_id, esc_attr($alt_text));
	if (!empty($alt_text)) {
		printf('<span class="dashicons dashicons-yes-alt" title="%s"></span>', esc_attr($alt_text));
	} else {
		echo '<span class="dashicons dashicons-warning"></span>';
	}
}
add_filter('manage_media_custom_column', 'odm_manage_media_custom_column', 10, 2);

function odm_save_alt_text()
{
	check_ajax_referer('save_odm_alt_text_nonce', 'nonce') || wp_send_json_error('Invalid nonce');

	$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
	$alt_text = isset($_POST['alt_text']) ? sanitize_text_field($_POST['alt_text']) : '';

	update_post_meta($post_id, '_wp_attachment_image_alt', $alt_text);

	$response = [
		'isEmpty' => empty($alt_text)
	];

	wp_send_json_success($response);
}

add_action('wp_ajax_save_odm_alt_text', 'odm_save_alt_text');

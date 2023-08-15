<?php

/**
 *  * Plugin Name: odm-alt-filter
 *   * Description: Adds a filter to the gallery to allow for filtering by missing alt tags
 *    * Version: 2023.08.14
 *     */

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


/**
 * If we're rendering the list-view media library page, add a drop-down list
 * to the filter bar with our IMG ALT filter options.
 */
function odm_render_drop_down_filter_options()
{
	if (!odm_is_on_media_admin_page()) {
		// We're not on the list-view media page in the back-end.
	} else {
		printf('<select name="%s">', esc_attr(ODM_FIELD_NAME));

		$selected_filter_option = odm_get_filter_from_query_args();
		$valid_filter_options = odm_get_filter_options();
		foreach ($valid_filter_options as $value => $label) {
			$props = '';

			if ($selected_filter_option == $value) {
				$props = 'selected';
			}

			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr($value),
				$props,
				esc_html($label)
			);
		}

		echo '</select>';
	}
}
add_action('restrict_manage_posts', 'odm_render_drop_down_filter_options');

/**
 * Be careful in here. This function runs on every page for every WordPress
 * query. We only want to adjust the query if we're on the media page and the
 * query that's running is the main query - i.e. the query that returns all the
 * media attachments.
 */
function odm_pre_get_posts($query)
{
	if (!odm_is_on_media_admin_page()) {
		// We're not on the list-view media page in the back-end.
	} elseif (!$query->is_main_query()) {
		// We're not on the main query, so don't do anything.
	} elseif (empty($selected_filter_option = odm_get_filter_from_query_args())) {
		// The user hasn't chosen an IMG ALT Text filter option.
	} elseif ($selected_filter_option == 'no-filter') {
		// The user has selected the "no-filter" option, so don't do anything.
	} else {
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
		} else {
			// ...
		}

		if (!empty($img_alt_meta_query)) {
			$meta_query = $query->get('meta_query');

			if (!is_array($meta_query)) {
				$meta_query = array(
					'relation' => 'AND',
				);
			}

			$meta_query[] = $img_alt_meta_query;

			// Set the query's "meta_query" to n=our filter.
			$query->set('meta_query', $meta_query);

			// We also want to only include posts (attachments) that have an image
			// in "post_mime_type" like this:
			$query->set('post_mime_type', ODM_IMAGE_MIME_TYPES);
		}
	}
}
add_action('pre_get_posts', 'odm_pre_get_posts');


function odm_admin_enqueue_scripts($hook_suffix)
{
	if (odm_is_on_media_admin_page()) {
		$base_uri = plugin_dir_url(__FILE__);
		$version = '1.0'; // You can dynamically generate this if you have a versioning system.

		wp_enqueue_style(
			'odm-admin',
			$base_uri . 'assets/odm-alt-filter-admin.css',
			null,
			$version
		);
	}
}
add_action('admin_enqueue_scripts', 'odm_admin_enqueue_scripts', 10, 1);

/**
 * Add a custom column for IMG ALT Text present/missing.
 */
function odm_manage_media_columns($columns)
{
	$columns['alt-text-status'] = 'ALT Text';

	return $columns;
}
add_filter('manage_media_columns', 'odm_manage_media_columns');

/**
 * This is called for each image in the media library list/table.
 */
function odm_manage_media_custom_column($column_name, $post_id)
{
	if ($column_name == 'alt-text-status') {
		if (!empty($alt_text = trim(strval(get_post_meta($post_id, '_wp_attachment_image_alt', true))))) {
			printf(
				'<span class="dashicons dashicons-yes-alt" title="%s"></span>',
				esc_attr($alt_text)
			);
		} else {
			echo '<span class="dashicons dashicons-warning"></span>';
		}
	}
}
add_filter('manage_media_custom_column', 'odm_manage_media_custom_column', 10, 2);

<?php
/*
Plugin Name: CodingBunny Image Organizer
Description: An add-on for CodingBunny Image Optimizer to organize images in the Media Library with categories.
Version: 1.2.1
Requires at least: 6.0
Requires PHP: 8.0
Author: CodingBunny
Text Domain: coding-bunny-image-organizer
Domain Path: /languages
License: GPLv2 or later
Requires Plugins: coding-bunny-image-optimizer-lite
Update URI: false
*/

if (!defined('ABSPATH')) exit;

if (!defined('CBORG_PLUGIN_BASENAME')) define('CBORG_PLUGIN_BASENAME', plugin_basename(__FILE__));

require_once __DIR__ . '/includes/class-cborg.php';

class CBORG_AJAX_Handler {

    public function __construct() {
        add_action('load-upload.php', [$this, 'handle_load_upload']);
        add_action('wp_ajax_cborg_sort_cats', [$this, 'handle_sort_cats']);
        add_action('wp_ajax_cborg_delete_cat', [$this, 'handle_delete_cat']);
        add_action('wp_ajax_cborg_copy_cat', [$this, 'handle_copy_cat']);
    }

    public function handle_load_upload() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['cborg-ajax']) && $_GET['cborg-ajax'] == '1') {
            add_action('admin_footer', function() {
                exit;
            }, 0);
        }
    }

    public function handle_sort_cats() {
        $this->check_nonce('cborg_set_terms');
        if (!$this->user_can_manage()) {
            wp_send_json_error('Permission denied');
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $order = isset($_POST['order']) ? array_map('sanitize_text_field', (array)wp_unslash($_POST['order'])) : [];
        if (empty($order)) {
            wp_send_json_error('No order');
        }
        foreach ($order as $pos => $slug) {
            $term = get_term_by('slug', $slug, 'image_category');
            if ($term) {
                update_term_meta($term->term_id, 'cborg_order', $pos);
            }
        }
        wp_send_json_success();
    }

    public function handle_delete_cat() {
    $this->check_nonce('cborg_set_terms');
    // phpcs:ignore WordPress.Security.NonceVerification.Missing
    $cat_id = isset($_POST['category_id']) ? intval(wp_unslash($_POST['category_id'])) : 0;
    if (!$cat_id) {
        wp_send_json_error(['message' => 'Missing ID']);
    }

    $attachments = get_posts([
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'posts_per_page' => -1,
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
        'tax_query' => [[
            'taxonomy' => 'image_category',
            'field'    => 'term_id',
            'terms'    => $cat_id,
            'include_children' => false,
        ]],
        'fields' => 'ids',
    ]);

    $deleted = wp_delete_term($cat_id, 'image_category');
    if (is_wp_error($deleted)) {
        wp_send_json_error(['message' => $deleted->get_error_message()]);
    }

    $uncat = get_term_by('slug', CodingBunny_Image_Organizer::UNCATEGORIZED_SLUG, 'image_category');
    if ($uncat && !empty($attachments)) {
        foreach ($attachments as $aid) {
            wp_set_object_terms($aid, [$uncat->term_id], 'image_category', false);
        }
    }

    wp_send_json_success(['message' => 'Category deleted. All images have been assigned to Uncategorized.']);
}

    public function handle_copy_cat() {
        $this->check_nonce('cborg_set_terms');
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $cat_id = isset($_POST['category_id']) ? intval(wp_unslash($_POST['category_id'])) : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $new_name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        if (!$cat_id || !$new_name) {
            wp_send_json_error(['message' => 'Missing ID or name']);
        }
        $cat = get_term($cat_id, 'image_category');
        if (!$cat || is_wp_error($cat)) {
            wp_send_json_error(['message' => 'Category not found']);
        }
        $args = [
            'description' => $cat->description,
            'parent'      => $cat->parent,
            'slug'        => wp_unique_term_slug(sanitize_title($new_name), 'image_category'),
        ];
        $new_cat = wp_insert_term($new_name, 'image_category', $args);
        if (is_wp_error($new_cat)) {
            wp_send_json_error(['message' => $new_cat->get_error_message()]);
        }
        $this->copy_term_meta($cat_id, $new_cat['term_id']);
        wp_send_json_success(['message' => 'Category duplicated']);
    }

    private function check_nonce($action) {
        check_ajax_referer($action, 'nonce');
    }

    private function user_can_manage() {
        return current_user_can('manage_categories');
    }

    private function copy_term_meta($from_id, $to_id) {
        $term_metas = get_term_meta($from_id);
        foreach ($term_metas as $meta_key => $meta_values) {
            foreach ($meta_values as $val) {
                update_term_meta($to_id, $meta_key, $val);
            }
        }
    }
}

new CBORG_AJAX_Handler();

CodingBunny_Image_Organizer::instance();
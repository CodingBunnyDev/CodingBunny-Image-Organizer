<?php
if (!defined('ABSPATH')) exit;

trait CBORG_Ajax {
    public function register_ajax_hooks() {
        add_action('wp_ajax_cborg_set_terms', [$this, 'ajax_set_terms']);
        add_action('wp_ajax_cborg_get_existing_terms', [$this, 'ajax_get_existing_terms']);
        add_action('wp_ajax_cborg_export_zip', [$this, 'ajax_export_zip']);
    }

    public function ajax_set_terms(): void {
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        check_ajax_referer('cborg_set_terms', 'nonce');
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $ids = isset($_POST['ids']) ? array_map('intval', wp_unslash((array)$_POST['ids'])) : [];
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $cat = isset($_POST['category']) ? intval(wp_unslash($_POST['category'])) : 0;
        if (empty($ids)) {
            wp_send_json_error(['message' => 'No images selected']);
        }
        foreach ($ids as $id) {
            if ($cat) {
                wp_set_object_terms($id, [$cat], 'image_category');
            } else {
                $uncat = get_term_by('slug', self::UNCATEGORIZED_SLUG, 'image_category');
                if ($uncat) {
                    wp_set_object_terms($id, [$uncat->term_id], 'image_category', false);
                }
            }
        }
        wp_send_json_success(['message' => 'Categories assigned!']);
    }

    public function ajax_get_existing_terms(): void {
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        check_ajax_referer('cborg_get_terms', 'nonce');
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $post_ids = isset($_POST['post_ids']) ? array_map('intval', wp_unslash((array)$_POST['post_ids'])) : [];
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field(wp_unslash($_POST['taxonomy'])) : '';
        if (empty($post_ids) || !in_array($taxonomy, ['image_category', 'image_tag'], true)) {
            wp_send_json_error(['message' => 'Invalid parameters']);
        }
        $common_terms = [];
        $all_terms_count = [];
        foreach ($post_ids as $post_id) {
            $terms = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'ids']);
            if (!is_wp_error($terms)) {
                foreach ($terms as $term_id) {
                    if (!isset($all_terms_count[$term_id])) {
                        $all_terms_count[$term_id] = 0;
                    }
                    $all_terms_count[$term_id]++;
                }
            }
        }
        $total_images = count($post_ids);
        foreach ($all_terms_count as $term_id => $count) {
            if ($count === $total_images) {
                $common_terms[] = intval($term_id);
            }
        }
        wp_send_json_success([
            'common_terms' => $common_terms,
            'term_counts' => $all_terms_count,
            'total_images' => $total_images
        ]);
    }

    public function ajax_export_zip(): void {
        if (!current_user_can('upload_files')) {
            wp_die('Not allowed');
        }
        check_admin_referer('cborg_set_terms', 'nonce');

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $cat_slug = isset($_POST['category']) ? sanitize_text_field(wp_unslash($_POST['category'])) : '';
        if (empty($cat_slug)) {
            wp_die('No category');
        }

        $cat = get_term_by('slug', $cat_slug, 'image_category');
        if (!$cat) {
            wp_die('Category not found');
        }

        $descendants = get_term_children($cat->term_id, 'image_category');
        $all_term_ids = array_unique(array_merge([$cat->term_id], $descendants));
       
        $attachments = get_posts([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
            'tax_query' => [[
                'taxonomy' => 'image_category',
                'field'    => 'term_id',
                'terms'    => $all_term_ids,
                'include_children' => false,
            ]],
            'fields' => 'ids',
        ]);

        if (empty($attachments)) {
            wp_die('No images found');
        }

        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        global $wp_filesystem;

        $tmp_file = wp_tempnam('cborgzip_');
        $zip = new ZipArchive();
        if ($zip->open($tmp_file, ZipArchive::OVERWRITE) !== true) {
            wp_die('Cannot create zip file');
        }

        foreach ($attachments as $aid) {
            $file_path = get_attached_file($aid);
            if ($wp_filesystem->exists($file_path)) {
                $zip->addFile($file_path, wp_basename($file_path));
            }
        }

        $zip->close();

        $zip_contents = $wp_filesystem->get_contents($tmp_file);
        if ($zip_contents === false) {
            wp_delete_file($tmp_file);
            wp_die('Failed to read zip file');
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="images-' . esc_attr($cat_slug) . '.zip"');
        header('Content-Length: ' . strlen($zip_contents));
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $zip_contents;

        wp_delete_file($tmp_file);

        exit;
    }
}
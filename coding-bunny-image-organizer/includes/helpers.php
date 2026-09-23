<?php
if (!defined('ABSPATH')) exit;

trait CBORG_Helpers {
    private function get_unique_attachments_for_term_and_descendants($term_id, $taxonomy = 'image_category'): array {
        $descendants = get_term_children($term_id, $taxonomy);
        $all_term_ids = array_unique(array_merge([$term_id], $descendants));
        $args = [
            'post_type'      => 'attachment',
            'post_status'    => 'any',
            'posts_per_page' => -1,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
            'tax_query'      => [
                [
                    'taxonomy' => $taxonomy,
                    'field'    => 'term_id',
                    'terms'    => $all_term_ids,
                    'include_children' => false,
                ]
            ],
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            'meta_query' => [
                [
                    'key'     => '_wp_trash_meta_status',
                    'compare' => 'NOT EXISTS',
                ]
            ],
            'fields' => 'ids',
        ];
        $query = new WP_Query($args);
        return array_unique($query->posts);
    }
}
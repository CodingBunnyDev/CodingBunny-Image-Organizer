<?php
if (!defined('ABSPATH')) exit;

trait CBORG_Taxonomy {
    public function register_all_hooks() {
        add_action('init', [$this, 'register_taxonomies']);
        add_action('add_attachment', [$this, 'assign_uncategorized_on_upload']);
        register_activation_hook(CBORG_PLUGIN_DIR . '/coding-bunny-image-organizer.php', [$this, 'on_activation']);
    }

    public function register_taxonomies(): void {
        register_taxonomy('image_category', 'attachment', [
            'label'        => __('Categories', 'coding-bunny-image-organizer'),
            'labels'       => [
                'name'          => __('Categories', 'coding-bunny-image-organizer'),
                'singular_name' => __('Category', 'coding-bunny-image-organizer'),
                'search_items'  => __('Search Categories', 'coding-bunny-image-organizer'),
                'all_items'     => __('All Categories', 'coding-bunny-image-organizer'),
                'edit_item'     => __('Edit Category', 'coding-bunny-image-organizer'),
                'update_item'   => __('Update Category', 'coding-bunny-image-organizer'),
                'add_new_item'  => __('Add New Category', 'coding-bunny-image-organizer'),
                'new_item_name' => __('New Category Name', 'coding-bunny-image-organizer'),
                'menu_name'     => __('Categories', 'coding-bunny-image-organizer'),
            ],
            'public'            => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'hierarchical'      => true,
            'rewrite'           => false,
        ]);
        if (!term_exists(self::UNCATEGORIZED_NAME, 'image_category')) {
            wp_insert_term(
                self::UNCATEGORIZED_NAME,
                'image_category',
                [
                    'slug' => self::UNCATEGORIZED_SLUG,
                    'description' => __('The default category for images not assigned to any other category.', 'coding-bunny-image-organizer'),
                ]
            );
        }
    }

    public function assign_uncategorized_on_upload($post_ID): void {
        $post = get_post($post_ID);
        if ($post && $post->post_type === 'attachment') {
            $terms = wp_get_object_terms($post_ID, 'image_category', ['fields' => 'ids']);
            if (empty($terms)) {
                $uncat = get_term_by('slug', self::UNCATEGORIZED_SLUG, 'image_category');
                if ($uncat) {
                    wp_set_object_terms($post_ID, [$uncat->term_id], 'image_category', false);
                }
            }
        }
    }

    public function on_activation(): void {
        if (!taxonomy_exists('image_category')) {
            $this->register_taxonomies();
        }
        if (!term_exists(self::UNCATEGORIZED_NAME, 'image_category')) {
            wp_insert_term(
                self::UNCATEGORIZED_NAME,
                'image_category',
                [
                    'slug' => self::UNCATEGORIZED_SLUG,
                    'description' => __('The default category for images not assigned to any other category.', 'coding-bunny-image-organizer'),
                ]
            );
        }
        $uncat = get_term_by('slug', self::UNCATEGORIZED_SLUG, 'image_category');
        if ($uncat) {
            $args = [
                'post_type'      => 'attachment',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
                'tax_query'      => [
                    [
                        'taxonomy' => 'image_category',
                        'operator' => 'NOT EXISTS',
                    ]
                ],
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                'meta_query' => [
                    [
                        'key'     => '_wp_trash_meta_status',
                        'compare' => 'NOT EXISTS',
                    ]
                ],
            ];
            $query = new WP_Query($args);
            foreach ($query->posts as $aid) {
                wp_set_object_terms($aid, [$uncat->term_id], 'image_category', false);
            }
        }
    }
}
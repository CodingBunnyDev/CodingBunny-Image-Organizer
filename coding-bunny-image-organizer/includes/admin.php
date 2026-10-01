<?php
if (!defined('ABSPATH')) exit;

trait CBORG_Admin {
	private $uncat_term_id = null;

	public function register_admin_hooks() {
		add_action('admin_init', [$this, 'maybe_migrate_color_meta']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
		add_action('deactivated_plugin', [$this, 'optimizer_dependency_check']);
		add_action('init', [$this, 'load_textdomain']);
		add_filter('parse_query', [$this, 'parse_query_upload_page']);
		add_filter('ajax_query_attachments_args', [$this, 'filter_ajax_query_attachments_args']);
		add_filter('attachment_fields_to_save', [$this, 'save_attachment_fields'], 10, 2);
		add_filter('image_category_row_actions', [$this, 'prevent_uncategorized_delete'], 10, 2);
		add_action('image_category_add_form_fields', [$this, 'add_category_color_field']);
		add_action('image_category_edit_form_fields', [$this, 'edit_category_color_field']);
		add_action('created_image_category', [$this, 'save_category_color_on_create']);
		add_action('edited_image_category', [$this, 'save_category_color_on_edit']);
		add_filter('manage_edit-image_category_columns', [$this, 'add_color_column']);
		add_filter('manage_image_category_custom_column', [$this, 'render_color_column'], 10, 3);
		add_filter('get_terms_args', [$this, 'exclude_uncategorized_from_admin_table'], 20, 2);
		add_action('pre_get_posts', [$this, 'apply_protected_virtual_filter']);

		if (!class_exists('CodingBunnyImageOptimizer')) {
			if (is_admin()) {
				add_action('admin_notices', [$this, 'show_missing_optimizer_notice']);
				add_action('admin_init', [$this, 'deactivate_if_no_optimizer']);
			}
			return;
		}
	}

	public function maybe_migrate_color_meta(): void {
		if ('1' === get_option('cborg_color_meta_migrated')) {
			return;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$term_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT tm.term_id FROM {$wpdb->termmeta} tm INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = tm.term_id WHERE tm.meta_key = %s AND tt.taxonomy = %s",
				CodingBunny_Image_Organizer::LEGACY_COLOR_META_KEY,
				'image_category'
			)
		);

		foreach ((array) $term_ids as $term_id) {
			$term_id = (int) $term_id;
			$color   = sanitize_hex_color((string) get_term_meta($term_id, CodingBunny_Image_Organizer::LEGACY_COLOR_META_KEY, true));
			if ($color && '' === (string) get_term_meta($term_id, CodingBunny_Image_Organizer::COLOR_META_KEY, true)) {
				update_term_meta($term_id, CodingBunny_Image_Organizer::COLOR_META_KEY, $color);
			}
			delete_term_meta($term_id, CodingBunny_Image_Organizer::LEGACY_COLOR_META_KEY);
		}

		update_option('cborg_color_meta_migrated', '1', false);
	}

	public function exclude_uncategorized_from_admin_table($args, $taxonomies) {
    global $pagenow;
    if (
        is_admin() &&
        $pagenow === 'edit-tags.php' &&
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
        isset($_GET['taxonomy']) && $_GET['taxonomy'] === 'image_category' &&
        in_array('image_category', (array)$taxonomies, true) &&
        empty($args['search']) &&
        empty($args['include']) &&
        empty($args['parent'])
    ) {
        static $uncat_id = null;
        if ($uncat_id === null) {
            remove_filter('get_terms_args', [$this, 'exclude_uncategorized_from_admin_table'], 20);
            $uncat = get_term_by('slug', CodingBunny_Image_Organizer::UNCATEGORIZED_SLUG, 'image_category');
            add_filter('get_terms_args', [$this, 'exclude_uncategorized_from_admin_table'], 20, 2);
            $uncat_id = $uncat ? $uncat->term_id : null;
        }
        if ($uncat_id) {
            if (isset($args['exclude']) && is_array($args['exclude'])) {
                $args['exclude'][] = $uncat_id;
            } else {
				// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
                $args['exclude'] = [$uncat_id];
            }
        }
    }
    return $args;
}

	public function enqueue_admin_assets($hook): void {
		if (in_array($hook, ['upload.php', 'media-upload-popup'])) {
			wp_enqueue_script('jquery-ui-draggable');
			wp_enqueue_script('jquery-ui-droppable');
			wp_enqueue_style('cborg-ui', plugin_dir_url(__FILE__).'../assets/cborg-styles.css', [], self::VERSION);

			wp_enqueue_script('cborg-sidebar', plugin_dir_url(__FILE__).'../assets/cborg-sidebar.js', ['jquery', 'media-views'], self::VERSION, true);
			wp_enqueue_script('cborg-interactions', plugin_dir_url(__FILE__).'../assets/cborg-interactions.js', ['cborg-sidebar'], self::VERSION, true);
			wp_enqueue_script('cborg-dragdrop', plugin_dir_url(__FILE__).'../assets/cborg-dragdrop.js', ['cborg-sidebar', 'cborg-interactions'], self::VERSION, true);
			wp_enqueue_script('cborg-tooltip', plugin_dir_url(__FILE__).'../assets/cborg-tooltip.js', ['cborg-sidebar', 'cborg-interactions'], self::VERSION, true);

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$old_get = $_GET;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$old_request = $_REQUEST;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if (isset($_GET['image_category'])) unset($_GET['image_category']);
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if (isset($_REQUEST['image_category'])) unset($_REQUEST['image_category']);

			$categories = get_terms(['taxonomy' => 'image_category', 'hide_empty' => false, 'orderby' => 'name']);
			if ($categories && !is_wp_error($categories)) {
				usort($categories, function($a, $b) {
					if ($a->slug === 'uncategorized') return 1;
					if ($b->slug === 'uncategorized') return -1;
					$ordA = get_term_meta($a->term_id, 'cborg_order', true);
					$ordB = get_term_meta($b->term_id, 'cborg_order', true);
					$ordA = ($ordA === '' ? 9999 : intval($ordA));
					$ordB = ($ordB === '' ? 9999 : intval($ordB));
					return $ordA - $ordB;
				});

				$cat_data = [];
				foreach ($categories as $c) {
					$unique_attachments = $this->get_unique_attachments_for_term_and_descendants($c->term_id, 'image_category');
					$cat_data[] = [
						'id'     => (int) $c->term_id,
						'parent' => (int) $c->parent,
						'slug'   => sanitize_text_field($c->slug),
						'name'   => esc_html($c->name),
						'count'  => count($unique_attachments),
						'color'  => sanitize_hex_color(get_term_meta($c->term_id, CodingBunny_Image_Organizer::COLOR_META_KEY, true)) ?: '#ff66b2',
					];
				}
			} else {
				$cat_data = [];
			}

			$protector_active = $this->is_protector_active();
			$protected_count  = $protector_active ? $this->get_protected_attachments_count() : 0;

			$_GET = $old_get;
			$_REQUEST = $old_request;

			wp_localize_script('cborg-sidebar', 'CBORG_AJAX', [
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce'    => wp_create_nonce('cborg_set_terms'),
				'get_terms_nonce' => wp_create_nonce('cborg_get_terms'),
				'bulk_modify_nonce' => wp_create_nonce('cborg_bulk_modify_terms')
			]);

			wp_localize_script('cborg-sidebar', 'CBORG_VARS', [
				'i18n' => [
					'allCategories' => __('All Files', 'coding-bunny-image-organizer'),
					'filterByCat'   => __('Categories', 'coding-bunny-image-organizer'),
					'uncategorized' => __('Uncategorized', 'coding-bunny-image-organizer'),
					'noImages'      => __('No images selected!', 'coding-bunny-image-organizer'),
					'ajaxError'     => __('AJAX error!', 'coding-bunny-image-organizer'),
					'assignmentOk'  => __('Assignment completed!', 'coding-bunny-image-organizer'),
					'loading'       => __('Loading...', 'coding-bunny-image-organizer'),
					'currentlyAssignedAll' => __('Currently assigned to all images', 'coding-bunny-image-organizer'),
					'currentlyAssignedTo' => __('Currently assigned to', 'coding-bunny-image-organizer'),
					'images' => __('images', 'coding-bunny-image-organizer'),
					'notAssignedAny' => __('Not assigned to any image', 'coding-bunny-image-organizer'),
					'manageCategories' => __('Manage Categories', 'coding-bunny-image-organizer'),
					'selectedImages' => __('Selected images:', 'coding-bunny-image-organizer'),
					'loadingExistingCategories' => __('Loading existing categories...', 'coding-bunny-image-organizer'),
					'loadingExistingTags' => __('Loading existing tags...', 'coding-bunny-image-organizer'),
					'toggleStates' => __('Toggle States:', 'coding-bunny-image-organizer'),
					'removeState' => __('REMOVE', 'coding-bunny-image-organizer'),
					'unchangedState' => __('UNCHANGED', 'coding-bunny-image-organizer'),
					'addState' => __('ADD', 'coding-bunny-image-organizer'),
					'removeDescription' => __('Remove from all selected images', 'coding-bunny-image-organizer'),
					'unchangedDescription' => __('Keep current assignments', 'coding-bunny-image-organizer'),
					'addDescription' => __('Add to all selected images', 'coding-bunny-image-organizer'),
					'applyChanges' => __('Apply Changes', 'coding-bunny-image-organizer'),
					'cancel' => __('Cancel', 'coding-bunny-image-organizer'),
					'noCategoriesAvailable' => __('No categories available. Create some categories first.', 'coding-bunny-image-organizer'),
					'protected' => __('Protected', 'coding-bunny-image-organizer'),
					'newCat' => __('New Category', 'coding-bunny-image-organizer'),
					'editCat' => __('Edit Category', 'coding-bunny-image-organizer'),
					'copyCat' => __('Copy Category', 'coding-bunny-image-organizer'),
					'deleteCat' => __('Delete Category', 'coding-bunny-image-organizer'),
					'exportCat' => __('Export Selected Category', 'coding-bunny-image-organizer'),
				],
				'categories' => $cat_data,
				'protectorActive'   => $protector_active,
				'protectedCount'    => $protected_count,
				'protectedSlug'     => 'protected',
				'initial'    => [
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended
					'category' => isset($_GET['image_category']) ? sanitize_text_field(wp_unslash($_GET['image_category'])) : '',
				],
			]);
		}
	}

	public function optimizer_dependency_check($plugin): void {
		if (defined('CBIO_PLUGIN_FILE') && plugin_basename(CBIO_PLUGIN_FILE) === $plugin) {
			deactivate_plugins(CBORG_PLUGIN_BASENAME);
			add_action('admin_notices', function() {
				echo '<div class="notice notice-warning"><p>'
					. esc_html__( 'CodingBunny Image Organizer has been deactivated because CodingBunny Image Optimizer is no longer active.', 'coding-bunny-image-organizer' )
						. '</p></div>';
			});
		}
	}

	public function show_missing_optimizer_notice(): void {
		echo '<div class="notice notice-error"><p>'
			. esc_html__( 'CodingBunny Image Organizer requires CodingBunny Image Optimizer to be installed and active. Please install and activate it first.', 'coding-bunny-image-organizer' )
				. '</p></div>';
	}

	public function deactivate_if_no_optimizer(): void {
		if (
			current_user_can('activate_plugins') &&
			is_plugin_active(CBORG_PLUGIN_BASENAME)
		) {
			deactivate_plugins(CBORG_PLUGIN_BASENAME);
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if (isset($_GET['activate'])) {
				unset($_GET['activate']);
			}
		}
	}

	public function load_textdomain(): void {
		// phpcs:ignore
		load_plugin_textdomain(
			'coding-bunny-image-organizer',
			false,
			dirname(plugin_basename(__FILE__)) . '/../languages/'
		);
	}

	public function parse_query_upload_page($wp_query): void {
		global $pagenow;
		if ($pagenow !== 'upload.php') return;
		foreach(['image_category'] as $tax) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if (!empty($_GET[$tax]) && $_GET[$tax] !== 'protected') {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$wp_query->query_vars[$tax] = sanitize_text_field(wp_unslash($_GET[$tax]));
			}
		}
	}

	public function filter_ajax_query_attachments_args($query) {
		$cat = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if (isset($_REQUEST['query']) && is_array($_REQUEST['query'])) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$request_query = array_map('sanitize_text_field', (array)wp_unslash($_REQUEST['query']));
			$cat = isset($request_query['image_category']) ? $request_query['image_category'] : '';
		}
		if ($cat === 'protected' && $this->is_protector_active()) {
			$query['image_category'] = '';
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			$query['tax_query'] = [];
			$query['post_type'] = 'attachment';
			$query['post_status'] = 'inherit';
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			$query['meta_query'] = [[
				'key'   => '_cbio_cbip_protection',
				'value' => '1',
			]];
		} elseif (!empty($cat)) {
			$tax_query = [[
				'taxonomy' => 'image_category',
				'field'    => 'slug',
				'terms'    => $cat
			]];
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			$query['tax_query'] = $tax_query;
		}
		return $query;
	}

	public function save_attachment_fields($post, $attachment) {
		if (
			isset($_POST['cborg_set_terms_nonce']) &&
			wp_verify_nonce(
				isset($_POST['cborg_set_terms_nonce']) ? sanitize_text_field(wp_unslash($_POST['cborg_set_terms_nonce'])) : '',
				'cborg_set_terms'
			)
		) {
			foreach (['image_category'] as $tax) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if (isset($_REQUEST[$tax])) {
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$terms = array_map('intval', (array)wp_unslash($_REQUEST[$tax]));
					if (empty($terms)) {
						$uncat = get_term_by('slug', self::UNCATEGORIZED_SLUG, $tax);
						if ($uncat) {
							wp_set_object_terms($post['ID'], [$uncat->term_id], $tax);
						}
					} else {
						wp_set_object_terms($post['ID'], $terms, $tax);
					}
				}
			}
		}
		return $post;
	}

	public function prevent_uncategorized_delete($actions, $term) {
		if ($term->slug === self::UNCATEGORIZED_SLUG) {
			unset($actions['delete']);
		}
		return $actions;
	}

	public function add_category_color_field(): void {
		?>
		<div class="form-field term-color-wrap">
			<label for="term-color"><?php esc_html_e('Color', 'coding-bunny-image-organizer'); ?></label>
			<input name="term-color" id="term-color" type="color" value="#ff66b2" />
			<?php wp_nonce_field('save_image_category_color', 'image_category_color_nonce'); ?>
			<p class="description"><?php esc_html_e('Choose a color for this category', 'coding-bunny-image-organizer'); ?></p>
		</div>
		<?php
	}

	public function edit_category_color_field($term): void {
		$color = sanitize_hex_color(get_term_meta($term->term_id, CodingBunny_Image_Organizer::COLOR_META_KEY, true)) ?: '#ff66b2';
		?>
		<tr class="form-field term-color-wrap">
			<th scope="row"><label for="term-color"><?php esc_html_e('Color', 'coding-bunny-image-organizer'); ?></label></th>
			<td>
				<input name="term-color" id="term-color" type="color" value="<?php echo esc_attr($color); ?>" />
				<?php wp_nonce_field('save_image_category_color', 'image_category_color_nonce'); ?>
				<p class="description"><?php esc_html_e('Choose a color for this category', 'coding-bunny-image-organizer'); ?></p>
			</td>
		</tr>
		<?php
	}

	public function save_category_color_on_edit($term_id): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if (
			isset($_POST['image_category_color_nonce']) &&
			wp_verify_nonce(
				sanitize_text_field(wp_unslash($_POST['image_category_color_nonce'])),
				'save_image_category_color'
			)
		) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if (isset($_POST['term-color'])) {
				update_term_meta(
					$term_id,
					CodingBunny_Image_Organizer::COLOR_META_KEY,
					// phpcs:ignore WordPress.Security.NonceVerification.Missing
					sanitize_hex_color(wp_unslash($_POST['term-color']))
				);
			}
		}
	}
	
	public function save_category_color_on_create($term_id) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if (
			isset($_POST['image_category_color_nonce']) &&
			wp_verify_nonce(
				sanitize_text_field(wp_unslash($_POST['image_category_color_nonce'])),
				'save_image_category_color'
			)
		) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if (isset($_POST['term-color'])) {
				update_term_meta(
					$term_id,
					CodingBunny_Image_Organizer::COLOR_META_KEY,
					// phpcs:ignore WordPress.Security.NonceVerification.Missing
					sanitize_hex_color(wp_unslash($_POST['term-color']))
				);
			}
		}
	}
	
	private function is_protector_active(): bool {
		return class_exists('CBIP_Image_Protector');
	}

	private function get_protected_attachments_count(): int {
		if (!$this->is_protector_active()) return 0;
		$q = new WP_Query([
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => [[
				'key'   => '_cbio_cbip_protection',
				'value' => '1',
			]],
			'no_found_rows'  => false,
		]);
		return (int) $q->found_posts;
	}

	public function apply_protected_virtual_filter($query): void {
		if (wp_doing_ajax()) return;
		
		if (!is_admin() || !$query->is_main_query()) return;
		
		global $pagenow;
		if ($pagenow !== 'upload.php') return;
		
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$cat = isset($_GET['image_category']) ? sanitize_text_field(wp_unslash($_GET['image_category'])) : '';
		
		if ($cat !== 'protected') return;
		if (!$this->is_protector_active()) return;
		
		$query->set('image_category', '');
		$query->set('tax_query', []);
		
		$query->set('post_type', 'attachment');
		$query->set('post_status', 'inherit');
		$query->set('meta_query', [[
			'key'   => '_cbio_cbip_protection',
			'value' => '1',
		]]);
	}

	public function add_color_column($columns) {
		$columns['cborg_color'] = __('Color', 'coding-bunny-image-organizer');
		return $columns;
	}

	public function render_color_column($content, $column_name, $term_id) {
		if ($column_name === 'cborg_color') {
			$color = sanitize_hex_color(get_term_meta($term_id, CodingBunny_Image_Organizer::COLOR_META_KEY, true)) ?: '#ff66b2';
			$content = '<span style="display:inline-block;width:20px;height:20px;background:' . esc_attr($color) . ';border:1px solid #ccc;border-radius:50px;"></span>';
		}
		return $content;
	}
}
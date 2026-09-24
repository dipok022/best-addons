<?php
if (! defined('ABSPATH')) exit;

/**
 * Custom Widget List Page — matches the Master Addons list UI from the screenshots:
 * columns: Preview | Widget Name | Widget Category | Author | Shortcode
 * Top actions: Add New Widget | Import Widget
 * Each row: shortcode copy button, edit link, delete
 */
class Best_Addons_Widget_Builder_List_Page
{

	public static function render()
	{
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('Unauthorized', 'best-addons'));
		}

		// Handle delete action.
		if (
			isset($_GET['action'], $_GET['widget_id']) &&
			$_GET['action'] === 'delete' &&
			check_admin_referer('ba_delete_widget_' . absint($_GET['widget_id']))
		) {
			wp_delete_post(absint($_GET['widget_id']), true);
			wp_safe_redirect(admin_url('admin.php?page=ba-widget-builder&deleted=1'));
			exit;
		}

		// Handle import.
		if (
			isset($_FILES['ba_import_file']) &&
			check_admin_referer('ba_import_widget', 'ba_import_nonce')
		) {
			self::handle_import();
		}

		// Query widgets.
		$widgets = get_posts([
			'post_type'      => 'best_widget',
			'post_status'    => ['publish', 'draft'],
			'posts_per_page' => -1,
			'no_found_rows'  => false,
			'orderby'        => 'date',
			'order'          => 'DESC',
		]);

		$total    = count($widgets);
		$pub_count = 0;
		foreach ($widgets as $w) {
			if ($w->post_status === 'publish') $pub_count++;
		}

		// Notices.
		if (isset($_GET['saved'])) {
			echo '<div class="ba-notice ba-notice-success">' . esc_html__('Widget saved successfully.', 'best-addons') . '</div>';
		}
		if (isset($_GET['deleted'])) {
			echo '<div class="ba-notice ba-notice-success">' . esc_html__('Widget deleted.', 'best-addons') . '</div>';
		}
		if (isset($_GET['imported'])) {
			echo '<div class="ba-notice ba-notice-success">' . esc_html__('Widget imported successfully.', 'best-addons') . '</div>';
		}
?>
		<div class="ba-wb-page wrap">

			<!-- ── Page Header ───────────────────────────────────────────── -->
			<div class="ba-wb-page-header">
				<div class="ba-wb-page-header-left">
					<h1 class="ba-wb-page-title">
						<span class="ba-wb-logo">⚡</span>
						<?php esc_html_e('Best Addons Widgets', 'best-addons'); ?>
					</h1>
					<div class="ba-wb-count-tabs">
						<span class="ba-count-tab active">
							<?php echo esc_html(
								sprintf(
									/* translators: %d: total widget count */
									_n('All (%d)', 'All (%d)', $total, 'best-addons'),
									$total
								)
							); ?>
						</span>
						<span class="ba-count-tab">
							<?php echo esc_html(
								sprintf(
									/* translators: %d: published widget count */
									_n('Published (%d)', 'Published (%d)', $pub_count, 'best-addons'),
									$pub_count
								)
							); ?>
						</span>
					</div>
				</div>
				<div class="ba-wb-page-header-actions">
					<button type="button" id="ba-add-new-widget" class="button ba-btn-primary">
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e('Add New Widget', 'best-addons'); ?>
					</button>
					<button type="button" id="ba-import-widget-btn" class="button ba-btn-secondary">
						<span class="dashicons dashicons-upload"></span>
						<?php esc_html_e('Import Widget', 'best-addons'); ?>
					</button>
					<a href="https://master-addons.com/docs/widget-builder/" target="_blank" class="button ba-btn-ghost">
						<span class="dashicons dashicons-video-alt3"></span>
						<?php esc_html_e('Video Tutorial', 'best-addons'); ?>
					</a>
				</div>
			</div>

			<!-- ── Widget Table ──────────────────────────────────────────── -->
			<div class="ba-wb-table-wrap">
				<?php if (empty($widgets)) : ?>
					<div class="ba-wb-empty-state">
						<div class="ba-wb-empty-icon">🧩</div>
						<h3><?php esc_html_e('No widgets yet', 'best-addons'); ?></h3>
						<p><?php esc_html_e('Create your first custom Elementor widget with HTML, CSS, JS and dynamic controls.', 'best-addons'); ?></p>
						<button type="button" id="ba-add-new-widget-empty" class="button ba-btn-primary">
							<?php esc_html_e('+ Create Your First Widget', 'best-addons'); ?>
						</button>
					</div>
				<?php else : ?>
					<table class="ba-wb-table widefat">
						<thead>
							<tr>
								<th class="check-column"><input type="checkbox" id="ba-check-all"></th>
								<th class="ba-col-preview"><?php esc_html_e('Preview', 'best-addons'); ?></th>
								<th class="ba-col-name"><?php esc_html_e('Widget Name', 'best-addons'); ?> <span class="ba-sortable">↕</span></th>
								<th class="ba-col-category"><?php esc_html_e('Widget Category', 'best-addons'); ?> <span class="ba-sortable">↕</span></th>
								<th class="ba-col-author"><?php esc_html_e('Author', 'best-addons'); ?></th>
								<th class="ba-col-shortcode"><?php esc_html_e('Shortcode', 'best-addons'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($widgets as $widget) :
								$icon        = get_post_meta($widget->ID, '_ba_icon',     true) ?: 'dashicons-layout';
								$category    = get_post_meta($widget->ID, '_ba_category', true) ?: '';
								$controls    = get_post_meta($widget->ID, '_ba_controls', true) ?: [];
								$shortcode   = '[best_widget id="' . $widget->ID . '"]';
								$edit_url    = admin_url('admin.php?page=ba-widget-builder-edit&widget_id=' . $widget->ID);
								$delete_url  = wp_nonce_url(
									admin_url('admin.php?page=ba-widget-builder&action=delete&widget_id=' . $widget->ID),
									'ba_delete_widget_' . $widget->ID
								);
								$author      = get_userdata($widget->post_author);
								$author_name = $author ? $author->display_name : '—';
								$status_class = $widget->post_status === 'publish' ? 'ba-status-active' : 'ba-status-draft';
								$ctrl_count  = is_array($controls) ? count($controls) : 0;

								// Get a preview icon from the saved icon key.
								$is_dashicon = str_starts_with($icon, 'dashicons-');
								$is_eicon    = str_starts_with($icon, 'eicon-');
								$is_fa       = str_starts_with($icon, 'fa ') || str_starts_with($icon, 'fas ') || str_starts_with($icon, 'far ');
							?>
								<tr class="ba-wb-row <?php echo esc_attr($widget->post_status); ?>">
									<td class="check-column">
										<input type="checkbox" name="widget_ids[]" value="<?php echo esc_attr($widget->ID); ?>">
									</td>
									<td class="ba-col-preview">
										<a href="<?php echo esc_url($edit_url); ?>" class="ba-preview-thumb">
											<div class="ba-widget-icon-preview">
												<?php if ($is_dashicon) : ?>
													<span class="dashicons <?php echo esc_attr($icon); ?>"></span>
												<?php elseif ($is_eicon) : ?>
													<i class="<?php echo esc_attr($icon); ?>"></i>
												<?php elseif ($is_fa) : ?>
													<i class="<?php echo esc_attr($icon); ?>"></i>
												<?php else : ?>
													<span class="dashicons dashicons-layout"></span>
												<?php endif; ?>
											</div>
										</a>
									</td>
									<td class="ba-col-name">
										<a href="<?php echo esc_url($edit_url); ?>" class="ba-widget-name-link">
											<?php echo esc_html($widget->post_title); ?>
										</a>
										<div class="ba-row-actions">
											<a href="<?php echo esc_url($edit_url); ?>"><?php esc_html_e('Edit', 'best-addons'); ?></a>
											<span class="sep"> | </span>
											<a href="<?php echo esc_url($delete_url); ?>" class="ba-delete-link" onclick="return confirm('<?php esc_attr_e('Delete this widget? This cannot be undone.', 'best-addons'); ?>')">
												<?php esc_html_e('Delete', 'best-addons'); ?>
											</a>
											<span class="sep"> | </span>
											<a href="#" class="ba-export-single" data-id="<?php echo esc_attr($widget->ID); ?>">
												<?php esc_html_e('Export', 'best-addons'); ?>
											</a>
										</div>
										<div class="ba-widget-meta">
											<span class="ba-status-badge <?php echo esc_attr($status_class); ?>">
												<?php echo $widget->post_status === 'publish' ? esc_html__('Active', 'best-addons') : esc_html__('Draft', 'best-addons'); ?>
											</span>
											<span class="ba-ctrl-count">
												<?php echo esc_html($ctrl_count . ' ' . _n('control', 'controls', $ctrl_count, 'best-addons')); ?>
											</span>
										</div>
									</td>
									<td class="ba-col-category">
										<?php if ($category) : ?>
											<span class="ba-category-badge"><?php echo esc_html($category); ?></span>
											<a href="#" class="ba-edit-category" data-id="<?php echo esc_attr($widget->ID); ?>">
												<span class="dashicons dashicons-edit"></span>
												<?php esc_html_e('Edit Category', 'best-addons'); ?>
											</a>
										<?php else : ?>
											<span class="ba-category-badge ba-category-none"><?php esc_html_e('None', 'best-addons'); ?></span>
										<?php endif; ?>
									</td>
									<td class="ba-col-author">
										<a href="<?php echo esc_url(admin_url('user-edit.php?user_id=' . absint($widget->post_author))); ?>">
											<?php echo esc_html($author_name); ?>
										</a>
									</td>
									<td class="ba-col-shortcode">
										<div class="ba-shortcode-wrap">
											<code class="ba-shortcode-code"><?php echo esc_html($shortcode); ?></code>
											<button type="button" class="ba-copy-shortcode" data-shortcode="<?php echo esc_attr($shortcode); ?>" title="<?php esc_attr_e('Copy shortcode', 'best-addons'); ?>">
												<span class="dashicons dashicons-admin-page"></span>
											</button>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<div class="ba-wb-table-footer">
						<p class="ba-found-count">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: total widget count */
									_n('%d item', '%d items', $total, 'best-addons'),
									$total
								)
							);
							?>
						</p>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- ── Create Widget Modal ─────────────────────────────────────── -->
		<div id="ba-create-modal" class="ba-modal" style="display:none;">
			<div class="ba-modal-overlay"></div>
			<div class="ba-modal-box">
				<div class="ba-modal-header">
					<h2><?php esc_html_e('Create New Widget', 'best-addons'); ?></h2>
					<button type="button" class="ba-modal-close">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
				<div class="ba-modal-body">
					<form id="ba-create-widget-form">
						<?php wp_nonce_field('ba_create_widget', 'ba_create_nonce'); ?>
						<div class="ba-form-field">
							<label for="ba-widget-title">
								<?php esc_html_e('Widget Title', 'best-addons'); ?>
								<span class="required">*</span>
							</label>
							<input
								type="text"
								id="ba-widget-title"
								name="widget_title"
								placeholder="<?php esc_attr_e('e.g. Hero Section, Pricing Card…', 'best-addons'); ?>"
								required
								autocomplete="off">
							<span class="ba-field-desc"><?php esc_html_e('This appears as the widget name in Elementor.', 'best-addons'); ?></span>
						</div>
						<div class="ba-form-field">
							<label for="ba-widget-category">
								<?php esc_html_e('Widget Category', 'best-addons'); ?>
							</label>
							<select id="ba-widget-category" name="widget_category">
								<option value="best-addons-category"><?php esc_html_e('Best Addons', 'best-addons'); ?></option>
								<option value="general"><?php esc_html_e('General', 'best-addons'); ?></option>
								<option value="basic"><?php esc_html_e('Basic', 'best-addons'); ?></option>
							</select>
						</div>
					</form>
				</div>
				<div class="ba-modal-footer">
					<button type="button" class="button ba-modal-close ba-btn-ghost"><?php esc_html_e('Cancel', 'best-addons'); ?></button>
					<button type="button" id="ba-create-widget-submit" class="button ba-btn-primary">
						<?php esc_html_e('Save Changes →', 'best-addons'); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- ── Import Widget Modal ─────────────────────────────────────── -->
		<div id="ba-import-modal" class="ba-modal" style="display:none;">
			<div class="ba-modal-overlay"></div>
			<div class="ba-modal-box">
				<div class="ba-modal-header">
					<h2><?php esc_html_e('Import Widget', 'best-addons'); ?></h2>
					<button type="button" class="ba-modal-close">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
				<div class="ba-modal-body">
					<form id="ba-import-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin.php?page=ba-widget-builder')); ?>">
						<?php wp_nonce_field('ba_import_widget', 'ba_import_nonce'); ?>
						<div class="ba-import-drop-zone" id="ba-import-drop-zone">
							<input type="file" name="ba_import_file" id="ba-import-file" accept=".json" class="ba-import-file-input">
							<div class="ba-import-drop-label">
								<span class="dashicons dashicons-upload ba-import-icon"></span>
								<strong><?php esc_html_e('Drop your JSON file here', 'best-addons'); ?></strong>
								<span><?php esc_html_e('or click to browse', 'best-addons'); ?></span>
								<span class="ba-import-file-name" id="ba-import-file-name"></span>
							</div>
						</div>
						<p class="ba-field-desc"><?php esc_html_e('Only .json files exported from Best Addons Widget Builder are supported.', 'best-addons'); ?></p>
						<div class="ba-modal-footer">
							<button type="button" class="button ba-modal-close ba-btn-ghost"><?php esc_html_e('Cancel', 'best-addons'); ?></button>
							<button type="submit" class="button ba-btn-primary" id="ba-import-submit" disabled>
								<span class="dashicons dashicons-upload"></span>
								<?php esc_html_e('Import Widget', 'best-addons'); ?>
							</button>
						</div>
					</form>
				</div>
			</div>
		</div>
<?php
	}

	// ── Import handler ────────────────────────────────────────────────────────

	private static function handle_import()
	{
		$file = $_FILES['ba_import_file'];

		if ($file['error'] !== UPLOAD_ERR_OK) {
			wp_safe_redirect(admin_url('admin.php?page=ba-widget-builder&import_error=1'));
			exit;
		}

		$json = file_get_contents($file['tmp_name']);
		$data = json_decode($json, true);

		if (empty($data['title'])) {
			wp_safe_redirect(admin_url('admin.php?page=ba-widget-builder&import_error=1'));
			exit;
		}

		$post_id = wp_insert_post([
			'post_type'   => 'best_widget',
			'post_title'  => sanitize_text_field($data['title']),
			'post_status' => 'publish',
		]);

		if (is_wp_error($post_id)) {
			wp_safe_redirect(admin_url('admin.php?page=ba-widget-builder&import_error=1'));
			exit;
		}

		// Restore meta fields.
		$meta_keys = ['_ba_icon', '_ba_category', '_ba_controls', '_ba_html', '_ba_css', '_ba_js', '_ba_includes'];
		foreach ($meta_keys as $key) {
			$clean_key = ltrim($key, '_ba_');
			if (isset($data[$clean_key])) {
				update_post_meta($post_id, $key, wp_slash($data[$clean_key]));
			}
		}

		wp_safe_redirect(admin_url('admin.php?page=ba-widget-builder&imported=1'));
		exit;
	}
}

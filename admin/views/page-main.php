<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$api_key          = get_option( 'sat_anthropic_api_key', '' );
$active_theme     = wp_get_theme();
$child_theme_name = $active_theme->get( 'Name' );
$has_parent       = SAT_Theme_Scanner::has_parent_theme();
$parent_theme_name = '';
if ( $has_parent ) {
	$parent_obj        = $active_theme->parent();
	$parent_theme_name = $parent_obj ? $parent_obj->get( 'Name' ) : $child_theme_name;
}
?>
<div class="wrap sat-wrap">

	<h1 class="wp-heading-inline"><?php esc_html_e( 'Search Alt Tags', 'search-alt-tags' ); ?></h1>

	<?php if ( empty( $api_key ) ) : ?>
	<div class="notice notice-warning inline sat-api-notice">
		<p><?php printf(
			/* translators: %s: link */
			esc_html__( 'AI generation is disabled — %s to add your Anthropic API key.', 'search-alt-tags' ),
			'<a href="' . esc_url( admin_url( 'upload.php?page=search-alt-tags-settings' ) ) . '">' . esc_html__( 'go to settings', 'search-alt-tags' ) . '</a>'
		); ?></p>
	</div>
	<?php endif; ?>

	<!-- ── Global summary bar ──────────────────────────────────────────────── -->
	<div class="sat-summary-bar" id="sat-summary-bar">
		<div class="sat-summary-item" id="sat-summary-media">
			<span class="sat-summary-icon dashicons dashicons-format-image"></span>
			<span class="sat-summary-count" id="sat-media-count">—</span>
			<span class="sat-summary-label"><?php esc_html_e( 'Media library issues', 'search-alt-tags' ); ?></span>
		</div>
		<div class="sat-summary-divider"></div>
		<div class="sat-summary-item" id="sat-summary-theme">
			<span class="sat-summary-icon dashicons dashicons-editor-code"></span>
			<span class="sat-summary-count" id="sat-theme-count">—</span>
			<span class="sat-summary-label">
				<?php
				/* translators: %s: theme name */
				printf( esc_html__( '%s issues', 'search-alt-tags' ), esc_html( $child_theme_name ) );
				?>
			</span>
		</div>
		<?php if ( $has_parent ) : ?>
		<div class="sat-summary-divider"></div>
		<div class="sat-summary-item" id="sat-summary-parent">
			<span class="sat-summary-icon dashicons dashicons-editor-code"></span>
			<span class="sat-summary-count" id="sat-parent-theme-count">—</span>
			<span class="sat-summary-label">
				<?php
				/* translators: %s: parent theme name */
				printf( esc_html__( '%s issues', 'search-alt-tags' ), esc_html( $parent_theme_name ) );
				?>
			</span>
		</div>
		<?php endif; ?>
		<div class="sat-summary-spacer"></div>
		<button id="sat-rescan-all-btn" class="button button-primary sat-rescan-all-btn">
			<span class="dashicons dashicons-update"></span>
			<?php esc_html_e( 'Rescan Everything', 'search-alt-tags' ); ?>
		</button>
	</div>

	<!-- ── Tabs ────────────────────────────────────────────────────────────── -->
	<div class="sat-tabs-wrap">
		<nav class="sat-tabs" role="tablist">
			<button class="sat-tab sat-tab--active" role="tab" aria-selected="true"
				aria-controls="sat-panel-media" id="sat-tab-media">
				<span class="dashicons dashicons-format-image"></span>
				<?php esc_html_e( 'Media Library', 'search-alt-tags' ); ?>
				<span class="sat-tab-badge" id="sat-tab-media-badge"></span>
			</button>
			<button class="sat-tab" role="tab" aria-selected="false"
				aria-controls="sat-panel-theme" id="sat-tab-theme">
				<span class="dashicons dashicons-editor-code"></span>
				<?php echo esc_html( $child_theme_name ); ?>
				<span class="sat-tab-badge" id="sat-tab-theme-badge"></span>
			</button>
			<?php if ( $has_parent ) : ?>
			<button class="sat-tab" role="tab" aria-selected="false"
				aria-controls="sat-panel-parent-theme" id="sat-tab-parent-theme">
				<span class="dashicons dashicons-editor-code"></span>
				<?php echo esc_html( $parent_theme_name ); ?>
				<span class="sat-tab-badge" id="sat-tab-parent-theme-badge"></span>
			</button>
			<?php endif; ?>
		</nav>

		<!-- ── PANEL: Media library ──────────────────────────────────────── -->
		<div class="sat-panel sat-panel--active" id="sat-panel-media" role="tabpanel" aria-labelledby="sat-tab-media">

			<div class="sat-panel-toolbar">
				<span class="sat-panel-count" id="sat-media-panel-count"></span>
				<div class="sat-panel-toolbar-right">
					<?php if ( ! empty( $api_key ) ) : ?>
					<button id="sat-bulk-generate-btn" class="button">
						<span class="dashicons dashicons-superhero"></span>
						<?php esc_html_e( 'AI Generate All', 'search-alt-tags' ); ?>
					</button>
					<?php endif; ?>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sat_export_media_csv' ), 'sat_export_nonce', 'nonce' ) ); ?>">
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'Export CSV', 'search-alt-tags' ); ?>
					</a>
					<button id="sat-import-media-btn" class="button">
						<span class="dashicons dashicons-upload"></span>
						<?php esc_html_e( 'Import CSV', 'search-alt-tags' ); ?>
					</button>
					<input type="file" id="sat-import-media-file" accept=".csv" style="display:none;">
					<button id="sat-rescan-media-btn" class="button">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Rescan Media', 'search-alt-tags' ); ?>
					</button>
				</div>
			</div>

			<div id="sat-import-result" class="sat-import-result" style="display:none;">
				<span class="sat-import-msg"></span>
				<button class="sat-import-dismiss" aria-label="<?php esc_attr_e( 'Dismiss', 'search-alt-tags' ); ?>">&#x2715;</button>
			</div>

			<!-- Bulk progress -->
			<div id="sat-bulk-progress" class="sat-bulk-progress" style="display:none;">
				<div class="sat-progress-bar-wrap">
					<div class="sat-progress-bar" id="sat-progress-bar"></div>
				</div>
				<div class="sat-progress-footer">
					<span class="sat-progress-label" id="sat-progress-label"></span>
					<button id="sat-cancel-bulk" class="button button-small"><?php esc_html_e( 'Cancel', 'search-alt-tags' ); ?></button>
				</div>
				<div id="sat-bulk-error" class="sat-bulk-error" style="display:none;"></div>
			</div>

			<table class="wp-list-table widefat fixed striped sat-media-table">
				<thead>
					<tr>
						<th class="sat-col-thumb"><?php esc_html_e( 'Image', 'search-alt-tags' ); ?></th>
						<th class="sat-col-file"><?php esc_html_e( 'File', 'search-alt-tags' ); ?></th>
						<th class="sat-col-alt"><?php esc_html_e( 'Alt Tag', 'search-alt-tags' ); ?></th>
						<th class="sat-col-actions"><?php esc_html_e( 'Actions', 'search-alt-tags' ); ?></th>
					</tr>
				</thead>
				<tbody id="sat-media-list">
					<tr class="sat-loading-row">
						<td colspan="4"><span class="spinner is-active"></span> <?php esc_html_e( 'Loading…', 'search-alt-tags' ); ?></td>
					</tr>
				</tbody>
			</table>

			<div id="sat-media-pagination" class="sat-pagination" style="display:none;">
				<button id="sat-prev-page" class="button" disabled><?php esc_html_e( '← Previous', 'search-alt-tags' ); ?></button>
				<span id="sat-page-info" class="sat-page-info"></span>
				<button id="sat-next-page" class="button"><?php esc_html_e( 'Next →', 'search-alt-tags' ); ?></button>
			</div>

		</div><!-- #sat-panel-media -->

		<!-- ── PANEL: Child theme templates ──────────────────────────────── -->
		<div class="sat-panel" id="sat-panel-theme" role="tabpanel" aria-labelledby="sat-tab-theme" hidden>

			<div class="sat-panel-toolbar">
				<div class="sat-panel-toolbar-left">
					<span class="sat-panel-count" id="sat-theme-panel-count"></span>
					<span class="sat-ignored-info" id="sat-ignored-info" style="display:none;">
						&nbsp;·&nbsp;
						<span id="sat-ignored-count"></span> ignored
						&nbsp;<button id="sat-clear-ignored-btn" class="button-link sat-clear-ignored-btn"><?php esc_html_e( 'Reset', 'search-alt-tags' ); ?></button>
					</span>
				</div>
				<div class="sat-panel-toolbar-right">
					<span class="sat-scan-time" id="sat-scan-time"></span>
					<a class="button" id="sat-export-theme-csv" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sat_export_theme_csv' ), 'sat_export_nonce', 'nonce' ) ); ?>">
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'Export CSV', 'search-alt-tags' ); ?>
					</a>
					<button id="sat-rescan-theme-btn" class="button">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Rescan', 'search-alt-tags' ); ?>
					</button>
				</div>
			</div>

			<!-- Legend -->
			<div class="sat-legend">
				<span class="sat-badge sat-badge--error"><?php esc_html_e( 'Error', 'search-alt-tags' ); ?></span>
				<?php esc_html_e( 'Missing alt attribute', 'search-alt-tags' ); ?>
				&ensp;
				<span class="sat-badge sat-badge--warning"><?php esc_html_e( 'Warning', 'search-alt-tags' ); ?></span>
				<?php esc_html_e( 'Empty alt=""', 'search-alt-tags' ); ?>
				&ensp;
				<span class="sat-badge sat-badge--notice"><?php esc_html_e( 'Notice', 'search-alt-tags' ); ?></span>
				<?php esc_html_e( 'Dynamic alt (may be empty)', 'search-alt-tags' ); ?>
			</div>

			<div id="sat-theme-results">
				<div class="sat-loading-row">
					<span class="spinner is-active"></span>
					<?php esc_html_e( 'Scanning theme files…', 'search-alt-tags' ); ?>
				</div>
			</div>

		</div><!-- #sat-panel-theme -->

		<?php if ( $has_parent ) : ?>
		<!-- ── PANEL: Parent theme templates ─────────────────────────────── -->
		<div class="sat-panel" id="sat-panel-parent-theme" role="tabpanel" aria-labelledby="sat-tab-parent-theme" hidden>

			<div class="sat-readonly-notice">
				<span class="dashicons dashicons-info"></span>
				<?php printf(
					/* translators: %s: parent theme name */
					esc_html__( 'These issues are in the %s parent theme. To fix them without losing changes on update, override the affected file in your child theme.', 'search-alt-tags' ),
					'<strong>' . esc_html( $parent_theme_name ) . '</strong>'
				); ?>
			</div>

			<div class="sat-panel-toolbar">
				<div class="sat-panel-toolbar-left">
					<span class="sat-panel-count" id="sat-parent-theme-panel-count"></span>
					<span class="sat-ignored-info" id="sat-parent-ignored-info" style="display:none;">
						&nbsp;·&nbsp;
						<span id="sat-parent-ignored-count"></span> ignored
						&nbsp;<button id="sat-clear-parent-ignored-btn" class="button-link sat-clear-ignored-btn"><?php esc_html_e( 'Reset', 'search-alt-tags' ); ?></button>
					</span>
				</div>
				<div class="sat-panel-toolbar-right">
					<span class="sat-scan-time" id="sat-parent-scan-time"></span>
					<a class="button" id="sat-export-parent-theme-csv" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sat_export_parent_theme_csv' ), 'sat_export_nonce', 'nonce' ) ); ?>">
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'Export CSV', 'search-alt-tags' ); ?>
					</a>
					<button id="sat-rescan-parent-theme-btn" class="button">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Rescan', 'search-alt-tags' ); ?>
					</button>
				</div>
			</div>

			<!-- Legend -->
			<div class="sat-legend">
				<span class="sat-badge sat-badge--error"><?php esc_html_e( 'Error', 'search-alt-tags' ); ?></span>
				<?php esc_html_e( 'Missing alt attribute', 'search-alt-tags' ); ?>
				&ensp;
				<span class="sat-badge sat-badge--warning"><?php esc_html_e( 'Warning', 'search-alt-tags' ); ?></span>
				<?php esc_html_e( 'Empty alt=""', 'search-alt-tags' ); ?>
				&ensp;
				<span class="sat-badge sat-badge--notice"><?php esc_html_e( 'Notice', 'search-alt-tags' ); ?></span>
				<?php esc_html_e( 'Dynamic alt (may be empty)', 'search-alt-tags' ); ?>
			</div>

			<div id="sat-parent-theme-results">
				<div class="sat-loading-row">
					<span class="spinner is-active"></span>
					<?php esc_html_e( 'Scanning theme files…', 'search-alt-tags' ); ?>
				</div>
			</div>

		</div><!-- #sat-panel-parent-theme -->
		<?php endif; ?>

	</div><!-- .sat-tabs-wrap -->

	<!-- ── Media row template (cloned by JS, never rendered as HTML) ──────── -->
	<script type="text/html" id="sat-media-row-tpl">
		<tr data-id="{{id}}" class="sat-media-row">
			<td class="sat-col-thumb">
				<a href="{{full_url}}" target="_blank" rel="noopener">
					<img src="{{thumb_url}}" alt="" class="sat-thumb" width="60" height="60" loading="lazy">
				</a>
			</td>
			<td class="sat-col-file">
				<strong class="sat-filename">{{filename}}</strong>
				<div class="sat-file-meta">
					<span>{{dimensions}}</span>
					<span>{{file_size}}</span>
					<span>{{date}}</span>
				</div>
			</td>
			<td class="sat-col-alt">
				<input
					type="text"
					class="sat-alt-input large-text"
					value="{{alt}}"
					placeholder="<?php esc_attr_e( 'Describe this image…', 'search-alt-tags' ); ?>"
					aria-label="<?php esc_attr_e( 'Alt tag', 'search-alt-tags' ); ?>"
				>
				<span class="sat-status"></span>
			</td>
			<td class="sat-col-actions">
				<button class="button sat-save-btn">
					<span class="dashicons dashicons-saved"></span><?php esc_html_e( 'Save', 'search-alt-tags' ); ?>
				</button>
				<?php if ( ! empty( $api_key ) ) : ?>
				<button class="button sat-generate-btn">
					<span class="dashicons dashicons-superhero-alt"></span><?php esc_html_e( 'AI Generate', 'search-alt-tags' ); ?>
				</button>
				<?php endif; ?>
			</td>
		</tr>
	</script>

</div><!-- .sat-wrap -->

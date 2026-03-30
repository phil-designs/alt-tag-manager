<?php
/**
 * Plugin Name: Alt Tag Manager
 * Plugin URI:  https://www.phildesigns.com
 * Description: Find images missing alt tags in the media library and in active theme templates. Add tags manually or auto-generate them with AI.
 * Version:     1.2.0
 * Author:      phil.designs | Phillip De Vita
 * Author URI:  http://www.phildesigns.com
 * License:     GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SAT_VERSION',    '1.2.0' );
define( 'SAT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SAT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once SAT_PLUGIN_DIR . 'includes/class-sat-media-scanner.php';
require_once SAT_PLUGIN_DIR . 'includes/class-sat-theme-scanner.php';
require_once SAT_PLUGIN_DIR . 'includes/class-sat-claude.php';

class Search_Alt_Tags {

	/**
	 * Set to true while our own AJAX save is running so the updated_post_meta
	 * hook skips the sync (we handle it directly there with an accurate count).
	 *
	 * @var bool
	 */
	private $skip_alt_sync = false;

	public function __construct() {
		add_action( 'admin_menu',             array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts',  array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );

		add_action( 'updated_post_meta',             array( $this, 'sync_alt_to_post_content' ), 10, 4 );
		add_action( 'wp_ajax_sat_get_media_images',  array( $this, 'ajax_get_media_images' ) );
		add_action( 'wp_ajax_sat_save_alt_tag',      array( $this, 'ajax_save_alt_tag' ) );
		add_action( 'wp_ajax_sat_generate_alt_tag',  array( $this, 'ajax_generate_alt_tag' ) );
		add_action( 'wp_ajax_sat_scan_theme',        array( $this, 'ajax_scan_theme' ) );
		add_action( 'wp_ajax_sat_rescan_theme',      array( $this, 'ajax_rescan_theme' ) );
		add_action( 'wp_ajax_sat_save_settings',     array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_sat_get_summary',                    array( $this, 'ajax_get_summary' ) );
		add_action( 'wp_ajax_sat_get_all_media_ids',              array( $this, 'ajax_get_all_media_ids' ) );
		add_action( 'wp_ajax_sat_ignore_theme_issue',             array( $this, 'ajax_ignore_theme_issue' ) );
		add_action( 'wp_ajax_sat_clear_ignored_theme',            array( $this, 'ajax_clear_ignored_theme' ) );
		add_action( 'wp_ajax_sat_scan_parent_theme',              array( $this, 'ajax_scan_parent_theme' ) );
		add_action( 'wp_ajax_sat_rescan_parent_theme',            array( $this, 'ajax_rescan_parent_theme' ) );
		add_action( 'wp_ajax_sat_ignore_parent_theme_issue',      array( $this, 'ajax_ignore_parent_theme_issue' ) );
		add_action( 'wp_ajax_sat_clear_ignored_parent_theme',     array( $this, 'ajax_clear_ignored_parent_theme' ) );
		add_action( 'wp_ajax_sat_import_media_csv',               array( $this, 'ajax_import_media_csv' ) );
		add_action( 'admin_post_sat_export_media_csv',            array( $this, 'export_media_csv' ) );
		add_action( 'admin_post_sat_export_theme_csv',            array( $this, 'export_theme_csv' ) );
		add_action( 'admin_post_sat_export_parent_theme_csv',     array( $this, 'export_parent_theme_csv' ) );
	}

	// -------------------------------------------------------------------------
	// Plugin action links (Plugins list page)
	// -------------------------------------------------------------------------
	public function plugin_action_links( $links ) {
		$plugin_links = array(
			'<a href="' . admin_url( 'upload.php?page=search-alt-tags-settings' ) . '">' . __( 'Settings', 'search-alt-tags' ) . '</a>',
			'<a href="' . admin_url( 'upload.php?page=search-alt-tags' ) . '">' . __( 'Scan', 'search-alt-tags' ) . '</a>',
		);
		return array_merge( $plugin_links, $links );
	}

	// -------------------------------------------------------------------------
	// Admin menus
	// -------------------------------------------------------------------------
	public function register_menus() {
		add_media_page(
			__( 'Search Alt Tags', 'search-alt-tags' ),
			__( 'Search Alt Tags', 'search-alt-tags' ),
			'upload_files',
			'search-alt-tags',
			array( $this, 'render_main_page' )
		);

		add_submenu_page(
			'upload.php',
			__( 'Alt Tags — Settings', 'search-alt-tags' ),
			__( 'Alt Tag Settings', 'search-alt-tags' ),
			'manage_options',
			'search-alt-tags-settings',
			array( $this, 'render_settings_page' )
		);
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------
	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'search-alt-tags' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'sat-admin',
			SAT_PLUGIN_URL . 'admin/css/sat-admin.css',
			array(),
			SAT_VERSION
		);

		wp_enqueue_script(
			'sat-admin',
			SAT_PLUGIN_URL . 'admin/js/sat-admin.js',
			array( 'jquery' ),
			SAT_VERSION,
			true
		);

		$api_key    = get_option( 'sat_anthropic_api_key', '' );
		$has_parent = SAT_Theme_Scanner::has_parent_theme();
		$parent_obj = $has_parent ? wp_get_theme()->parent() : null;

		wp_localize_script( 'sat-admin', 'SAT', array(
			'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
			'nonce'           => wp_create_nonce( 'sat_nonce' ),
			'hasApiKey'       => ! empty( $api_key ),
			'themeDir'        => get_template_directory(),
			'hasParentTheme'  => $has_parent,
			'parentThemeName' => $has_parent && $parent_obj ? $parent_obj->get( 'Name' ) : '',
			'exportParentCsvUrl' => $has_parent ? wp_nonce_url( admin_url( 'admin-post.php?action=sat_export_parent_theme_csv' ), 'sat_export_nonce', 'nonce' ) : '',
			'i18n'            => array(
				'saving'       => __( 'Saving…', 'search-alt-tags' ),
				'saved'        => __( 'Saved!', 'search-alt-tags' ),
				'generating'   => __( 'Generating…', 'search-alt-tags' ),
				'scanning'     => __( 'Scanning theme files…', 'search-alt-tags' ),
				'error'        => __( 'Error. Try again.', 'search-alt-tags' ),
				'confirmBulk'  => __( 'Generate AI alt tags for all images shown? Each image will be analysed by Claude and saved automatically.', 'search-alt-tags' ),
				'confirmRescan'=> __( 'Rescan both the media library and theme templates now?', 'search-alt-tags' ),
				'noIssues'     => __( 'No issues found — everything looks good!', 'search-alt-tags' ),
				'bulkProgress' => __( 'Processing %1$d of %2$d…', 'search-alt-tags' ),
				'bulkDone'     => __( 'Done! Generated %d alt tags.', 'search-alt-tags' ),
			),
		) );
	}

	// -------------------------------------------------------------------------
	// Views
	// -------------------------------------------------------------------------
	public function render_main_page() {
		require_once SAT_PLUGIN_DIR . 'admin/views/page-main.php';
	}

	public function render_settings_page() {
		require_once SAT_PLUGIN_DIR . 'admin/views/page-settings.php';
	}

	// -------------------------------------------------------------------------
	// AJAX — summary counts (used on load and after rescan)
	// -------------------------------------------------------------------------
	public function ajax_get_summary() {
		check_ajax_referer( 'sat_nonce', 'nonce' );
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error();
		}

		$media_scanner = new SAT_Media_Scanner();
		$media_result  = $media_scanner->get_images_missing_alt( 1, 1 ); // just need total

		$theme_scanner = new SAT_Theme_Scanner();
		$theme_result  = $theme_scanner->get_cached_results();

		$theme_total = null;
		if ( $theme_result ) {
			$filtered    = $this->apply_ignore_filter( $theme_result );
			$theme_total = $filtered['total_issues'];
		}

		$parent_total = null;
		if ( SAT_Theme_Scanner::has_parent_theme() ) {
			$parent_scanner = new SAT_Theme_Scanner( 'parent' );
			$parent_result  = $parent_scanner->get_cached_results();
			if ( $parent_result ) {
				$filtered     = $this->apply_ignore_filter( $parent_result, 'sat_ignored_parent_theme_issues' );
				$parent_total = $filtered['total_issues'];
			}
		}

		wp_send_json_success( array(
			'media_total'   => $media_result['total'],
			'theme_total'   => $theme_total,
			'parent_total'  => $parent_total,
			'theme_scanned' => $theme_result ? $theme_result['scanned_at'] : null,
		) );
	}

	// -------------------------------------------------------------------------
	// AJAX — all media IDs missing alt (for bulk generate across all pages)
	// -------------------------------------------------------------------------
	public function ajax_get_all_media_ids() {
		check_ajax_referer( 'sat_nonce', 'nonce' );
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'search-alt-tags' ) ) );
		}

		$scanner = new SAT_Media_Scanner();
		wp_send_json_success( array( 'ids' => $scanner->get_all_ids_missing_alt() ) );
	}

	// -------------------------------------------------------------------------
	// AJAX — paginated media library
	// -------------------------------------------------------------------------
	public function ajax_get_media_images() {
		check_ajax_referer( 'sat_nonce', 'nonce' );
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'search-alt-tags' ) ) );
		}

		$page     = isset( $_POST['page'] ) ? max( 1, intval( $_POST['page'] ) ) : 1;
		$per_page = 20;

		$scanner = new SAT_Media_Scanner();
		$result  = $scanner->get_images_missing_alt( $page, $per_page );

		wp_send_json_success( $result );
	}

	// -------------------------------------------------------------------------
	// AJAX — save alt tag
	// -------------------------------------------------------------------------
	public function ajax_save_alt_tag() {
		check_ajax_referer( 'sat_nonce', 'nonce' );
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'search-alt-tags' ) ) );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? intval( $_POST['attachment_id'] ) : 0;
		$alt_text      = isset( $_POST['alt_text'] )      ? sanitize_text_field( wp_unslash( $_POST['alt_text'] ) ) : '';

		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment ID.', 'search-alt-tags' ) ) );
		}

		$this->skip_alt_sync = true;
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
		$this->skip_alt_sync = false;

		$posts_updated = ! empty( $alt_text ) ? $this->update_post_content_alt( $attachment_id, $alt_text ) : 0;

		wp_send_json_success( array(
			'message'       => __( 'Alt tag saved.', 'search-alt-tags' ),
			'attachment_id' => $attachment_id,
			'alt_text'      => $alt_text,
			'posts_updated' => $posts_updated,
		) );
	}

	// -------------------------------------------------------------------------
	// AJAX — generate alt tag via Claude
	// -------------------------------------------------------------------------
	public function ajax_generate_alt_tag() {
		check_ajax_referer( 'sat_nonce', 'nonce' );
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'search-alt-tags' ) ) );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? intval( $_POST['attachment_id'] ) : 0;
		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment ID.', 'search-alt-tags' ) ) );
		}

		$api_key = get_option( 'sat_anthropic_api_key', '' );
		if ( empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Anthropic API key not configured. Visit Alt Tag Settings.', 'search-alt-tags' ) ) );
		}

		$image_url = wp_get_attachment_url( $attachment_id );
		if ( ! $image_url ) {
			wp_send_json_error( array( 'message' => __( 'Could not get image URL.', 'search-alt-tags' ) ) );
		}

		$claude   = new SAT_Claude( $api_key );
		$alt_text = $claude->generate_alt_tag( $image_url );

		if ( is_wp_error( $alt_text ) ) {
			wp_send_json_error( array( 'message' => $alt_text->get_error_message() ) );
		}

		wp_send_json_success( array(
			'alt_text'      => $alt_text,
			'attachment_id' => $attachment_id,
		) );
	}

	// -------------------------------------------------------------------------
	// AJAX — scan theme (returns cached or fresh results)
	// -------------------------------------------------------------------------
	public function ajax_scan_theme() {
		check_ajax_referer( 'sat_nonce', 'nonce' );
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'search-alt-tags' ) ) );
		}

		$scanner = new SAT_Theme_Scanner();
		$results = $scanner->get_results(); // uses cache if available

		wp_send_json_success( $this->apply_ignore_filter( $results ) );
	}

	// -------------------------------------------------------------------------
	// AJAX — force-rescan theme (clears cache)
	// -------------------------------------------------------------------------
	public function ajax_rescan_theme() {
		check_ajax_referer( 'sat_nonce', 'nonce' );
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'search-alt-tags' ) ) );
		}

		$scanner = new SAT_Theme_Scanner();
		$scanner->clear_cache();
		$results = $scanner->get_results();

		wp_send_json_success( $this->apply_ignore_filter( $results ) );
	}

	// -------------------------------------------------------------------------
	// AJAX — save settings
	// -------------------------------------------------------------------------
	public function ajax_save_settings() {
		check_ajax_referer( 'sat_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'search-alt-tags' ) ) );
		}

		$api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
		update_option( 'sat_anthropic_api_key', $api_key );

		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'search-alt-tags' ) ) );
	}
	// -------------------------------------------------------------------------
	// AJAX — ignore / clear ignored theme issues
	// -------------------------------------------------------------------------
	public function ajax_ignore_theme_issue() {
		check_ajax_referer( 'sat_nonce', 'nonce' );
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'search-alt-tags' ) ) );
		}

		$key    = isset( $_POST['key'] )           ? sanitize_text_field( wp_unslash( $_POST['key'] ) )           : '';
		$action = isset( $_POST['ignore_action'] ) ? sanitize_text_field( wp_unslash( $_POST['ignore_action'] ) ) : 'add';

		if ( empty( $key ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid key.', 'search-alt-tags' ) ) );
		}

		$ignored = get_option( 'sat_ignored_theme_issues', array() );

		if ( 'remove' === $action ) {
			unset( $ignored[ $key ] );
		} else {
			$ignored[ $key ] = true;
		}

		update_option( 'sat_ignored_theme_issues', $ignored, false );
		wp_send_json_success( array( 'key' => $key, 'action' => $action ) );
	}

	public function ajax_clear_ignored_theme() {
		check_ajax_referer( 'sat_nonce', 'nonce' );
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'search-alt-tags' ) ) );
		}

		delete_option( 'sat_ignored_theme_issues' );
		wp_send_json_success();
	}

	// -------------------------------------------------------------------------
	// AJAX — import media CSV
	// -------------------------------------------------------------------------

	/**
	 * Accepts a CSV upload with "ID" and "Alt Tag" columns and bulk-updates
	 * the _wp_attachment_image_alt meta for each matched attachment.
	 */
	public function ajax_import_media_csv() {
		check_ajax_referer( 'sat_nonce', 'nonce' );
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'search-alt-tags' ) ) );
		}

		if ( empty( $_FILES['csv_file'] ) || UPLOAD_ERR_OK !== (int) $_FILES['csv_file']['error'] ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded or upload error.', 'search-alt-tags' ) ) );
		}

		$file = $_FILES['csv_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		// Validate extension.
		$ext = strtolower( pathinfo( sanitize_file_name( $file['name'] ), PATHINFO_EXTENSION ) );
		if ( 'csv' !== $ext ) {
			wp_send_json_error( array( 'message' => __( 'Please upload a .csv file.', 'search-alt-tags' ) ) );
		}

		$handle = fopen( $file['tmp_name'], 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $handle ) {
			wp_send_json_error( array( 'message' => __( 'Could not read the uploaded file.', 'search-alt-tags' ) ) );
		}

		// Parse and normalise header row.
		$headers = fgetcsv( $handle );
		if ( ! $headers ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			wp_send_json_error( array( 'message' => __( 'CSV file is empty or invalid.', 'search-alt-tags' ) ) );
		}

		$headers = array_map( function ( $h ) { return strtolower( trim( $h ) ); }, $headers );
		$id_col  = array_search( 'id', $headers, true );
		$alt_col = array_search( 'alt tag', $headers, true );

		if ( false === $id_col || false === $alt_col ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			wp_send_json_error( array( 'message' => __( 'CSV must contain "ID" and "Alt Tag" columns. Download the template CSV for the correct format.', 'search-alt-tags' ) ) );
		}

		$updated = 0;
		$skipped = 0;
		$errors  = 0;

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			$id  = isset( $row[ $id_col ] )  ? (int) $row[ $id_col ]                                               : 0;
			$alt = isset( $row[ $alt_col ] ) ? sanitize_text_field( wp_unslash( $row[ $alt_col ] ) ) : '';

			if ( $id <= 0 ) {
				$skipped++;
				continue;
			}

			if ( '' === trim( $alt ) ) {
				$skipped++;
				continue;
			}

			$post = get_post( $id );
			if ( ! $post || 'attachment' !== $post->post_type ) {
				$errors++;
				continue;
			}

			update_post_meta( $id, '_wp_attachment_image_alt', $alt );
			$updated++;
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		wp_send_json_success( array(
			'updated' => $updated,
			'skipped' => $skipped,
			'errors'  => $errors,
		) );
	}

	// -------------------------------------------------------------------------
	// AJAX — parent theme scan / rescan / ignore
	// -------------------------------------------------------------------------
	public function ajax_scan_parent_theme() {
		check_ajax_referer( 'sat_nonce', 'nonce' );
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'search-alt-tags' ) ) );
		}

		$scanner = new SAT_Theme_Scanner( 'parent' );
		wp_send_json_success( $this->apply_ignore_filter( $scanner->get_results(), 'sat_ignored_parent_theme_issues' ) );
	}

	public function ajax_rescan_parent_theme() {
		check_ajax_referer( 'sat_nonce', 'nonce' );
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'search-alt-tags' ) ) );
		}

		$scanner = new SAT_Theme_Scanner( 'parent' );
		$scanner->clear_cache();
		wp_send_json_success( $this->apply_ignore_filter( $scanner->get_results(), 'sat_ignored_parent_theme_issues' ) );
	}

	public function ajax_ignore_parent_theme_issue() {
		check_ajax_referer( 'sat_nonce', 'nonce' );
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'search-alt-tags' ) ) );
		}

		$key    = isset( $_POST['key'] )           ? sanitize_text_field( wp_unslash( $_POST['key'] ) )           : '';
		$action = isset( $_POST['ignore_action'] ) ? sanitize_text_field( wp_unslash( $_POST['ignore_action'] ) ) : 'add';

		if ( empty( $key ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid key.', 'search-alt-tags' ) ) );
		}

		$ignored = get_option( 'sat_ignored_parent_theme_issues', array() );

		if ( 'remove' === $action ) {
			unset( $ignored[ $key ] );
		} else {
			$ignored[ $key ] = true;
		}

		update_option( 'sat_ignored_parent_theme_issues', $ignored, false );
		wp_send_json_success( array( 'key' => $key, 'action' => $action ) );
	}

	public function ajax_clear_ignored_parent_theme() {
		check_ajax_referer( 'sat_nonce', 'nonce' );
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'search-alt-tags' ) ) );
		}

		delete_option( 'sat_ignored_parent_theme_issues' );
		wp_send_json_success();
	}

	// -------------------------------------------------------------------------
	// CSV exports — triggered via admin-post.php
	// -------------------------------------------------------------------------
	public function export_media_csv() {
		if ( ! check_admin_referer( 'sat_export_nonce', 'nonce' ) ) {
			wp_die( 'Invalid request.' );
		}
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( 'Insufficient permissions.' );
		}

		$scanner = new SAT_Media_Scanner();
		$result  = $scanner->get_images_missing_alt( 1, PHP_INT_MAX );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="missing-alt-tags-media-' . gmdate( 'Y-m-d' ) . '.csv"' );
		header( 'Pragma: no-cache' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'ID', 'Filename', 'URL', 'Dimensions', 'File Size', 'Date Uploaded', 'Alt Tag' ) );

		foreach ( $result['images'] as $img ) {
			fputcsv( $out, array(
				$img['id'],
				$img['filename'],
				$img['full_url'],
				$img['dimensions'],
				$img['file_size'],
				$img['date'],
				'', // Fill in this column then re-upload via Import CSV
			) );
		}

		fclose( $out );
		exit;
	}

	public function export_theme_csv() {
		if ( ! check_admin_referer( 'sat_export_nonce', 'nonce' ) ) {
			wp_die( 'Invalid request.' );
		}
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( 'Insufficient permissions.' );
		}

		$scanner = new SAT_Theme_Scanner();
		$results = $scanner->get_cached_results() ?: $scanner->get_results();
		$results = $this->apply_ignore_filter( $results );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="theme-alt-tag-issues-' . gmdate( 'Y-m-d' ) . '.csv"' );
		header( 'Pragma: no-cache' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'File', 'Line', 'Severity', 'Issue Type', 'Snippet' ) );

		foreach ( $results['files'] as $file => $issues ) {
			foreach ( $issues as $issue ) {
				fputcsv( $out, array(
					$file,
					$issue['line'],
					$issue['severity'],
					$issue['label'],
					$issue['snippet'],
				) );
			}
		}

		fclose( $out );
		exit;
	}

	public function export_parent_theme_csv() {
		if ( ! check_admin_referer( 'sat_export_nonce', 'nonce' ) ) {
			wp_die( 'Invalid request.' );
		}
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( 'Insufficient permissions.' );
		}

		$scanner = new SAT_Theme_Scanner( 'parent' );
		$results = $scanner->get_cached_results() ?: $scanner->get_results();
		$results = $this->apply_ignore_filter( $results, 'sat_ignored_parent_theme_issues' );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="parent-theme-alt-tag-issues-' . gmdate( 'Y-m-d' ) . '.csv"' );
		header( 'Pragma: no-cache' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'File', 'Line', 'Severity', 'Issue Type', 'Snippet' ) );

		foreach ( $results['files'] as $file => $issues ) {
			foreach ( $issues as $issue ) {
				fputcsv( $out, array(
					$file,
					$issue['line'],
					$issue['severity'],
					$issue['label'],
					$issue['snippet'],
				) );
			}
		}

		fclose( $out );
		exit;
	}

	// -------------------------------------------------------------------------
	// Helper — filter ignored issues out of theme scan results
	// -------------------------------------------------------------------------

	/**
	 * Removes entries the user has marked as resolved and appends an
	 * 'ignored_count' key to the result array.
	 *
	 * @param array  $results    Raw results from SAT_Theme_Scanner.
	 * @param string $option_key WordPress option storing the ignored-keys array.
	 * @return array
	 */
	private function apply_ignore_filter( $results, $option_key = 'sat_ignored_theme_issues' ) {
		$ignored_keys  = get_option( $option_key, array() );
		$ignored_count = 0;

		foreach ( $results['files'] as $file => &$issues ) {
			$issues = array_values( array_filter( $issues, function ( $issue ) use ( $file, $ignored_keys, &$ignored_count ) {
				if ( isset( $ignored_keys[ $file . '::' . $issue['line'] ] ) ) {
					$ignored_count++;
					return false;
				}
				return true;
			} ) );

			if ( empty( $issues ) ) {
				unset( $results['files'][ $file ] );
			}
		}
		unset( $issues );

		$results['total_issues'] = max( 0, $results['total_issues'] - $ignored_count );
		$results['ignored_count'] = $ignored_count;

		// Recount per-severity totals.
		$counts = array( 'error' => 0, 'warning' => 0, 'notice' => 0 );
		foreach ( $results['files'] as $issues ) {
			foreach ( $issues as $issue ) {
				$counts[ $issue['severity'] ]++;
			}
		}
		$results['counts'] = $counts;

		return $results;
	}

	// -------------------------------------------------------------------------
	// Hook — sync alt tag edits made outside the plugin to post content
	// -------------------------------------------------------------------------

	/**
	 * Fires whenever _wp_attachment_image_alt is updated — including saves made
	 * directly in the WordPress media library. Rewrites any <img> tags in post
	 * content that reference this attachment so they stay in sync.
	 *
	 * Our own ajax_save_alt_tag() sets $skip_alt_sync = true before calling
	 * update_post_meta, so this hook is a no-op for plugin-originated saves
	 * (which handle post-content rewriting directly with an accurate count).
	 *
	 * @param int    $meta_id   ID of the updated meta row (unused).
	 * @param int    $post_id   Attachment post ID.
	 * @param string $meta_key  Meta key that was updated.
	 * @param string $meta_value New alt text value.
	 */
	public function sync_alt_to_post_content( $meta_id, $post_id, $meta_key, $meta_value ) {
		if ( '_wp_attachment_image_alt' !== $meta_key || $this->skip_alt_sync ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return;
		}

		$this->update_post_content_alt( $post_id, $meta_value );
	}

	// -------------------------------------------------------------------------
	// Helpers — update <img> alt attributes in post content
	// -------------------------------------------------------------------------

	/**
	 * Finds every published post/page whose content contains an <img> tag
	 * referencing the given attachment and rewrites the alt attribute.
	 *
	 * Matches on:
	 *  - Gutenberg class="wp-image-{id}"
	 *  - The image's base filename (covers all registered thumbnail sizes)
	 *
	 * @param int    $attachment_id
	 * @param string $alt_text
	 * @return int Number of posts whose content was changed.
	 */
	private function update_post_content_alt( $attachment_id, $alt_text ) {
		global $wpdb;

		$image_url = wp_get_attachment_url( $attachment_id );
		if ( ! $image_url ) {
			return 0;
		}

		// Build a base path that matches all registered size variants of the file.
		// e.g. "2024/01/photo.jpg" → base "2024/01/photo" matches
		//      "photo.jpg", "photo-300x200.jpg", "photo-150x150.jpg", etc.
		$upload_dir = wp_upload_dir();
		$rel        = str_replace( trailingslashit( $upload_dir['baseurl'] ), '', $image_url );
		$info       = pathinfo( $rel );
		$base       = $info['dirname'] . '/' . preg_replace( '/-\d+x\d+$/', '', $info['filename'] );

		$id_like  = '%wp-image-' . intval( $attachment_id ) . '%';
		$url_like = '%' . $wpdb->esc_like( $base ) . '%';
		$updated  = 0;

		// ── 1. post_content (standard posts, pages, all CPTs) ────────────────
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_content
				   FROM {$wpdb->posts}
				  WHERE post_status NOT IN ('trash','auto-draft')
				    AND post_type  NOT IN ('revision','nav_menu_item','attachment')
				    AND ( post_content LIKE %s
				       OR post_content LIKE %s )",
				$id_like,
				$url_like
			),
			ARRAY_A
		);

		foreach ( $posts as $post ) {
			$new_content = $this->rewrite_img_alts( $post['post_content'], $attachment_id, $base, $alt_text );
			if ( $new_content !== $post['post_content'] ) {
				$wpdb->update(
					$wpdb->posts,
					array( 'post_content' => $new_content ),
					array( 'ID'           => $post['ID'] )
				);
				clean_post_cache( $post['ID'] );
				$updated++;
			}
		}

		// ── 2. postmeta (ACF WYSIWYG fields, page-builder HTML meta, etc.) ──
		// Only process rows that are plaintext HTML — skip serialized/JSON data.
		$meta_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_id, meta_value
				   FROM {$wpdb->postmeta}
				  WHERE ( meta_value LIKE %s
				       OR meta_value LIKE %s )
				    AND meta_value LIKE '%<img%'",
				$id_like,
				$url_like
			),
			ARRAY_A
		);

		foreach ( $meta_rows as $row ) {
			// Skip serialized data — altering it with regex would corrupt it.
			if ( is_serialized( $row['meta_value'] ) ) {
				continue;
			}

			$new_value = $this->rewrite_img_alts( $row['meta_value'], $attachment_id, $base, $alt_text );
			if ( $new_value !== $row['meta_value'] ) {
				$wpdb->update(
					$wpdb->postmeta,
					array( 'meta_value' => $new_value ),
					array( 'meta_id'    => $row['meta_id'] )
				);
				$updated++;
			}
		}

		return $updated;
	}

	/**
	 * Replaces the alt attribute on every <img> tag in $html that belongs to
	 * the given attachment (matched by wp-image-{id} class or base filename).
	 *
	 * @param string $html
	 * @param int    $attachment_id
	 * @param string $base  Base file path without size suffix or extension.
	 * @param string $alt_text
	 * @return string
	 */
	private function rewrite_img_alts( $html, $attachment_id, $base, $alt_text ) {
		return preg_replace_callback(
			'/<img\s[^>]+>/i',
			function ( $m ) use ( $attachment_id, $base, $alt_text ) {
				$tag = $m[0];

				if ( strpos( $tag, 'wp-image-' . $attachment_id ) === false
					&& strpos( $tag, $base ) === false ) {
					return $tag;
				}

				// Remove any existing alt attribute (handles single and double quotes).
				$tag = preg_replace( '/\s+alt=(["\'])[^"\']*\1/i', '', $tag );

				// Insert the new alt attribute just before the closing >.
				$close = strrpos( $tag, '>' );
				if ( false === $close ) {
					return $tag;
				}

				return substr( $tag, 0, $close )
					. ' alt="' . esc_attr( $alt_text ) . '"'
					. substr( $tag, $close );
			},
			$html
		);
	}
}

new Search_Alt_Tags();

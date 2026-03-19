<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scans active theme template files for <img> tags with missing or
 * potentially-empty alt attributes.
 *
 * Severity levels
 * ---------------
 *  error   — <img> with NO alt attribute at all (definite accessibility issue)
 *  warning — <img> with alt="" (static empty string; may be intentional for
 *             decorative images, but flagged for review)
 *  notice  — <img> with alt containing only a PHP expression that could
 *             resolve to empty at runtime (e.g. alt="<?php echo $var; ?>")
 */
class SAT_Theme_Scanner {

	const CACHE_KEY = 'sat_theme_scan';
	const CACHE_TTL = 43200; // 12 hours in seconds

	/** File extensions to scan. */
	const SCAN_EXTENSIONS = array( 'php', 'html' );

	/**
	 * Directory names to skip entirely when walking the theme tree.
	 * Covers compiled assets, package managers, and VCS folders.
	 */
	const EXCLUDE_DIRS = array(
		'node_modules',
		'vendor',
		'.git',
		'_compiled-styles',
		'_compiled',
		'_static',
		'bower_components',
	);

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Returns scan results, using the cache if available.
	 *
	 * @return array See self::build_result_shape().
	 */
	public function get_results() {
		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			return $cached;
		}
		return $this->run_scan();
	}

	/**
	 * Returns cached results without triggering a fresh scan.
	 * Returns null if no cache exists yet.
	 *
	 * @return array|null
	 */
	public function get_cached_results() {
		$cached = get_transient( self::CACHE_KEY );
		return false !== $cached ? $cached : null;
	}

	/**
	 * Deletes the cached results so the next call to get_results() rescans.
	 */
	public function clear_cache() {
		delete_transient( self::CACHE_KEY );
	}

	// -------------------------------------------------------------------------
	// Core scan logic
	// -------------------------------------------------------------------------

	/**
	 * Walks the active theme directory, scans every eligible file, and caches
	 * the results.
	 *
	 * @return array
	 */
	private function run_scan() {
		$theme_dir = get_template_directory();
		$files     = $this->collect_files( $theme_dir );
		$by_file   = array();

		foreach ( $files as $absolute_path ) {
			$issues = $this->scan_file( $absolute_path );
			if ( ! empty( $issues ) ) {
				// Store relative path so UI doesn't expose server filesystem root.
				$relative          = str_replace( $theme_dir . DIRECTORY_SEPARATOR, '', $absolute_path );
				$by_file[ $relative ] = $issues;
			}
		}

		// Count totals
		$total_issues = 0;
		$counts       = array( 'error' => 0, 'warning' => 0, 'notice' => 0 );
		foreach ( $by_file as $issues ) {
			foreach ( $issues as $issue ) {
				$total_issues++;
				$counts[ $issue['severity'] ]++;
			}
		}

		$result = array(
			'files'        => $by_file,
			'total_issues' => $total_issues,
			'counts'       => $counts,
			'files_scanned'=> count( $files ),
			'theme_name'   => wp_get_theme()->get( 'Name' ),
			'theme_dir'    => $theme_dir,
			'scanned_at'   => current_time( 'mysql' ),
		);

		set_transient( self::CACHE_KEY, $result, self::CACHE_TTL );

		return $result;
	}

	/**
	 * Recursively collects all scannable files under $dir.
	 *
	 * @param string $dir Absolute path to start from.
	 * @return string[]   Absolute file paths.
	 */
	private function collect_files( $dir ) {
		$files = array();

		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveCallbackFilterIterator(
					new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
					array( $this, 'filter_iterator' )
				),
				RecursiveIteratorIterator::SELF_FIRST
			);

			foreach ( $iterator as $file ) {
				if ( $file->isFile() && in_array( strtolower( $file->getExtension() ), self::SCAN_EXTENSIONS, true ) ) {
					$files[] = $file->getPathname();
				}
			}
		} catch ( Exception $e ) {
			// If the directory isn't readable, return an empty list.
		}

		return $files;
	}

	/**
	 * Callback for RecursiveCallbackFilterIterator — skips excluded directories.
	 *
	 * @param SplFileInfo                $current  Current file/dir.
	 * @param string                     $key      Current path.
	 * @param RecursiveDirectoryIterator $iterator Iterator.
	 * @return bool True to include the entry.
	 */
	public function filter_iterator( $current, $key, $iterator ) {
		if ( $current->isDir() ) {
			return ! in_array( $current->getFilename(), self::EXCLUDE_DIRS, true );
		}
		return true;
	}

	/**
	 * Scans a single file for problematic <img> tags.
	 *
	 * PHP blocks (<?php ... ?> and <?= ... ?>) are replaced with a safe
	 * placeholder before matching so that '>' characters inside PHP expressions
	 * (e.g. alt="<?php esc_attr_e('Label'); ?>") don't prematurely terminate
	 * the <img … > regex. Line counts are preserved so reported line numbers
	 * remain accurate, and snippets are pulled from the original source.
	 *
	 * @param string $filepath Absolute path to the file.
	 * @return array           Array of issue arrays (may be empty).
	 */
	private function scan_file( $filepath ) {
		$content = @file_get_contents( $filepath ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $content || '' === $content ) {
			return array();
		}

		// Replace every PHP block with __PHPEXPR__, preserving newline count
		// so that line numbers in the cleaned string still match the original.
		$cleaned = preg_replace_callback(
			'/<\?(?:php|=).*?\?>/si',
			function ( $m ) {
				return str_repeat( "\n", substr_count( $m[0], "\n" ) ) . '__PHPEXPR__';
			},
			$content
		);

		$issues     = array();
		$orig_lines = explode( "\n", $content );

		if ( ! preg_match_all( '/<img\b[^>]*>/i', $cleaned, $matches, PREG_OFFSET_CAPTURE ) ) {
			return array();
		}

		foreach ( $matches[0] as $match ) {
			$cleaned_tag = $match[0];
			$offset      = $match[1];

			// Derive the 1-based line number from the cleaned content offset.
			$line_num = substr_count( substr( $cleaned, 0, $offset ), "\n" ) + 1;

			$issue = $this->classify_tag( $cleaned_tag, $line_num );
			if ( null !== $issue ) {
				// Replace the placeholder-based snippet with the original source.
				$issue['snippet'] = $this->make_snippet(
					$this->extract_original_tag( $orig_lines, $line_num )
				);
				$issues[] = $issue;
			}
		}

		return $issues;
	}

	/**
	 * Extracts the original <img> tag from the source lines starting at
	 * $line_num. Looks ahead up to 5 lines to handle multi-line tags.
	 *
	 * @param string[] $orig_lines Lines of the original file content.
	 * @param int      $line_num   1-based line number.
	 * @return string
	 */
	private function extract_original_tag( $orig_lines, $line_num ) {
		$chunk = implode( ' ', array_slice( $orig_lines, $line_num - 1, 5 ) );
		if ( preg_match( '/<img\b.*?>/si', $chunk, $m ) ) {
			return $m[0];
		}
		return $orig_lines[ $line_num - 1 ] ?? '';
	}

	/**
	 * Returns an issue descriptor for a given <img> tag, or null if no issue.
	 * The $tag passed here has PHP blocks replaced with __PHPEXPR__.
	 *
	 * @param string $tag      The cleaned <img …> string.
	 * @param int    $line_num Line number in the source file.
	 * @return array|null
	 */
	private function classify_tag( $tag, $line_num ) {
		$has_alt_attr = (bool) preg_match( '/\balt\s*=/i', $tag );

		// ── Error: no alt attribute at all ───────────────────────────────────
		if ( ! $has_alt_attr ) {
			return array(
				'severity' => 'error',
				'type'     => 'missing',
				'label'    => 'Missing alt',
				'line'     => $line_num,
			);
		}

		// ── Warning: alt is a static empty string ─────────────────────────────
		// Matches alt="" or alt='' (with optional whitespace inside the quotes).
		if ( preg_match( '/\balt\s*=\s*(["\'])\s*\1/i', $tag ) ) {
			return array(
				'severity' => 'warning',
				'type'     => 'empty',
				'label'    => 'Empty alt',
				'line'     => $line_num,
			);
		}

		// ── Notice: alt value is a pure PHP expression ────────────────────────
		// After preprocessing, PHP blocks become __PHPEXPR__. If the alt value
		// contains only that placeholder (no surrounding static text) it means
		// the alt is entirely dynamic and may resolve to empty at runtime.
		if ( preg_match( '/\balt\s*=\s*(["\'])\s*__PHPEXPR__\s*\1/i', $tag ) ) {
			return array(
				'severity' => 'notice',
				'type'     => 'dynamic',
				'label'    => 'Dynamic alt (may be empty)',
				'line'     => $line_num,
			);
		}

		return null; // Tag has a non-empty static (or mixed) alt — no issue.
	}

	/**
	 * Trims whitespace and collapses internal newlines for display.
	 * Caps the snippet at 200 chars to keep the UI readable.
	 *
	 * @param string $tag Raw tag HTML.
	 * @return string
	 */
	private function make_snippet( $tag ) {
		$tag = preg_replace( '/\s+/', ' ', trim( $tag ) );
		if ( strlen( $tag ) > 200 ) {
			$tag = substr( $tag, 0, 197 ) . '…';
		}
		return $tag;
	}
}

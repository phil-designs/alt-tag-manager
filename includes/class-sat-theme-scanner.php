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
	 * @param string $filepath Absolute path to the file.
	 * @return array           Array of issue arrays (may be empty).
	 */
	private function scan_file( $filepath ) {
		$content = @file_get_contents( $filepath ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $content || '' === $content ) {
			return array();
		}

		$issues = array();

		/**
		 * Match <img …> tags including those that span multiple lines.
		 *
		 * [^>]* stops at the next > so it correctly handles self-closing tags.
		 * The `i` flag makes the match case-insensitive.
		 */
		if ( ! preg_match_all( '/<img\b[^>]*>/i', $content, $matches, PREG_OFFSET_CAPTURE ) ) {
			return array();
		}

		foreach ( $matches[0] as $match ) {
			$tag    = $match[0];
			$offset = $match[1];

			// Derive the 1-based line number from the character offset.
			$line_num = substr_count( substr( $content, 0, $offset ), "\n" ) + 1;

			$issue = $this->classify_tag( $tag, $line_num );
			if ( null !== $issue ) {
				$issues[] = $issue;
			}
		}

		return $issues;
	}

	/**
	 * Returns an issue descriptor for a given <img> tag, or null if no issue.
	 *
	 * @param string $tag      The raw <img …> string.
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
				'snippet'  => $this->make_snippet( $tag ),
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
				'snippet'  => $this->make_snippet( $tag ),
			);
		}

		// ── Notice: alt value is a bare PHP expression with no static fallback ─
		// Matches alt attributes whose only content is a PHP echo block.
		if ( preg_match( '/\balt\s*=\s*(["\'])\s*<\?php[^?]*(?:\?(?!>)[^?]*)?\?>\s*\1/i', $tag ) ) {
			return array(
				'severity' => 'notice',
				'type'     => 'dynamic',
				'label'    => 'Dynamic alt (may be empty)',
				'line'     => $line_num,
				'snippet'  => $this->make_snippet( $tag ),
			);
		}

		return null; // Tag has an alt attribute with static content — no issue.
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

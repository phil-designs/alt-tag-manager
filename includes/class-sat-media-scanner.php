<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queries the media library for image attachments missing an alt tag.
 */
class SAT_Media_Scanner {

	/**
	 * Returns a paginated list of images with missing/empty alt tags.
	 *
	 * @param int $page     1-based page number.
	 * @param int $per_page Items per page.
	 * @return array {
	 *   @type array $images   Formatted image data.
	 *   @type int   $total    Total count of images missing alt tags.
	 *   @type int   $pages    Total page count.
	 *   @type int   $page     Current page.
	 *   @type int   $per_page Items per page.
	 * }
	 */
	public function get_images_missing_alt( $page = 1, $per_page = 20 ) {
		$args = array(
			'post_type'      => 'attachment',
			'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml' ),
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => '_wp_attachment_image_alt',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_wp_attachment_image_alt',
					'value'   => '',
					'compare' => '=',
				),
			),
		);

		$query   = new WP_Query( $args );
		$all_ids = $query->posts;
		$total   = count( $all_ids );
		$pages   = $per_page > 0 ? (int) ceil( $total / $per_page ) : 1;

		$offset   = ( $page - 1 ) * $per_page;
		$page_ids = array_slice( $all_ids, $offset, $per_page );

		$images = array();
		foreach ( $page_ids as $id ) {
			$images[] = $this->format_attachment( $id );
		}

		return array(
			'images'   => $images,
			'total'    => $total,
			'pages'    => max( 1, $pages ),
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * Returns all attachment IDs that are missing or have an empty alt tag.
	 * Used by the bulk-generate feature to process every page at once.
	 *
	 * @return int[]
	 */
	public function get_all_ids_missing_alt() {
		$args = array(
			'post_type'      => 'attachment',
			'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml' ),
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => '_wp_attachment_image_alt',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_wp_attachment_image_alt',
					'value'   => '',
					'compare' => '=',
				),
			),
		);

		$query = new WP_Query( $args );
		return array_map( 'intval', $query->posts );
	}

	/**
	 * Builds a display-ready array for a single attachment.
	 *
	 * @param int $id Attachment post ID.
	 * @return array
	 */
	private function format_attachment( $id ) {
		$post      = get_post( $id );
		$meta      = wp_get_attachment_metadata( $id );
		$full_url  = wp_get_attachment_url( $id );
		$thumb     = wp_get_attachment_image_url( $id, 'thumbnail' );
		$alt       = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
		$file_size = '';

		$attached_file = get_attached_file( $id );
		if ( $attached_file && file_exists( $attached_file ) ) {
			$file_size = size_format( filesize( $attached_file ) );
		}

		$dimensions = '';
		if ( ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
			$dimensions = $meta['width'] . ' × ' . $meta['height'];
		}

		return array(
			'id'         => $id,
			'title'      => $post ? $post->post_title : '',
			'filename'   => basename( $full_url ),
			'full_url'   => $full_url,
			'thumb_url'  => $thumb ?: '',
			'alt'        => $alt,
			'file_size'  => $file_size,
			'dimensions' => $dimensions,
			'date'       => $post ? get_the_date( 'M j, Y', $post ) : '',
		);
	}
}

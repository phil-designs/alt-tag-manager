<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles communication with the Anthropic Claude API to generate alt tags
 * by analysing images using the vision capability.
 */
class SAT_Claude {

	const API_URL = 'https://api.anthropic.com/v1/messages';
	const MODEL   = 'claude-haiku-4-5-20251001';

	/** @var string */
	private $api_key;

	/**
	 * @param string $api_key Anthropic API key.
	 */
	public function __construct( $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * Sends an image to Claude and returns a suggested alt tag.
	 *
	 * @param string $image_url Publicly accessible URL of the image.
	 * @return string|WP_Error
	 */
	public function generate_alt_tag( $image_url ) {
		$mime = $this->get_mime_type( $image_url );

		// SVG files cannot be sent as vision inputs — fall back to filename.
		if ( 'image/svg+xml' === $mime ) {
			return $this->generate_from_filename( $image_url );
		}

		$payload = array(
			'model'      => self::MODEL,
			'max_tokens' => 256,
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => array(
						array(
							'type'   => 'image',
							'source' => array(
								'type' => 'url',
								'url'  => $image_url,
							),
						),
						array(
							'type' => 'text',
							'text' => 'Write a concise, descriptive alt tag for this image that accurately describes its content for screen-reader users. Focus on what is visually present. Respond with only the alt tag text — no quotes, no explanation, no trailing punctuation unless it is a natural part of the description.',
						),
					),
				),
			),
		);

		return $this->request( $payload );
	}

	/**
	 * Fallback for non-vision-compatible files: generates an alt tag from the
	 * filename by sending a short text-only prompt.
	 *
	 * @param string $image_url Image URL.
	 * @return string|WP_Error
	 */
	private function generate_from_filename( $image_url ) {
		$filename = pathinfo( wp_parse_url( $image_url, PHP_URL_PATH ), PATHINFO_FILENAME );
		$label    = ucwords( str_replace( array( '-', '_', '.' ), ' ', $filename ) );

		$payload = array(
			'model'      => self::MODEL,
			'max_tokens' => 128,
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => 'Based only on this image filename, write a concise alt tag in plain English (no quotes, no explanation): "' . esc_html( $label ) . '"',
				),
			),
		);

		return $this->request( $payload );
	}

	/**
	 * Executes the API request and extracts the first text block from the response.
	 *
	 * @param array $payload
	 * @return string|WP_Error
	 */
	private function request( $payload ) {
		$response = wp_remote_post( self::API_URL, array(
			'timeout' => 30,
			'headers' => array(
				'Content-Type'      => 'application/json',
				'x-api-key'         => $this->api_key,
				'anthropic-version' => '2023-06-01',
			),
			'body' => wp_json_encode( $payload ),
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'sat_request_failed',
				sprintf( __( 'API request failed: %s', 'search-alt-tags' ), $response->get_error_message() )
			);
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status ) {
			$message = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Unknown API error.', 'search-alt-tags' );
			return new WP_Error( 'sat_api_error', sprintf( 'Claude API error (%d): %s', $status, $message ) );
		}

		if ( ! empty( $body['content'] ) ) {
			foreach ( $body['content'] as $block ) {
				if ( isset( $block['type'] ) && 'text' === $block['type'] && ! empty( $block['text'] ) ) {
					return trim( $block['text'] );
				}
			}
		}

		return new WP_Error( 'sat_empty_response', __( 'Claude returned an empty response.', 'search-alt-tags' ) );
	}

	/**
	 * Infers the MIME type from the URL's file extension.
	 *
	 * @param string $url
	 * @return string
	 */
	private function get_mime_type( $url ) {
		$ext = strtolower( pathinfo( wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
		$map = array(
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'gif'  => 'image/gif',
			'webp' => 'image/webp',
			'svg'  => 'image/svg+xml',
		);
		return isset( $map[ $ext ] ) ? $map[ $ext ] : 'image/jpeg';
	}
}

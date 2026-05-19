<?php
namespace BricksMCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tool_Media extends Tool_Base {

	public function define(): array {
		return [
			[
				'name'        => 'bricks_list_media',
				'description' => 'List media library items (images, documents, videos). Returns id, title, URL, MIME type, and dimensions.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'per_page'  => [ 'type' => 'integer', 'description' => 'Results per page (default 20)', 'default' => 20 ],
						'page'      => [ 'type' => 'integer', 'description' => 'Page number', 'default' => 1 ],
						'mime_type' => [ 'type' => 'string', 'description' => 'Filter by MIME type: image | video | audio | application/pdf' ],
						'search'    => [ 'type' => 'string', 'description' => 'Search keyword in title/filename' ],
					],
				],
			],
			[
				'name'        => 'bricks_upload_media_from_url',
				'description' => 'Upload a media file from an external URL to the WordPress media library. Useful for importing images from design specs or CDNs.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'url'   => [ 'type' => 'string', 'description' => 'External file URL to download and import' ],
						'title' => [ 'type' => 'string', 'description' => 'Media title (optional, defaults to filename)' ],
						'alt'   => [ 'type' => 'string', 'description' => 'Alt text for images' ],
					],
					'required' => [ 'url' ],
				],
			],
			[
				'name'        => 'bricks_get_media',
				'description' => 'Get full details for a specific media attachment — URL, alt text, sizes, metadata.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'media_id' => [ 'type' => 'integer', 'description' => 'Attachment post ID' ],
					],
					'required' => [ 'media_id' ],
				],
			],
		];
	}

	public function execute( string $name, array $args ): array|\WP_Error {
		switch ( $name ) {
			case 'bricks_list_media':
				return $this->list_media( $args );
			case 'bricks_upload_media_from_url':
				return $this->upload_from_url( $args );
			case 'bricks_get_media':
				return $this->get_media( $args );
		}
		return $this->err( 'Unknown tool: ' . $name );
	}

	private function list_media( array $args ): array {
		$per_page  = min( $this->int_arg( $args, 'per_page', 20 ), 100 );
		$page      = max( $this->int_arg( $args, 'page', 1 ), 1 );
		$search    = $this->str_arg( $args, 'search' );
		$mime_type = $this->str_arg( $args, 'mime_type' );

		$query_args = [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		if ( $search ) {
			$query_args['s'] = sanitize_text_field( $search );
		}

		if ( $mime_type ) {
			$query_args['post_mime_type'] = sanitize_text_field( $mime_type );
		}

		$query  = new \WP_Query( $query_args );
		$media  = [];

		foreach ( $query->posts as $post ) {
			$meta = wp_get_attachment_metadata( $post->ID );
			$media[] = [
				'id'        => $post->ID,
				'title'     => $post->post_title,
				'url'       => wp_get_attachment_url( $post->ID ),
				'mime_type' => $post->post_mime_type,
				'alt'       => get_post_meta( $post->ID, '_wp_attachment_image_alt', true ),
				'width'     => $meta['width'] ?? null,
				'height'    => $meta['height'] ?? null,
				'filename'  => basename( get_attached_file( $post->ID ) ?: '' ),
				'date'      => $post->post_date,
			];
		}

		return [
			'media'       => $media,
			'total'       => $query->found_posts,
			'total_pages' => $query->max_num_pages,
			'page'        => $page,
			'per_page'    => $per_page,
		];
	}

	private function upload_from_url( array $args ): array|\WP_Error {
		$err = $this->require_cap( 'upload_files' );
		if ( $err ) return $err;

		$url = $this->str_arg( $args, 'url' );
		if ( ! $url ) return $this->err( '"url" is required.' );

		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return $this->err( 'Invalid URL provided.' );
		}

		// Load media handling functions if not available
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}
		if ( ! function_exists( 'wp_read_image_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$tmp_file = download_url( $url );
		if ( is_wp_error( $tmp_file ) ) {
			return $this->err( 'Failed to download file: ' . $tmp_file->get_error_message() );
		}

		$file_array = [
			'name'     => basename( parse_url( $url, PHP_URL_PATH ) ) ?: 'upload',
			'tmp_name' => $tmp_file,
		];

		$title = $this->str_arg( $args, 'title' );

		$attachment_id = media_handle_sideload( $file_array, 0, $title ?: null );

		@unlink( $tmp_file );

		if ( is_wp_error( $attachment_id ) ) {
			return $this->err( 'Failed to import file: ' . $attachment_id->get_error_message() );
		}

		$alt = $this->str_arg( $args, 'alt' );
		if ( $alt ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
		}

		$meta = wp_get_attachment_metadata( $attachment_id );

		return [
			'id'           => $attachment_id,
			'url'          => wp_get_attachment_url( $attachment_id ),
			'thumbnail_url'=> wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) ?: '',
			'width'        => $meta['width'] ?? null,
			'height'       => $meta['height'] ?? null,
			'message'      => 'Media uploaded successfully.',
		];
	}

	private function get_media( array $args ): array|\WP_Error {
		$media_id = $this->int_arg( $args, 'media_id' );
		if ( ! $media_id ) return $this->err( '"media_id" is required.' );

		$post = get_post( $media_id );
		if ( ! $post || $post->post_type !== 'attachment' ) {
			return $this->err( "Media {$media_id} not found." );
		}

		$meta = wp_get_attachment_metadata( $media_id );

		$sizes = [];
		if ( isset( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $size_name => $size_data ) {
				$url = wp_get_attachment_image_url( $media_id, $size_name );
				if ( $url ) {
					$sizes[ $size_name ] = [
						'url'    => $url,
						'width'  => $size_data['width'],
						'height' => $size_data['height'],
					];
				}
			}
		}

		return [
			'id'        => $media_id,
			'title'     => $post->post_title,
			'url'       => wp_get_attachment_url( $media_id ),
			'mime_type' => $post->post_mime_type,
			'alt'       => get_post_meta( $media_id, '_wp_attachment_image_alt', true ),
			'caption'   => $post->post_excerpt,
			'width'     => $meta['width'] ?? null,
			'height'    => $meta['height'] ?? null,
			'sizes'     => $sizes,
			'filename'  => basename( get_attached_file( $media_id ) ?: '' ),
			'date'      => $post->post_date,
		];
	}
}

<?php

/**
 * media extractor for wp posts
 * Class Post_Images_Extractor
 */
class Post_Images_Extractor implements Images_Extractor {
	/**
	 * @var string[]
	 */
	private array $galleries_images = array();
	/**
	 * @var string[]
	 */
	private array $posts_images_urls = array();
	/**
	 * @var Images_Extractor_Helper
	 */
	private Images_Extractor_Helper $helper;

	private  bool $ignore_media;

	/**
	 * Post_Images_Extractor constructor.
	 *
	 * @param bool $ignore_media
	 */
	public function __construct(bool $ignore_media) {
		$this->helper = Images_Extractor_Helper::get_helper();
		$this->ignore_media = $ignore_media;
	}

	/**
	 * @inerhitDoc
	 * @throws Exception
	 */
	public function scan_once(): void {
		$this->posts_images_urls = array_merge( $this->posts_images_urls, $this->helper->get_images_from_themes() );
		if ($this->ignore_media === false) {
			$this->scan_images_from_media();
		}
	}

	/**
	 * @inheritDoc
	 * @throws Exception
	 */
	public function scan_post( $post_id, string $content ): void {
		$this->scan_body( $post_id, $content );
	}

	/**
	 * @inheritDoc
	 */
	public function scan_widget( $widget ): void {
	}

	/**
	 * @param $post_id
	 * @param string $content
	 *
	 * @throws Exception
	 */
	private function scan_body( $post_id, string $content ): void {
		$galleries_images  = array();
		$posts_images_urls = $this->helper->get_urls_from_html( $content );
		$excerpt           = get_post_field( 'post_excerpt', $post_id );
		if ( ! empty( $excerpt ) ) {
			$posts_images_urls = array_merge( $posts_images_urls, $this->helper->get_urls_from_html( $excerpt ) );
		}

		$galleries = get_post_galleries_images( $post_id );
		foreach ( $galleries as $gallery ) {
			foreach ( $gallery as $image ) {
				array_push( $galleries_images, $this->helper->clean_url( $image ) );
			}
		}
		$this->posts_images_urls = array_merge( $this->posts_images_urls, $posts_images_urls );
		$this->galleries_images  = array_merge( $this->galleries_images, $galleries_images );
	}

	/**
	 * @inheritDoc
	 */
	public function get_used_images(): array {
		return array_unique( $this->posts_images_urls );
	}

	private function scan_images_from_media():void {
		/**
		 * @var $db wpdb
		 */
		$db    = WC()->get_global( 'wpdb' );
		/** @noinspection SqlNoDataSourceInspection */
		$sql   = <<< SQL
		SELECT meta_value FROM {$db->postmeta} 
		WHERE meta_key = '_wp_attachment_metadata' 
SQL;
		$metas = $db->get_col( $db->prepare( $sql ) );
		if ( count( $metas ) === 0 ) {
			return;
		}
		foreach ( $metas as $meta ) {
			$this->extract_media_from_meta( $meta );
		}

	}

	/**
	 * @param $meta
	 */
	private function extract_media_from_meta( $meta ): void {
		if ( ! is_serialized( $meta ) ) {
			return;
		}
		$decoded = @unserialize( $meta );
		if ( is_array( $decoded ) ) {
			$prepared   = array();
			$prepared[] = $decoded['file'];
			foreach ( $decoded['sizes'] as $size => $size_info ) {
				$dirname = _wp_get_attachment_relative_path( $decoded['file'] );
				if ( $dirname ) {
					$dirname = trailingslashit( $dirname );
					$name       = $dirname . $size_info['file'];
					$prepared[] = $name;
				}
			}
			$this->posts_images_urls = array_merge( $this->posts_images_urls, $prepared );
		}
	}

}


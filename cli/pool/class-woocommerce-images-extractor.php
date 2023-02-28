<?php

/**
 * Woocommerce media extractor
 * Class Woocommerce_Images_Extractor
 */
class Woocommerce_Images_Extractor implements Images_Extractor {
	/**
	 * @var string[]
	 */
	private array $posts_images_urls = array();
	/**
	 * @var Images_Extractor_Helper
	 */
	private Images_Extractor_Helper $helper;

	private bool $ignore_media;

	/**
	 * Woocommerce_Images_Extractor constructor.
	 *
	 * @param bool $ignore_media
	 */
	public function __construct(bool $ignore_media) {
		$this->helper = Images_Extractor_Helper::get_helper();
		$this->ignore_media = $ignore_media;
	}

	/**
	 * @inerhitDoc
	 */
	public function scan_once(): void {

	}

	/**
	 * @inheritDoc
	 */
	public function scan_post( $post_id, string $content ): void {
		$this->scan_meta( $post_id );
	}

	/**
	 * @inheritDoc
	 */
	public function scan_widget( $widget ): void {
	}

	/**
	 * @param $id
	 */
	private function scan_meta( $id ): void {
		/**
		 * @var $db wpdb
		 */
		$db    = WC()->get_global( 'wpdb' );
		/** @noinspection SqlNoDataSourceInspection */
		$sql   = <<< SQL
		SELECT t1.meta_value FROM {$db->postmeta} t1
		INNER JOIN wp_postmeta as t2 
		ON t2.post_id = t1.post_id
		WHERE t1.post_id = %d
		AND t1.meta_key = '_wp_attachment_metadata' 
		AND t2.meta_key ='_wc_attachment_source'
SQL;
		$metas = $db->get_col( $db->prepare( $sql, $id ) );
		if ( count( $metas ) === 0 ) {
			return;
		}
		foreach ( $metas as $meta ) {
			$this->extract_media_from_meta( $meta, $id );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function get_used_images(): array {
		return array_unique( $this->posts_images_urls );
	}

	/**
	 * @param $meta
	 * @param $id
	 */
	private function extract_media_from_meta( $meta, $id ): void {
		if ( ! is_serialized( $meta ) ) {
			return;
		}
		$decoded = @unserialize( $meta );
		if ( is_array( $decoded ) ) {
			$prepared   = array();
			$data       = wp_get_attachment_metadata( $id );
			$prepared[] = $data['file'];
			foreach ( $data['sizes'] as $size => $size_info ) {
				$dirname = _wp_get_attachment_relative_path( $data['file'] );
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


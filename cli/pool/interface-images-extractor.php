<?php

/**
 * Media extractor interface.
 * every Extractor class must implement this interface
 * Interface Images_Extractor
 */
interface Images_Extractor {
	/**
	 * Scan Data Once (general data templates< custom tables etc)
	 */
	public function scan_once(): void;

	/**
	 * Scan Single Post
	 *
	 * @param $post_id
	 * @param string $content
	 */
	public function scan_post( $post_id, string $content ): void;

	/**
	 * Scan One Widget ( menu etc)
	 *
	 * @param $widget
	 */
	public function scan_widget( $widget ): void;

	/**
	 * used images getter
	 * @return array
	 */
	public function get_used_images(): array;

}

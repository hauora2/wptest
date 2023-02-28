<?php
/** @noinspection PhpIncludeInspection */
require_once( get_template_directory() . '/cli/pool/interface-images-extractor.php' );
require_once( get_template_directory() . '/cli/pool/class-post-images-extractor.php' );
require_once( get_template_directory() . '/cli/helper/class-images-extractor-helper.php' );
if ( class_exists( 'WooCommerce' ) ) {
	require_once( get_template_directory() . '/cli/pool/class-woocommerce-images-extractor.php' );
}

/**
 * Class Post_Scanner
 */
class Post_Scanner {
	/**
	 * Uploads directory subfolders scan level
	 */
	const
		UPLOAD_LEVELS = 5;

	/**
	 * @var Images_Extractor[]
	 */
	private array $scanner_pool = [];

	/**
	 * Post_Scanner constructor.
	 *
	 * @param bool $ignore_media
	 */
	public function __construct( bool $ignore_media ) {
		$this->make_scanner_pool( $ignore_media );
	}

	/**
	 * Scan Posts
	 * slide effect method
	 *
	 */
	public function scan_posts(): void {
		$post_ids = $this->get_all_post_ids();
		if ( count( $post_ids ) === 0 ) {
			return;
		}
		$this->extract_images_from_content();
		$this->extract_images_from_posts( $post_ids );
	}

	/**
	 * All content media getter
	 *
	 *
	 * @return string[]
	 */
	public function get_used_images(): array {
		$res = array();
		foreach ( $this->scanner_pool as $extractor ) {
			$res = array_merge( $res, $extractor->get_used_images() );
		}

		return array_unique( $res );
	}

	/**
	 * All upload directory media getter
	 * @return string[]
	 */
	public function get_all_media_files_from_dir(): array {
		$ret     = array();
		$subpath = $this->get_uploads_dir() . '/';
		foreach ( list_files( $this->get_uploads_dir(), self::UPLOAD_LEVELS ) as $v ) {
			$ret[] = str_replace( $subpath, '', $v );
		}

		return $ret;
	}


	/**
	 * @return string
	 */
	private function get_uploads_dir(): string {
		return wp_upload_dir()['basedir'];
	}


	/**
	 * create extractors pool
	 *
	 * @param bool $ignore_media
	 */
	private function make_scanner_pool( bool $ignore_media ): void {
		$this->scanner_pool[] = new Post_Images_Extractor( $ignore_media );
		if ( class_exists( 'WooCommerce' ) ) {
			$this->scanner_pool[] = new Woocommerce_Images_Extractor( $ignore_media );
		}

	}

	/**
	 * get all post Ids
	 * @return array
	 */
	private function get_all_post_ids(): array {
		/**
		 * @var $db wpdb
		 */
		$db = WC()->get_global( 'wpdb' );
		/** @noinspection SqlNoDataSourceInspection */
		$sql = <<<SQL
		SELECT p.ID FROM {$db->posts} p
		WHERE p.post_status NOT IN ('trash')
		AND p.post_type NOT IN ( 'shop_order', 'shop_order_refund', 'nav_menu_item', 'revision', 'auto-draft', 'wphb_minify_group', 'customize_changeset', 'oembed_cache', 'nf_sub', 'jp_img_sitemap')
		AND p.post_type NOT LIKE 'dlssus_%'
		AND p.post_type NOT LIKE 'ml-slide%'
		AND p.post_type NOT LIKE '%acf-%'
		AND p.post_type NOT LIKE '%edd_%'
SQL;

		return $db->get_col( $sql );
	}

	/**
	 * extract images from general content ( widgets and theme files )
	 *
	 */
	private function extract_images_from_content(): void {
		global $wp_registered_widgets;
		$sidebars_widgets   = get_option( 'sidebars_widgets' );
		$registered_widgets = $wp_registered_widgets;
		foreach ( $sidebars_widgets as $sidebar_name => $widgets ) {
			if ( $sidebar_name != 'wp_inactive_widgets' && ! empty( $widgets ) && is_array( $widgets ) ) {
				foreach ( $widgets as $key => $widget ) {
					$this->scan_widget( $registered_widgets[ $widget ] );
				}
			}
		}
		$this->scan_once();
	}

	/**
	 * extract images from posts
	 *
	 * @param array $posts
	 */
	private function extract_images_from_posts( array $posts ): void {
		foreach ( $posts as $post ) {
			$this->scan_post( $post );
		}
	}


	/**
	 * Scan one widget using pool
	 *
	 * @param $widget
	 */
	private function scan_widget( $widget ): void {
		foreach ( $this->scanner_pool as $extractor ) {
			//TODO for future extensions
			$extractor->scan_widget( $widget );
		}
	}

	/**
	 * Scan once using pool
	 */
	private function scan_once(): void {
		foreach ( $this->scanner_pool as $extractor ) {
			$extractor->scan_once();
		}
	}

	/**
	 * scan One post
	 *
	 * @param $post_id
	 */
	private function scan_post( $post_id ): void {
		$html = get_post_field( 'post_content', $post_id );
		foreach ( $this->scanner_pool as $extractor ) {
			$extractor->scan_post( $post_id, $html );

		}
	}


}

<?php

/**
 * single tone class
 * Class Images_Extractor_Helper
 */
class Images_Extractor_Helper {
	/**
	 * @var Images_Extractor_Helper|null
	 */
	private static Images_Extractor_Helper|null $helper_object = null;
	/**
	 * @var string
	 */
	private string $site_url;
	/**
	 * @var mixed|string
	 */
	private string$upload_path;
	/**
	 * @var false|string
	 */
	private string|false $upload_url;

	/**
	 * Images_Extractor_Helper constructor.
	 * Not for direct use
	 */
	private function __construct() {
		$this->site_url = get_site_url();
		$upload_dir = wp_upload_dir();
		$this->upload_path = $upload_dir['basedir'];
		$this->upload_url = substr( $upload_dir['baseurl'], 1 + strlen( $this->site_url ) );
	}

	/**
	 * public fabric method
	 * @return Images_Extractor_Helper
	 */
	public static function get_helper():Images_Extractor_Helper {
		if ( self::$helper_object === null ) {
			self::$helper_object = new self();
		}
		return self::$helper_object;
	}

	/**
	 * @return string[]
	 * @throws Exception
	 */
	public function get_images_from_themes():array {
		$urls = array();
		$header = get_custom_header();
		if ( !empty( $header ) && !empty( $header->url ) ) {
			array_push( $urls, $this->clean_url( $header->url ) );
		}
		if ( $this->is_url( $header->thumbnail_url ) ) {
			array_push( $urls, $this->clean_url( $header->thumbnail_url ) );
		}
		$custom_logo = get_custom_logo();
		if ( $this->is_url( $custom_logo ) ) {
			$urls = array_merge( $this->get_urls_from_html( $custom_logo ), $urls );
		}
		$site_icon_url = get_site_icon_url();
		if ( $this->is_url( $site_icon_url ) ) {
			array_push( $urls, $this->clean_url( $site_icon_url ) );
		}
		$background_image = get_background_image();
		if ( $this->is_url( $background_image ) ) {
			array_push( $urls, $this->clean_url( $background_image ) );
		}
		return  $urls;
	}


	/**
	 * @param string $html
	 *
	 * @return string[]
	 * @throws Exception
	 */
	public function get_urls_from_html( string $html ): array {
		if ( empty( $html ) ) {
			return array();
		}
		$html = $this->prepare_html_to_parse( $html );
		if ( empty( $html ) ) {
			return array();
		}
		$dom     = $this->get_html_as_dom( $html );
		$results = $this->get_images_from_meta( $dom );
		$results = array_merge(
			$results,
			$this->get_images_from_img( $dom ),
			$this->get_images_from_video( $dom ),
			$this->get_images_from_auto( $dom ),
			$this->get_images_from_source( $dom ),
			$this->get_images_from_a( $dom ),
			$this->get_images_from_link( $dom ),
			$this->get_images_from_pdf( $html ),
			$this->get_images_from_background( $html )
		);

		return $results;
	}


	/**
	 * @param string $url
	 *
	 * @return bool
	 */
	private function is_url( string $url ): bool {
		return ( (
		         ! empty( $url ) ) &&
		         is_string( $url ) &&
		         strlen( $url ) > 4 && (
			         strtolower( substr( $url, 0, 4 ) ) == 'http' || $url[0] == '/'
		         )
		);
	}

	/**
	 * @param string $url
	 *
	 * @return string
	 */
	public function clean_url( string $url ): string {
		$dirIndex = strpos( $url, $this->upload_url );
		if ( empty( $url ) || $dirIndex === false ) {
			$finalUrl = '';
		} else {
			$finalUrl = urldecode( substr( $url, 1 + strlen( $this->upload_url ) + $dirIndex ) );
		}
		return $finalUrl;
	}

	/**
	 * @param string $html
	 *
	 * @throws Exception If reserve stock fails.
	 * @return DOMDocument
	 */
	private function get_html_as_dom( string $html ): DOMDocument {
		if ( ! class_exists( "DOMDocument" ) ) {
			error_log( 'The DOM extension is not installed.' );
			throw new Exception( 'The DOM extension is not installed.' );
		}
		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		@$dom->loadHTML( $html );
		libxml_clear_errors();
		return $dom;
	}

	/**
	 * @param $html
	 *
	 * @return string
	 */
	private function prepare_html_to_parse( $html ): string {
		$html = mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' );
		$html = do_shortcode( $html );
		$html = wp_filter_content_tags( $html );

		return $html;
	}

	/**
	 * @param DOMDocument $dom
	 *
	 * @return string[]
	 */
	private function get_images_from_meta( DOMDocument $dom ): array {
		$results = array();
		$metas   = $dom->getElementsByTagName( 'meta' );
		foreach ( $metas as $meta ) {
			$property = $meta->getAttribute( 'property' );
			if ( $property == 'og:image' || $property == 'og:image:secure_url' || $property == 'twitter:image' ) {
				$url = $meta->getAttribute( 'content' );
				if ( $this->is_url( $url ) ) {
					$src = $this->clean_url( $url );
					if ( ! empty( $src ) ) {
						array_push( $results, $src );
					}
				}
			}
		}

		return $results;
	}

	/**
	 * @param DOMDocument $dom
	 *
	 * @return string[]
	 */
	private function get_images_from_img( DOMDocument $dom ): array {
		$results = array();
		$images    = $dom->getElementsByTagName( 'img' );
		foreach ( $images as $img ) {
			$src = $this->clean_url( $img->getAttribute( 'src' ) );
			array_push( $results, $src );
			$srcset = $img->getAttribute( 'srcset' );
			if ( ! empty( $srcset ) ) {
				$set_images = explode( ',', trim( $srcset ) );
				foreach ( $set_images as $setImg ) {
					$finalSetImg = explode( ' ', trim( $setImg ) );
					if ( is_array( $finalSetImg ) ) {
						array_push( $results, $this->clean_url( $finalSetImg[0] ) );
					}
				}
			}
		}

		return $results;
	}

	/**
	 * @param DOMDocument $dom
	 *
	 * @return string[]
	 */
	private function get_images_from_video( DOMDocument $dom ): array {
		$results = array();
		$videos  = $dom->getElementsByTagName( 'video' );
		foreach ( $videos as $video ) {
			$src = $this->clean_url( $video->getAttribute( 'src' ) );
			array_push( $results, $src );
		}

		return $results;
	}

	/**
	 * @param DOMDocument $dom
	 *
	 * @return string[]
	 */
	private function get_images_from_auto( DOMDocument $dom ): array {
		$results = array();
		$audios  = $dom->getElementsByTagName( 'audio' );
		foreach ( $audios as $audio ) {
			$src = $this->clean_url( $audio->getAttribute( 'src' ) );
			array_push( $results, $src );
		}

		return $results;
	}

	/**
	 * @param DOMDocument $dom
	 *
	 * @return string[]
	 */
	private function get_images_from_source( DOMDocument $dom ): array {
		$results = array();
		$audios  = $dom->getElementsByTagName( 'source' );
		foreach ( $audios as $audio ) {
			//error_log($audio->getAttribute('src'));
			$src = $this->clean_url( $audio->getAttribute( 'src' ) );
			array_push( $results, $src );
		}

		return $results ;
	}

	/**
	 * @param DOMDocument $dom
	 *
	 * @return string[]
	 */
	private function get_images_from_a( DOMDocument $dom ): array {
		$results = array();
		$urls    = $dom->getElementsByTagName( 'a' );
		foreach ( $urls as $url ) {
			$url_href = $url->getAttribute( 'href' ); // mm change
			if ( $this->is_url( $url_href ) ) { // mm change
				$src = $this->clean_url( $url_href );  // mm change
				if ( ! empty( $src ) ) {
					array_push( $results, $src );
				}
			}
		}

		return $results;
	}

	/**
	 * @param DOMDocument $dom
	 *
	 * @return string[]
	 */
	private function get_images_from_link( DOMDocument $dom ): array {
		$results = array();
		$urls    = $dom->getElementsByTagName( 'link' );
		foreach ( $urls as $url ) {
			$url_href = $url->getAttribute( 'href' );
			if ( $this->is_url( $url_href ) ) {
				$src = $this->clean_url( $url_href );
				if ( ! empty( $src ) ) {
					array_push( $results, $src );
				}
			}
		}

		return $results;
	}

	/**
	 * @param string $html
	 *
	 * @return string[]
	 */
	private function get_images_from_pdf( string $html ): array {
		$results = array();
		preg_match_all( "/((https?:\/\/)?[^\\&\#\[\] \"\?]+\.pdf)/", $html, $res );
		if ( ! empty( $res ) && isset( $res[1] ) && count( $res[1] ) > 0 ) {
			foreach ( $res[1] as $url ) {
				if ( $this->is_url( $url ) ) {
					array_push( $results, $this->clean_url( $url ) );
				}
			}
		}

		return $results;
	}

	/**
	 * @param string $html
	 *
	 * @return string[]
	 */
	private function get_images_from_background( string $html ): array {
		$results = array();
		preg_match_all( "/url\(\'?\"?((https?:\/\/)?[^\\&\#\[\] \"\?]+\.(jpe?g|gif|png))\'?\"?/", $html, $res );
		if ( ! empty( $res ) && isset( $res[1] ) && count( $res[1] ) > 0 ) {
			foreach ( $res[1] as $url ) {
				if ( $this->is_url( $url ) ) {
					array_push( $results, $this->clean_url( $url ) );
				}
			}
		}
		return $results;
	}

}

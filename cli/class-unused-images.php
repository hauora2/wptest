<?php
/** @noinspection PhpIncludeInspection */
require_once( get_template_directory() . '/cli/class-post-scanner.php' );

/**
 * Check unused media
 *
 */
class Unused_Images {
	public const
		IGNORE_MEDIA = true;
	public const
		USING_MEDIA = false;
	/**
	 * @var string[]
	 */
	private array $media_files = array();
	/**
	 * @var string[]
	 */
	private array $used_files = array();
	/**
	 * @var Post_Scanner
	 */
	private Post_Scanner $scanner;

	public function __construct() {
	}

	/**
	 *  Check unused files in Uploads and Gallery
	 */
	public function strong(): void {
		$this->scanner = new Post_Scanner( self::IGNORE_MEDIA );
		WP_CLI::line( 'Scan posts' );
		$this->scanner->scan_posts();
		WP_CLI::line( 'Extract media from posts' );
		$this->used_files = $this->scanner->get_used_images();
		WP_CLI::line( 'Extract files from uploads directory' );
		$this->media_files = $this->scanner->get_all_media_files_from_dir();
		WP_CLI::line( 'Extract files from uploads directory' );
		WP_CLI::log( 'Result (unused files from gallery and uploads):' );
		$this->print_results();
		WP_CLI::line( 'Done.' );
	}

	/**
	 *  Check unused files only in uploads
	 */
	public function only_uploads(): void {
		$this->scanner = new Post_Scanner( self::USING_MEDIA );
		WP_CLI::line( 'Scan posts' );
		$this->scanner->scan_posts();
		WP_CLI::line( 'Extract media from posts' );
		$this->used_files = $this->scanner->get_used_images();
		WP_CLI::line( 'Extract files from uploads directory' );
		$this->media_files = $this->scanner->get_all_media_files_from_dir();
		WP_CLI::line( 'Extract files from uploads directory' );
		WP_CLI::log( 'Result (unused files from uploads only ):' );
		$this->print_results();
		WP_CLI::line( 'Done.' );
	}

	/**
	 * output result
	 */
	private function print_results(): void {
		$result = array_diff( $this->media_files, $this->used_files );
		WP_CLI::line( WP_CLI::colorize( '%G---------------------%n' ) );
		foreach ( $result as $v ) {
			WP_CLI::log( $v );
		}
		WP_CLI::line( WP_CLI::colorize( '%G---------------------%n' ) );
	}

	static public function register_command(): void {
		/*  @noinspection  PhpUnhandledExceptionInspection */
		WP_CLI::add_command( 'checkmedia', 'Unused_Images' );
	}
}

add_action( 'cli_init', 'Unused_Images::register_command' );

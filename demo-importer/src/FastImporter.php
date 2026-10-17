<?php
/**
 * Fast Demo Importer – Parallel Image Pre-Downloader
 *
 * Problem: The default demo import downloads 497 attachment files ONE BY ONE
 * (sequential HTTP requests), each taking 2-4 seconds = ~20 minutes total.
 *
 * Solution implemented here:
 *  1. BEFORE XML import  – Parse content.xml, download all images in PARALLEL
 *                           batches (12 at a time) using cURL multi. Stores
 *                           files to a temp dir. Typical time: ~60 seconds.
 *  2. DURING XML import  – Intercept every wp_safe_remote_get() attachment
 *                           download via pre_http_request filter and serve the
 *                           already-cached local file instead (~0ms per file).
 *                           Also suppresses per-image thumbnail generation.
 *  3. AFTER XML import   – Removes all hooks, cleans temp dir, finds every
 *                           attachment that has no thumbnail metadata, saves
 *                           the list to a transient, and schedules a WP-Cron
 *                           job to regenerate thumbnails in background batches.
 *  4. WP-CRON batches    – 20 attachments at a time, rescheduling itself until
 *                           all thumbnails are regenerated.
 *
 * Net result:
 *   Import time   : ~20 min  →  ~2-3 min   (images download in parallel)
 *   Thumbnails    : generated silently in background after import completes
 *   Site usability: images display immediately at original size; properly-sized
 *                   crops appear once cron finishes (~10-20 min later)
 *
 * @package RT\Listygo_Core\DemoImporter
 */

namespace RT\Listygo_Core\DemoImporter;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * FastImporter Class
 */
class FastImporter {

	/**
	 * Temp directory used to store pre-downloaded files.
	 *
	 * @var string
	 */
	private string $cache_dir = '';

	/**
	 * Map of remote URL → local cached file path.
	 *
	 * @var array<string, string>
	 */
	private array $cache_map = [];

	/**
	 * Whether the pre_http_request cache hook is active.
	 *
	 * @var bool
	 */
	private bool $active = false;

	/**
	 * Number of concurrent downloads per batch.
	 * 12 is a safe default: fast enough without triggering server-side rate limits.
	 */
	private const BATCH_SIZE = 12;

	/**
	 * WP-Cron hook name for background thumbnail regeneration.
	 */
	private const CRON_HOOK = 'listygo_demo_regen_thumbnails';

	/**
	 * Transient key storing the list of attachment IDs awaiting thumbnail regen.
	 */
	private const REGEN_TRANSIENT = 'listygo_pending_thumb_regen';

	/**
	 * Register all WordPress hooks.
	 */
	public function __construct() {
		// Phase 1 & 2: pre-download + cache serving.
		add_action( 'sd/edi/before_import', [ $this, 'pre_download' ], 1, 2 );

		// Phase 3: cleanup + schedule background regen.
		add_action( 'sd/edi/after_content_import', [ $this, 'after_xml_import' ], 1, 2 );

		// Phase 4: cron thumbnail regeneration handler.
		add_action( self::CRON_HOOK, [ $this, 'process_thumbnail_batch' ] );
	}

	// =========================================================================
	// PHASE 1 — Pre-download all images in parallel before WP's import loop
	// =========================================================================

	/**
	 * Hooked to sd/edi/before_import (priority 1).
	 *
	 * Parses content.xml for all attachment URLs, downloads them in parallel
	 * batches, then registers the pre_http_request filter to serve from cache.
	 *
	 * @param string $xml_file Absolute path to content.xml.
	 * @param object $importer InstallDemo instance (unused, but hook passes it).
	 */
	public function pre_download( string $xml_file, $importer = null ): void {
		// Bail if images are excluded by the user's "Skip Demo Images" toggle.
		// In that case $fetch_attachments will be false and no HTTP calls will be
		// made during import, so pre-downloading is pointless.
		$exclude_images = ! empty( $_POST['excludeImages'] )  // phpcs:ignore WordPress.Security.NonceVerification.Missing
			? sanitize_text_field( wp_unslash( $_POST['excludeImages'] ) )  // phpcs:ignore WordPress.Security.NonceVerification.Missing
			: '';

		if ( 'true' === $exclude_images ) {
			return;
		}

		if ( ! file_exists( $xml_file ) ) {
			return;
		}

		// Give this PHP process unlimited execution time – we will be here a while.
		if ( false === strpos( (string) ini_get( 'disable_functions' ), 'set_time_limit' ) ) {
			set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		// Always suppress thumbnail generation during import regardless of whether
		// cURL multi is available. This alone saves ~30-40 % of import time.
		add_filter( 'intermediate_image_sizes_advanced', '__return_empty_array', 999 );
		add_filter( 'wp_generate_attachment_metadata', '__return_empty_array', 999 );

		// cURL multi is required for parallel downloads.
		if ( ! function_exists( 'curl_multi_init' ) ) {
			return; // Thumbnail-skip optimisation still applies via filters above.
		}

		$urls = $this->parse_attachment_urls( $xml_file );

		if ( empty( $urls ) ) {
			return;
		}

		// Create a uniquely-named temp directory for this import session.
		$this->cache_dir = trailingslashit( sys_get_temp_dir() )
			. 'listygo_demo_' . substr( md5( uniqid( '', true ) ), 0, 12 ) . '/';

		if ( ! wp_mkdir_p( $this->cache_dir ) ) {
			return; // Can't create temp dir — skip parallel optimisation.
		}

		// Download everything, then activate the cache filter.
		$this->bulk_download( $urls );

		if ( empty( $this->cache_map ) ) {
			$this->cleanup_cache_dir();
			return;
		}

		$this->active = true;
		add_filter( 'pre_http_request', [ $this, 'serve_from_cache' ], 5, 3 );
	}

	/**
	 * Extract every attachment URL from content.xml using regex.
	 *
	 * Two sources:
	 *  • <wp:attachment_url> — the canonical source for attachment files.
	 *  • <guid> on attachment posts — fallback for any not covered above.
	 *
	 * @param string $xml_file Absolute path to content.xml.
	 * @return array<string>  Deduplicated list of URLs.
	 */
	private function parse_attachment_urls( string $xml_file ): array {
		// file_get_contents is appropriate here; content.xml is typically < 10 MB.
		$content = file_get_contents( $xml_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( ! $content ) {
			return [];
		}

		$urls = [];

		// Primary: <wp:attachment_url><![CDATA[…]]></wp:attachment_url>
		if ( preg_match_all(
			'/<wp:attachment_url><!\[CDATA\[([^\]]+)\]\]><\/wp:attachment_url>/',
			$content,
			$matches
		) ) {
			$urls = array_merge( $urls, $matches[1] );
		}

		// Fallback: <guid isPermaLink="false"><![CDATA[…]]></guid>
		// Only include URLs that look like media files.
		if ( preg_match_all(
			'/<guid[^>]*><!\[CDATA\[([^\]]+)\]\]><\/guid>/',
			$content,
			$guid_matches
		) ) {
			$media_exts = [ 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'mp4', 'mp3', 'wav', 'ogg' ];
			foreach ( $guid_matches[1] as $guid ) {
				$ext = strtolower( pathinfo( (string) parse_url( $guid, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
				if ( in_array( $ext, $media_exts, true ) ) {
					$urls[] = $guid;
				}
			}
		}

		return array_values( array_unique( array_filter( $urls ) ) );
	}

	/**
	 * Split URL list into batches and download each batch concurrently.
	 *
	 * @param array<string> $urls
	 */
	private function bulk_download( array $urls ): void {
		foreach ( array_chunk( $urls, self::BATCH_SIZE ) as $batch ) {
			$this->download_batch( $batch );
		}
	}

	/**
	 * Download a single batch of URLs using cURL multi (fully parallel).
	 *
	 * Successfully downloaded files are added to $this->cache_map.
	 * Failed files are silently skipped (WP will download them normally).
	 *
	 * @param array<string> $urls
	 */
	private function download_batch( array $urls ): void {
		$mh      = curl_multi_init();
		$handles = [];

		foreach ( $urls as $url ) {
			// Already cached from a previous batch – shouldn't happen with
			// array_unique, but guard anyway.
			if ( isset( $this->cache_map[ $url ] ) ) {
				continue;
			}

			$safe_name  = md5( $url ) . '_' . sanitize_file_name( (string) basename( (string) parse_url( $url, PHP_URL_PATH ) ) );
			$local_file = $this->cache_dir . $safe_name;

			$fp = @fopen( $local_file, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.PHP.NoSilencedErrors.Discouraged
			if ( ! $fp ) {
				continue;
			}

			$ch = curl_init();
			curl_setopt_array(
				$ch,
				[
					CURLOPT_URL            => $url,
					CURLOPT_FILE           => $fp,           // Stream response to disk.
					CURLOPT_TIMEOUT        => 60,
					CURLOPT_CONNECTTIMEOUT => 10,
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_MAXREDIRS      => 5,
					CURLOPT_SSL_VERIFYPEER => (bool) apply_filters( 'https_ssl_verify', true ),
					CURLOPT_USERAGENT      => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
					CURLOPT_ENCODING       => 'identity',    // No compression – match WP header.
					CURLOPT_HEADER         => false,
				]
			);

			curl_multi_add_handle( $mh, $ch );
			$handles[ $url ] = [
				'ch'   => $ch,
				'fp'   => $fp,
				'file' => $local_file,
			];
		}

		if ( empty( $handles ) ) {
			curl_multi_close( $mh );
			return;
		}

		// Run the multi-handle event loop.
		$running = null;
		do {
			$status = curl_multi_exec( $mh, $running );
			if ( $running && CURLM_OK === $status ) {
				// Wait up to 0.5 s for activity to avoid busy-looping.
				curl_multi_select( $mh, 0.5 );
			}
		} while ( $running > 0 && CURLM_OK === $status );

		// Collect results and update the cache map.
		foreach ( $handles as $url => $data ) {
			$http_code = curl_getinfo( $data['ch'], CURLINFO_HTTP_CODE );
			fclose( $data['fp'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

			curl_multi_remove_handle( $mh, $data['ch'] );
			curl_close( $data['ch'] );

			$file_size = file_exists( $data['file'] ) ? filesize( $data['file'] ) : 0;

			if ( 200 === $http_code && $file_size > 0 ) {
				$this->cache_map[ $url ] = $data['file'];
			} else {
				@unlink( $data['file'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}

		curl_multi_close( $mh );
	}

	// =========================================================================
	// PHASE 2 — Serve pre-downloaded files during WP's sequential import loop
	// =========================================================================

	/**
	 * pre_http_request filter (priority 5).
	 *
	 * When the WP importer calls wp_safe_remote_get( $url, [ 'stream' => true,
	 * 'filename' => $tmp ] ), this filter:
	 *  1. Checks whether the URL is in our cache map.
	 *  2. Copies the pre-downloaded file to $args['filename'] (= $tmp).
	 *  3. Returns a fake 200 OK response so fetch_remote_file() proceeds.
	 *
	 * Result: each "download" completes in microseconds instead of 2-4 seconds.
	 *
	 * @param false|array|\WP_Error $preempt Non-false value short-circuits the request.
	 * @param array                 $args    Parsed request arguments.
	 * @param string                $url     The requested URL.
	 * @return false|array|\WP_Error
	 */
	public function serve_from_cache( $preempt, array $args, string $url ) {
		if ( ! $this->active || ! isset( $this->cache_map[ $url ] ) ) {
			return $preempt;
		}

		$cached = $this->cache_map[ $url ];

		if ( ! file_exists( $cached ) ) {
			unset( $this->cache_map[ $url ] );
			return $preempt; // Cache miss — let WP download normally.
		}

		// WP uses stream mode: it expects the response body written to $args['filename'].
		if ( ! empty( $args['filename'] ) ) {
			if ( ! copy( $cached, $args['filename'] ) ) {
				return $preempt; // Copy failed — fall back to normal download.
			}
		}

		// Return a minimal valid WP HTTP response array.
		// NOTE: 'content-length' is intentionally omitted to bypass the size-mismatch
		// check in fetch_remote_file() — filesize( $tmp_file_name ) is checked instead.
		return [
			'headers'  => [
				'content-type' => $this->get_mime_type( $cached ),
			],
			'body'     => '', // Empty when streaming to file.
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'cookies'  => [],
			'filename' => $args['filename'] ?? '',
		];
	}

	/**
	 * Determine the MIME type of a local file.
	 *
	 * Uses the fileinfo extension when available, falls back to extension map.
	 *
	 * @param string $file Absolute path to the file.
	 * @return string MIME type string.
	 */
	private function get_mime_type( string $file ): string {
		if ( function_exists( 'mime_content_type' ) ) {
			$mime = mime_content_type( $file );
			if ( $mime ) {
				return $mime;
			}
		}

		$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
		$map = [
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'gif'  => 'image/gif',
			'webp' => 'image/webp',
			'svg'  => 'image/svg+xml',
			'pdf'  => 'application/pdf',
			'mp4'  => 'video/mp4',
			'mp3'  => 'audio/mpeg',
		];

		return $map[ $ext ] ?? 'application/octet-stream';
	}

	// =========================================================================
	// PHASE 3 — After XML import: cleanup + schedule background thumbnail regen
	// =========================================================================

	/**
	 * Hooked to sd/edi/after_content_import (priority 1).
	 *
	 * Called right after the WP XML import loop completes. Removes all added
	 * filters, cleans up temp files, then queues thumbnail regeneration.
	 *
	 * @param string $xml_file Absolute path to content.xml (unused here).
	 * @param object $importer InstallDemo instance (unused here).
	 */
	public function after_xml_import( string $xml_file, $importer = null ): void {
		// Deactivate cache serving.
		$this->active = false;
		remove_filter( 'pre_http_request', [ $this, 'serve_from_cache' ], 5 );

		// Restore normal thumbnail generation for all future requests.
		remove_filter( 'intermediate_image_sizes_advanced', '__return_empty_array', 999 );
		remove_filter( 'wp_generate_attachment_metadata', '__return_empty_array', 999 );

		// Find attachments that have no thumbnail metadata and schedule background regen.
		$this->schedule_thumbnail_regeneration();

		// Delete all temp pre-downloaded files.
		$this->cleanup_cache_dir();
	}

	/**
	 * Find all attachments imported without thumbnails and schedule WP-Cron regen.
	 *
	 * Attachments imported while intermediate_image_sizes_advanced returns [] will
	 * have their _wp_attachment_metadata stored as a:0:{} (empty serialised array).
	 * We find those and queue them for background processing.
	 */
	private function schedule_thumbnail_regeneration(): void {
		global $wpdb;

		// Direct query is more reliable than WP_Query for serialised meta comparisons.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$attachment_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID
				 FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} pm
				   ON p.ID = pm.post_id
				   AND pm.meta_key = %s
				 WHERE p.post_type   = 'attachment'
				   AND p.post_status = 'inherit'
				   AND (pm.meta_value IS NULL OR pm.meta_value = %s)",
				'_wp_attachment_metadata',
				serialize( [] ) // 'a:0:{}'
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( empty( $attachment_ids ) ) {
			return;
		}

		$attachment_ids = array_map( 'intval', $attachment_ids );

		// Store the full list in a transient (24 h TTL; cron will clear it sooner).
		set_transient( self::REGEN_TRANSIENT, $attachment_ids, DAY_IN_SECONDS );

		// Schedule the first cron batch to run 60 s after import finishes —
		// giving the rest of the import steps (customizer, widgets, menus…) time
		// to complete first.
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 60, self::CRON_HOOK );
		}
	}

	// =========================================================================
	// PHASE 4 — WP-Cron: regenerate thumbnails in background batches
	// =========================================================================

	/**
	 * WP-Cron callback: regenerate thumbnails for a batch of 20 attachments.
	 *
	 * Updates the transient before processing (not after), so remaining items
	 * survive even if the cron request times out mid-batch. Reschedules itself
	 * with a short delay until the full list is processed.
	 */
	public function process_thumbnail_batch(): void {
		$all_pending = get_transient( self::REGEN_TRANSIENT );

		if ( empty( $all_pending ) || ! is_array( $all_pending ) ) {
			delete_transient( self::REGEN_TRANSIENT );
			return;
		}

		// Split: first 20 to process now, the rest for subsequent cron runs.
		$current_batch = array_slice( $all_pending, 0, 20 );
		$remaining     = array_slice( $all_pending, 20 );

		// Persist the remaining list BEFORE processing — crash-safe.
		if ( ! empty( $remaining ) ) {
			set_transient( self::REGEN_TRANSIENT, $remaining, DAY_IN_SECONDS );
			if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_single_event( time() + 5, self::CRON_HOOK );
			}
		} else {
			delete_transient( self::REGEN_TRANSIENT );
		}

		foreach ( $current_batch as $attachment_id ) {
			$attachment_id = (int) $attachment_id;
			$file          = get_attached_file( $attachment_id );

			if ( ! $file || ! file_exists( $file ) ) {
				continue; // File missing — skip gracefully.
			}

			$metadata = wp_generate_attachment_metadata( $attachment_id, $file );

			if ( ! is_wp_error( $metadata ) && ! empty( $metadata ) ) {
				wp_update_attachment_metadata( $attachment_id, $metadata );
			}
		}
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Delete all pre-downloaded files and remove the temp cache directory.
	 */
	private function cleanup_cache_dir(): void {
		if ( empty( $this->cache_dir ) || ! is_dir( $this->cache_dir ) ) {
			return;
		}

		$files = glob( $this->cache_dir . '*' );
		if ( $files ) {
			array_map( 'unlink', $files );
		}

		@rmdir( $this->cache_dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->cache_dir = '';
		$this->cache_map = [];
	}
}

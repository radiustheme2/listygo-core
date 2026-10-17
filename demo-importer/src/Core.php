<?php
/**
 * Demo Importer Core Class
 *
 * This file contains the core functionality of the demo importer.
 *
 * @package RT\Listygo_Core\DemoImporter
 */

namespace RT\Listygo_Core\DemoImporter;

use RT\Listygo_Core\DemoImporter\Utils;
use RT\Listygo_Core\DemoImporter\Handlers\RtclHandler;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * Demo Importer Core Class.
 */
class Core {
	/**
	 * Theme configuration.
	 *
	 * @var array
	 */
	private $config = [];

	/**
	 * Configuration cache.
	 *
	 * @var array
	 */
	private $config_cache = null;

	/**
	 * Utilities instance.
	 *
	 * @var Utils
	 */
	private $utils;

	/**
	 * RTCL handler instance.
	 *
	 * @var RtclHandler|null
	 */
	private $rtcl = null;

	/**
	 * Class Constructor.
	 *
	 * @param array $config Theme configuration array.
	 */
	public function __construct( array $config = [] ) {
		$this->config = $config;
		$this->utils  = new Utils( $config );

		if ( $this->is_feature_enabled( 'rtcl_support' ) ) {
			$this->rtcl = new RtclHandler( $config, $this->utils );
		}

		$this->load_user_importer();
		$this->setup_hooks();
	}

	/**
	 * Check if a feature is enabled.
	 *
	 * @param string $feature Feature name.
	 * @return bool
	 */
	private function is_feature_enabled( string $feature ): bool {
		return isset( $this->config['features'][ $feature ] ) && $this->config['features'][ $feature ];
	}

	/**
	 * Load the user importer file if it exists.
	 *
	 * @return void
	 */
	private function load_user_importer(): void {
		if ( ! $this->is_feature_enabled( 'user_import' ) ) {
			return;
		}

		$user_importer_path = $this->config['core_base_dir'] . $this->config['user_importer'];

		if ( file_exists( $user_importer_path ) ) {
			require_once $user_importer_path;
		}
	}

	/**
	 * Setup WordPress hooks.
	 *
	 * @return void
	 */
	private function setup_hooks(): void {
		/**
		 * Filters.
		 */
		add_filter( 'sd/edi/importer/config', [ $this, 'import_config' ] );
		add_filter( 'sd/edi/activated_plugin_actions', [ $this, 'plugin_actions' ] );

		/**
		 * Actions.
		 */
		add_action( 'sd/edi/after_plugins_activation', [ $this, 'before_import_actions' ] );
		add_action( 'sd/edi/before_widgets_import', [ $this, 'remove_default_widgets' ] );
		add_action( 'sd/edi/after_import', [ $this, 'after_import_actions' ] );
	}

	/**
	 * Generate import configuration from theme config.
	 *
	 * @return array
	 */
	public function import_config(): array {
		if ( null !== $this->config_cache ) {
			return $this->config_cache;
		}

		$this->config_cache = [
			'themeName'             => $this->config['theme_name'],
			'themeSlug'             => $this->config['theme_slug'],
			'multipleZip'           => false,
			'demoZip'               => $this->utils->get_demo_asset_url( $this->config['demo_content_zip'] ),
			'blogSlug'              => $this->config['blog_slug'],
			'urlToReplace'          => $this->config['demo_url'],
			'replaceCommenterEmail' => $this->config['commenter_email'] ?? '',
			'elementor_data_fix'    => $this->is_feature_enabled( 'elementor_fixes' ) ? $this->config['elementor_fixes'] : [],
			'settingsJson'          => $this->config['settings_json'],
			'menus'                 => $this->config['menus'],
			'demoData'              => $this->build_demo_data(),
			'plugins'               => $this->build_plugins_config(),
		];

		if ( ! empty( $this->config['fluent_forms_json'] ) ) {
			$this->config_cache['fluentFormsJson'] = $this->config['fluent_forms_json'];
		}

		return $this->config_cache;
	}

	/**
	 * Run plugin activation actions after activation.
	 *
	 * @param array $actions Existing plugin activation actions.
	 *
	 * @return array
	 */
	public function plugin_actions( array $actions ): array {
		$actions['classified-listing/classified-listing.php'] = [
			'class'  => '\Rtcl\Helpers\Installer',
			'action' => 'install',
		];

		$actions['classified-listing-pro/classified-listing-pro.php'] = [
			'class'  => '\RtclPro\Helpers\Installer',
			'action' => 'install',
		];

		$actions['classified-listing-store/classified-listing-store.php'] = [
			'class'  => '\RtclStore\Helpers\Install',
			'action' => 'install',
		];

		return $actions;
	}

	/**
	 * Build demo data configuration.
	 *
	 * @return array
	 */
	private function build_demo_data(): array {
		return array_map(
			function ( $variant ) {
				return [
					'name'         => $variant['name'],
					'previewImage' => $this->utils->get_demo_asset_url( $variant['preview'] ),
					'previewUrl'   => $this->config['demo_url'] . $variant['url'],
				];
			},
			$this->config['demo_variants']
		);
	}

	/**
	 * Build plugins configuration.
	 *
	 * @return array
	 */
	private function build_plugins_config(): array {
		return array_merge(
			$this->build_wp_plugins(),
			$this->build_bundled_plugins()
		);
	}

	/**
	 * Build WordPress repository plugins configuration.
	 *
	 * @return array
	 */
	private function build_wp_plugins(): array {
		$plugins = [];

		foreach ( $this->config['wp_plugins'] as $slug => $plugin ) {
			$name     = is_array( $plugin ) ? $plugin['name'] : $plugin;
			$filePath = is_array( $plugin ) && ! empty( $plugin['file'] ) ? $plugin['file'] : $slug . '/' . $slug . '.php';

			$plugins[ $slug ] = [
				'name'     => $name,
				'source'   => 'wordpress',
				'filePath' => $filePath,
			];
		}

		return $plugins;
	}

	/**
	 * Build bundled plugins configuration.
	 *
	 * @return array
	 */
	private function build_bundled_plugins(): array {
		$plugins = [];

		foreach ( $this->config['bundled_plugins'] as $slug => $plugin ) {
			$plugins[ $slug ] = [
				'name'     => $plugin['name'],
				'source'   => 'bundled',
				'filePath' => $slug . '/' . $slug . '.php',
				'location' => get_parent_theme_file_uri( $plugin['file'] ),
			];
		}

		return $plugins;
	}

	/**
	 * Execute before import actions.
	 *
	 * @return void
	 */
	public function before_import_actions(): void {
		if ( $this->rtcl ) {
			$this->rtcl->prepare_environment();
			flush_rewrite_rules();
		}

		$this->set_import_options();
	}
	/**
	 * Remove default widgets.
	 *
	 * @return void
	 */
	public function remove_default_widgets(): void {
		delete_option( 'sidebars_widgets' );
	}

	/**
	 * Execute after import actions.
	 *
	 * @return void
	 */
	public function after_import_actions(): void {
		if ( $this->is_feature_enabled( 'user_import' ) ) {
			$this->import_users();
		}

		if ( $this->rtcl ) {
			$this->rtcl->rebuild_environment();
		}

		$this->cleanup_after_import();

		// Booking Demo Setup.
		$this->radius_booking();
	}

	/**
	 * @return $this
	 */
	public function radius_booking() {
		if ( ! class_exists( '\RadiusTheme\RadiusBooking\Facades\Import' ) ) {
			return $this;
		}
		$json_file_path = LISTYGO_CORE_BASE_DIR . 'demo-importer/demo-files/radius-booking.json';
		if ( file_exists( $json_file_path ) ) {
			\RadiusTheme\RadiusBooking\Facades\Import::fromFile( $json_file_path )->import();
		}
		$this->sync_booking_meta();

		return $this;
	}

	/**
	 * After booking import:
	 * 1. Read _rtrb_booking_enabled values from content.xml and update post meta.
	 * 2. Link each service to its listing in radius_booking_services table
	 *    (ServiceImporter sets listing_id = null; without this the booking
	 *     button never renders on the frontend).
	 *
	 * @return void
	 */
	private function sync_booking_meta(): void {
		global $wpdb;

		$xml_path = LISTYGO_CORE_BASE_DIR . 'demo-importer/demo-files/content.xml';
		if ( ! file_exists( $xml_path ) ) {
			return;
		}

		// Build title (lowercase) → 'yes'|'no' map from the XML.
		$booking_map = [];
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$xml = file_get_contents( $xml_path );

		foreach ( preg_split( '/<item>/', $xml ) as $block ) {
			if ( false === strpos( $block, 'rtcl_listing' ) ) {
				continue;
			}
			if ( ! preg_match( '/<title><!\[CDATA\[([^\]]+)\]\]><\/title>/', $block, $title_m ) ) {
				continue;
			}

			$val = 'no';
			if ( preg_match(
				     '/<wp:meta_key><!\[CDATA\[_rtrb_booking_enabled\]\]><\/wp:meta_key>\s*<wp:meta_value><!\[CDATA\[([^\]]*)\]\]><\/wp:meta_value>/',
				     $block,
				     $val_m
			     ) && 'yes' === $val_m[1] ) {
				$val = 'yes';
			}

			$booking_map[ strtolower( trim( $title_m[1] ) ) ] = $val;
		}

		if ( empty( $booking_map ) ) {
			return;
		}

		// Build service name → service id map from booking DB.
		$services_table = $wpdb->prefix . 'radius_booking_services';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows        = $wpdb->get_results( "SELECT id, name FROM {$services_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$service_map = [];
		foreach ( (array) $rows as $row ) {
			$service_map[ strtolower( trim( $row->name ) ) ] = (int) $row->id;
		}

		// Update meta and link services for each imported listing.
		$listings = get_posts( [
			'post_type'      => 'rtcl_listing',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'all',
		] );

		foreach ( $listings as $listing ) {
			$key = strtolower( trim( $listing->post_title ) );

			// 1. Update _rtrb_booking_enabled meta.
			update_post_meta( $listing->ID, '_rtrb_booking_enabled', $booking_map[ $key ] ?? 'no' );

			// 2. Link service → listing so the frontend booking button resolves.
			if ( isset( $service_map[ $key ] ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$services_table,
					[ 'listing_id' => $listing->ID ],
					[ 'id'         => $service_map[ $key ] ],
					[ '%d' ],
					[ '%d' ]
				);
			}
		}
	}



	/**
	 * Set import-specific options.
	 *
	 * @return void
	 */
	private function set_import_options(): void {
		foreach ( $this->config['pre_import_options'] as $option => $value ) {
			update_option( $option, $value );
		}
	}

	/**
	 * Import users and update post authors.
	 *
	 * @return void
	 */
	private function import_users(): void {
		global $wpdb;

		$current_user_id = get_current_user_id();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->posts} SET post_author = %d",
				$current_user_id
			)
		);

		if ( class_exists( $this->config['user_class'] ) ) {
			$class_name = $this->config['user_class'];
			new $class_name();
		}
	}

	/**
	 * Cleanup after import completion.
	 *
	 * @return void
	 */
	private function cleanup_after_import(): void {
		foreach ( $this->config['post_import_options'] as $option => $value ) {
			update_option( $option, $value );
		}

		flush_rewrite_rules();
	}
}

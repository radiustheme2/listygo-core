<?php
/**
 * Theme Demo Configuration File
 *
 * This file contains the configuration for the demo importer.
 *
 * @package @package Radiustheme\CL_Classified_Core
 */

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

return [
	// Basic theme information.
	'blog_slug'            => 'blog',
	'demo_url'             => 'https://radiustheme.com/demo/wordpress/themes/listygo/',
	'menus'                => [
		'primary' => 'Main Menu',
		'crmenu' => 'Copyright Footer Menu'
	],

	// File paths.
	'demo_content_zip'     => 'demo-files/demo-content.zip',

	// Demo variants.
	'demo_variants'   => [
		'home-one'    => [
			'name'    => 'Home One',
			'preview' => 'screenshots/home-one.webp',
			'url'     => '',
		],
		'home-two'    => [
			'name'    => 'Home Two',
			'preview' => 'screenshots/home-two.webp',
			'url'     => 'home-two/',
		],
		'home-three'    => [
			'name'    => 'Home Three',
			'preview' => 'screenshots/home-three.webp',
			'url'     => 'home-three/',
		],
		'home-four'    => [
			'name'    => 'Home Four',
			'preview' => 'screenshots/home-four.webp',
			'url'     => 'home-four/',
		],
		'home-five'    => [
			'name'    => 'Home Five',
			'preview' => 'screenshots/home-five.webp',
			'url'     => 'home-five/',
		],
		'home-six'    => [
			'name'    => 'Home Six',
			'preview' => 'screenshots/home-six.webp',
			'url'     => 'home-six/',
		],
		'home-seven'    => [
			'name'    => 'Home Seven',
			'preview' => 'screenshots/home-seven.webp',
			'url'     => 'home-seven/',
		],
	],

	// Additional settings.
	'settings_json'        => [
		'rtcl_filter_settings',
	],
	'fluent_forms_json' => 'fluentforms',

	// WordPress repository plugins.
	'wp_plugins'           => [
		'classified-listing'          => 'Classified Listing – Classified ads & Business Directory Plugin',
		'classified-listing-toolkits' => 'Classified Listing Toolkits',
		'elementor'                   => 'Elementor Page Builder',
		'fluentform'                  => 'Fluent Forms',
		'review-schema'               => 'Review Schema',
	],

	// Bundled/Premium plugins.
	'bundled_plugins'      => [
		'rt-framework' => [
			'name' => 'RT Framework',
			'file' => 'plugin-bundle/rt-framework.zip',
		],
		'classified-listing-pro' => [
			'name' => 'Classified Listing Pro',
			'file' => 'plugin-bundle/classified-listing-pro.4.0.8.zip',
		],
		'classified-listing-store' => [
			'name' => 'Classified Listing Store',
			'file' => 'plugin-bundle/classified-listing-store.3.0.6.zip',
		],
		'review-schema-pro' => [
			'name' => 'Review Schema Pro',
			'file' => 'plugin-bundle/review-schema-pro.2.0.0.zip',
		],
	],

	// Enable/disable import features.
	'features'             => [
		'rtcl_support'    => true,
		'elementor_fixes' => true,
		'user_import'     => true,
	],

	// Elementor data fixes.
	'elementor_fixes' => [
		'rt-listing-categories'    => [ 'highlight_categories' ],
		'rt-listing-category-box'  => [ 'listing_category' ],
		'rt-locations'             => [ 'repeater_locations' => 'location_name' ],
		'rt-listing-location-box'  => [ 'listing_location' ],
		'rt-listings'              => [ 'terms', 'locations' ],
	],

	// User importer.
	'user_importer'        => 'demo-users/user-importer.php',
	'user_class'           => 'Listygo_Core_Demo_User_Import',

	// RTCL Pages to remove before import.
	'rtcl_pages_to_remove' => [
		'Listings',
		'Listing Form',
		'My Account',
		'Checkout',
	],

	// RTCL pages to create/update.
	'rtcl_pages'           => [],

	// Pre-import options.
	'pre_import_options'   => [
		'elementor_experiment-e_font_icon_svg' => 'inactive',
	],

	// Post-import options.
	'post_import_options'  => [
		'elementor_allow_unfiltered_files' => true,
		'posts_per_page'                   => 6,
	],

	'rtcl_custom_files' => [
		'rtcl_options' => 'demo-files/rtcl-options.json',
		'rtcl_forms'   => [
			'demo-files/rtcl-listygo-form.json'
		],
	],

	'max_execution_time' => '2400',
	'memory_limit' => '512M',

];

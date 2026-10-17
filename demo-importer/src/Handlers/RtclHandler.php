<?php
/**
 * Demo Importer RTCL Handler
 *
 * This file contains RTCL-specific functionality.
 *
 * @package RT\Listygo_Core\DemoImporter
 */

namespace RT\Listygo_Core\DemoImporter\Handlers;

use Rtcl\Models\Form\Form;
use RtclStore\Controllers\Hooks\Init;
use RT\Listygo_Core\DemoImporter\Utils;
use SigmaDevs\EasyDemoImporter\Common\Functions\Helpers;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * Demo Importer RTCL Handler Class.
 */
class RtclHandler {
	/**
	 * Theme configuration.
	 *
	 * @var array
	 */
	private $config = [];

	/**
	 * Utilities instance.
	 *
	 * @var Utils
	 */
	private $utils;

	/**
	 * Class Constructor.
	 *
	 * @param array $config Theme configuration.
	 * @param Utils $utils  Utils instance.
	 */
	public function __construct( array $config, Utils $utils ) {
		$this->config = $config;
		$this->utils  = $utils;
	}

	/**
	 * Prepare RTCL environment for import.
	 *
	 * @return void
	 */
	public function prepare_environment(): void {
		$this->update_rtcl_options();
		$this->remove_default_pages();
	}

	/**
	 * Rebuild RTCL environment after import.
	 *
	 * @return void
	 */
	public function rebuild_environment(): void {
		$this->update_rtcl_options();
		$this->clear_forms();
		$this->import_forms();
		$this->remap_form_conditional_term_ids();
		$this->update_pages();
		$this->set_listing_types();
	}

	/**
	 * Remove default pages before import.
	 *
	 * @return void
	 */
	private function remove_default_pages(): void {
		if ( empty( $this->config['rtcl_pages_to_remove'] ) ) {
			return;
		}

		foreach ( $this->config['rtcl_pages_to_remove'] as $page_title ) {
			$page = Helpers::getPageByTitle( $page_title );

			if ( $page ) {
				wp_delete_post( $page->ID, true );
			}
		}
	}

	/**
	 * Clear RTCL forms table.
	 *
	 * @return void
	 */
	private function clear_forms(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'rtcl_forms';

		// Whitelist allowed tables.
		$allowed_tables = [
			$wpdb->prefix . 'rtcl_forms',
		];

		if ( in_array( $table, $allowed_tables, true ) ) {
			$wpdb->query( "TRUNCATE TABLE `{$table}`" );
		}
	}

	/**
	 * Update RTCL membership settings.
	 *
	 * @return void
	 */
	private function update_rtcl_options(): void {
		$file_path = $this->utils->get_demo_content_path() . $this->config['rtcl_custom_files']['rtcl_options'];
		$data      = $this->utils->load_json_file( $file_path );

		if ( is_array( $data ) && ! empty( $data['settings'] ) ) {
			$admin_email = get_option( 'admin_email' );

			foreach ( $data['settings'] as $setting ) {
				$key   = $setting['key'];
				$value = $setting['value'];

				// Handle nested array for rtcl_email_settings.
				if ( 'rtcl_email_settings' === $key && is_array( $value ) ) {
					foreach ( $value as $sub_key => $sub_value ) {
						if ( in_array( $sub_key, [ 'from_email', 'admin_notice_emails' ], true ) ) {
							$value[ $sub_key ] = is_array( $sub_value ) ? [ $admin_email ] : $admin_email;
						}
					}
				}

				update_option( $key, $value );
			}
		}

		update_option( 'rtcl_importing_demo', 'yes' );
	}

	/**
	 * Import RTCL forms from one or more JSON files.
	 *
	 * Supports both single file path (string) and multiple file paths (array).
	 *
	 * @return void
	 */
	private function import_forms(): void {
		if ( ! class_exists( '\Rtcl\Models\Form\Form' ) ) {
			return;
		}

		$rtcl_forms = $this->config['rtcl_custom_files']['rtcl_forms'];

		// Normalize to array for consistent handling.
		if ( is_string( $rtcl_forms ) ) {
			$rtcl_forms = [ $rtcl_forms ];
		}

		$base_path = $this->utils->get_demo_content_path();

		foreach ( $rtcl_forms as $form_file ) {
			$file_path = $base_path . $form_file;
			$forms     = $this->utils->load_json_file( $file_path );

			if ( $forms && is_array( $forms ) ) {
				foreach ( $forms as $form_item ) {
					Form::query()->insert( $form_item );
				}
			}
		}
	}

	/**
	 * Remap taxonomy term IDs in RTCL form conditional logic after import.
	 *
	 * During demo import, taxonomy term IDs change because WordPress
	 * auto-increments IDs. This method rebuilds the conditional logic
	 * in both 'fields' and 'sections' columns of the rtcl_forms table
	 * by looking up terms by name using the 'rtcl_term_name_map' config.
	 *
	 * Conditional logic structure in both fields and sections:
	 * "logics": {
	 *     "status": true,
	 *     "conditions": [
	 *         { "value": 5, "fieldId": "uuid", "operator": "contains" }
	 *     ]
	 * }
	 *
	 * @return void
	 */
	private function remap_form_conditional_term_ids(): void {
		$term_id_map = $this->build_term_id_map();

		if ( empty( $term_id_map ) ) {
			return;
		}

		global $wpdb;

		$table = $wpdb->prefix . 'rtcl_forms';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$forms = $wpdb->get_results( "SELECT id, fields, sections FROM `{$table}`", ARRAY_A );

		if ( empty( $forms ) ) {
			return;
		}

		foreach ( $forms as $form ) {
			$updated  = false;
			$fields   = $this->decode_column( $form['fields'] );
			$sections = $this->decode_column( $form['sections'] );

			// Remap conditional logic in fields.
			if ( is_array( $fields ) ) {
				$fields = $this->remap_fields_conditions( $fields, $term_id_map, $updated );
			}

			// Remap conditional logic in sections.
			if ( is_array( $sections ) ) {
				$sections = $this->remap_sections_conditions( $sections, $term_id_map, $updated );
			}

			if ( $updated ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$table,
					[
						'fields'   => wp_json_encode( $fields ),
						'sections' => wp_json_encode( $sections ),
					],
					[ 'id' => $form['id'] ],
					[ '%s', '%s' ],
					[ '%d' ]
				);
			}
		}
	}

	/**
	 * Build old-to-new term ID map using the config 'rtcl_term_name_map'.
	 *
	 * Looks up each term by name in rtcl_category and rtcl_location
	 * taxonomies to find the new ID assigned after import.
	 *
	 * @return array Associative array of old_id => new_id.
	 */
	private function build_term_id_map(): array {
		$name_map = $this->config['rtcl_term_name_map'] ?? [];

		if ( empty( $name_map ) ) {
			return [];
		}

		$term_id_map = [];
		$taxonomies  = [ 'rtcl_category', 'rtcl_location' ];

		foreach ( $name_map as $old_id => $term_name ) {
			foreach ( $taxonomies as $taxonomy ) {
				if ( ! taxonomy_exists( $taxonomy ) ) {
					continue;
				}

				$term = get_term_by( 'name', $term_name, $taxonomy );

				if ( $term && ! is_wp_error( $term ) ) {
					$term_id_map[ (int) $old_id ] = (int) $term->term_id;
					break;
				}
			}
		}

		return $term_id_map;
	}

	/**
	 * Decode a database column value to array.
	 *
	 * Handles both JSON strings and already-decoded arrays.
	 *
	 * @param mixed $value The column value from database.
	 *
	 * @return array|null Decoded array or null on failure.
	 */
	private function decode_column( $value ) {
		if ( is_array( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );

			if ( json_last_error() === JSON_ERROR_NONE ) {
				return $decoded;
			}

			// Try unserialize as fallback.
			$unserialized = maybe_unserialize( $value );

			if ( is_array( $unserialized ) ) {
				return $unserialized;
			}
		}

		return null;
	}

	/**
	 * Remap conditional logic term IDs in form fields.
	 *
	 * Fields is a keyed object: { "uuid": { "logics": { "conditions": [...] } } }
	 *
	 * @param array $fields      The form fields associative array.
	 * @param array $term_id_map Old-to-new term ID mapping.
	 * @param bool  $updated     Reference flag, set true if changes made.
	 *
	 * @return array Updated fields array.
	 */
	private function remap_fields_conditions( array $fields, array $term_id_map, bool &$updated ): array {
		foreach ( $fields as $uuid => &$field ) {
			if ( empty( $field['logics'] ) || ! is_array( $field['logics'] ) ) {
				continue;
			}

			if ( empty( $field['logics']['conditions'] ) || ! is_array( $field['logics']['conditions'] ) ) {
				continue;
			}

			$field['logics']['conditions'] = $this->remap_conditions(
				$field['logics']['conditions'],
				$term_id_map,
				$updated
			);
		}

		unset( $field );

		return $fields;
	}

	/**
	 * Remap conditional logic term IDs in form sections.
	 *
	 * Sections is a sequential array: [ { "logics": { "conditions": [...] } }, ... ]
	 *
	 * @param array $sections    The form sections array.
	 * @param array $term_id_map Old-to-new term ID mapping.
	 * @param bool  $updated     Reference flag, set true if changes made.
	 *
	 * @return array Updated sections array.
	 */
	private function remap_sections_conditions( array $sections, array $term_id_map, bool &$updated ): array {
		foreach ( $sections as &$section ) {
			if ( empty( $section['logics'] ) || ! is_array( $section['logics'] ) ) {
				continue;
			}

			if ( empty( $section['logics']['conditions'] ) || ! is_array( $section['logics']['conditions'] ) ) {
				continue;
			}

			$section['logics']['conditions'] = $this->remap_conditions(
				$section['logics']['conditions'],
				$term_id_map,
				$updated
			);
		}

		unset( $section );

		return $sections;
	}

	/**
	 * Remap term IDs within a conditions array.
	 *
	 * Each condition: { "value": 5, "fieldId": "uuid-string", "operator": "contains" }
	 * The "value" holds the old taxonomy term ID that needs remapping.
	 *
	 * @param array $conditions  Array of condition rules.
	 * @param array $term_id_map Old-to-new term ID mapping.
	 * @param bool  $updated     Reference flag, set true if changes made.
	 *
	 * @return array Updated conditions array.
	 */
	private function remap_conditions( array $conditions, array $term_id_map, bool &$updated ): array {
		foreach ( $conditions as &$condition ) {
			if ( ! isset( $condition['value'] ) ) {
				continue;
			}

			$old_value = $condition['value'];

			// Handle integer value.
			if ( is_int( $old_value ) && isset( $term_id_map[ $old_value ] ) ) {
				$condition['value'] = $term_id_map[ $old_value ];
				$updated            = true;
				continue;
			}

			// Handle numeric string value.
			if ( is_string( $old_value ) && is_numeric( $old_value ) && isset( $term_id_map[ (int) $old_value ] ) ) {
				$condition['value'] = $term_id_map[ (int) $old_value ];
				$updated            = true;
			}
		}

		unset( $condition );

		return $conditions;
	}

	/**
	 * Update RTCL page options.
	 *
	 * @return void
	 */
	private function update_pages(): void {
		$pages = [];

		foreach ( $this->config['rtcl_pages'] as $key => $title ) {
			$page = Helpers::getPageByTitle( $title );

			if ( $page ) {
				$pages[ $key ] = $page->ID;
			}
		}

		if ( empty( $pages ) ) {
			return;
		}

		$settings         = array_merge( [ 'permalink' => 'listing' ], $pages );
		$defaults         = get_option( 'rtcl_advanced_settings', [] );
		$updated_settings = wp_parse_args( $settings, $defaults );

		update_option( 'rtcl_advanced_settings', $updated_settings );
	}

	/**
	 * Set RTCL listing types.
	 *
	 * @return void
	 */
	private function set_listing_types(): void {
		if ( empty( $this->config['rtcl_listing_types'] ) ) {
			return;
		}

		update_option( 'rtcl_listing_types', $this->config['rtcl_listing_types'] );
	}
}

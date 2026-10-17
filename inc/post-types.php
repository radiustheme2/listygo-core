<?php
/**
 * @author  RadiusTheme
 * @since   1.0
 * @version 1.0
 */

namespace radiustheme\Listygo_Core;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use \RT_Posts;
use radiustheme\listygo\Listing_Functions;

if ( !class_exists( 'RT_Posts' ) ) {
	return;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$prefix = LISTYGO_CORE_THEME_PREFIX;
$post_types = [];
$taxonomies = [];
$Posts = RT_Posts::getInstance();
$Posts->add_post_types( $post_types );
$Posts->add_taxonomies( $taxonomies );
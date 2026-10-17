<?php 
// Security check
defined('ABSPATH') || die();

class RTOPreload {

    public function __construct(){
        add_action('wp_head', [&$this, 'preload']);
        add_action('wp_head', [&$this, 'preconnect']);
    }

	public function preload() {
		global $rt_optimize;

		$urls = array_map(
			'trim',
			explode( ',', (string) $rt_optimize->options->get_option( 'rt_preload_list' ) )
		);

		if ( empty( $urls ) ) {
			return;
		}

		foreach ( $urls as $url ) {
			if ( empty( $url ) ) {
				continue;
			}

			printf(
				'<link rel="preload" href="%1$s" as="font" type="font/woff2" crossorigin />' . "\n",
				esc_url( $url )
			);
		}
	}


	public function preconnect() {
		global $rt_optimize;

		$urls = array_map(
			'trim',
			explode( ',', (string) $rt_optimize->options->get_option( 'rt_preconnect_list' ) )
		);

		if ( empty( $urls ) ) {
			return;
		}

		foreach ( $urls as $url ) {
			if ( empty( $url ) ) {
				continue;
			}

			printf(
				'<link rel="preconnect" href="%1$s" />' . "\n",
				esc_url( $url )
			);
		}
	}
}

new RTOPreload();
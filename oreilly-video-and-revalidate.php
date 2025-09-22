<?php
/**
 * Plugin Name: O’Reilly Video & Revalidate (MU)
 * Description: Kaltura block + Next.js revalidate hook + WPGraphQL fields. MU-loaded via top-level loader.
 * Version: 0.3.0
 */
declare(strict_types=1);

namespace Oreilly\VideoAndRevalidate;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Plugin {
	private static $instance = null;
	public static function instance(): Plugin {
		if ( null === self::$instance ) { self::$instance = new self(); }
		return self::$instance;
	}
	private function __construct() {
		add_action( 'plugins_loaded', [ $this, 'on_plugins_loaded' ] );
	}
	public function on_plugins_loaded(): void {
		add_action( 'init', [ $this, 'register_block_loader' ] );
		add_action( 'transition_post_status', [ $this, 'maybe_revalidate' ], 10, 3 );
		add_action( 'graphql_register_types', [ $this, 'register_graphql_types' ] );
	}

	/** Register assets + block with server render */
	public function register_block_loader(): void {
		$assets_dir = __DIR__ . '/assets';
		$base_url   = plugin_dir_url( __FILE__ );

		$ver_editor = file_exists( $assets_dir . '/kaltura-editor.js' ) ? (string) filemtime( $assets_dir . '/kaltura-editor.js' ) : '0';
		wp_register_script(
			'oreilly-kaltura-editor',
			$base_url . 'assets/kaltura-editor.js',
			[ 'wp-blocks', 'wp-i18n', 'wp-element', 'wp-components', 'wp-block-editor' ],
			$ver_editor,
			true
		);

		$ver_view = file_exists( $assets_dir . '/kaltura-view.js' ) ? (string) filemtime( $assets_dir . '/kaltura-view.js' ) : '0';
		wp_register_script(
			'oreilly-kaltura-view',
			$base_url . 'assets/kaltura-view.js',
			[],
			$ver_view,
			true
		);

		$ver_style = file_exists( $assets_dir . '/kaltura-style.css' ) ? (string) filemtime( $assets_dir . '/kaltura-style.css' ) : '0';
		wp_register_style(
			'oreilly-kaltura-style',
			$base_url . 'assets/kaltura-style.css',
			[],
			$ver_style
		);

		register_block_type(
			__DIR__ . '/blocks/kaltura/block.json',
			[ 'render_callback' => [ $this, 'render_kaltura_block' ] ]
		);
	}

	/** Server-side renderer: consent-gated, lazy-loaded container with GA4-friendly data-* */
	public function render_kaltura_block( array $attributes, string $content, \WP_Block $block ): string {
		$partner  = isset( $attributes['partnerId'] ) ? sanitize_text_field( (string) $attributes['partnerId'] ) : '';
		$entry    = isset( $attributes['entryId'] ) ? sanitize_text_field( (string) $attributes['entryId'] ) : '';
		$poster   = isset( $attributes['poster'] ) ? esc_url_raw( (string) $attributes['poster'] ) : '';
		$autoplay = ! empty( $attributes['autoplay'] );
		$consent  = isset( $attributes['consentRequired'] ) ? (bool) $attributes['consentRequired'] : true;

		$data = sprintf(
			' data-video="kaltura" data-entryid="%1$s" data-partnerid="%2$s" data-autoplay="%3$s" data-consent="%4$s"%5$s',
			esc_attr( $entry ),
			esc_attr( $partner ),
			$autoplay ? '1' : '0',
			$consent ? '1' : '0',
			$poster ? ' data-poster="' . esc_attr( $poster ) . '"' : ''
		);

		wp_enqueue_style( 'oreilly-kaltura-style' );
		wp_enqueue_script( 'oreilly-kaltura-view' );

		$placeholder = $consent
			? sprintf(
				'<div class="oreilly-kaltura-consent"><div><p>%s</p><button type="button" data-consent-button="1">%s</button></div></div>',
				esc_html__( 'To play this video, please allow media from our provider.', 'oreilly' ),
				esc_html__( 'Allow & Play', 'oreilly' )
			)
			: '';

		return sprintf( '<div class="oreilly-kaltura-container"%1$s>%2$s</div>', $data, $placeholder );
	}

	/** On publish/update, POST { path, secret } to Next.js revalidate endpoint */
	public function maybe_revalidate( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( 'inherit' === $post->post_status || 'revision' === $post->post_type ) { return; }
		if ( 'publish' !== $new_status ) { return; }

		$secret   = defined( 'REVALIDATE_SECRET' ) ? (string) REVALIDATE_SECRET : (string) getenv( 'REVALIDATE_SECRET' );
		$endpoint = defined( 'VERCEL_REVALIDATE_URL' ) ? (string) VERCEL_REVALIDATE_URL : (string) getenv( 'VERCEL_REVALIDATE_URL' );
		if ( empty( $secret ) || empty( $endpoint ) ) { return; }

		$slug = $post->post_name ? $post->post_name : sanitize_title( (string) $post->post_title );
		$path = apply_filters( 'oreilly_revalidate_path', '/articles/' . $slug, $post );

		$args = [
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => wp_json_encode( [ 'path' => $path, 'secret' => $secret ] ),
			'timeout' => 5,
		];
		$response = wp_remote_post( $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			error_log( '[oreilly-revalidate] WP_Error: ' . $response->get_error_message() );
			return;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			error_log( '[oreilly-revalidate] Non-200 from revalidate endpoint: ' . $code );
		}
	}

	/** WPGraphQL: expose list of Kaltura blocks on Posts/Pages (typed fields) */
	public function register_graphql_types(): void {
		if ( ! function_exists( 'register_graphql_object_type' ) || ! function_exists( 'register_graphql_field' ) ) {
			return;
		}

		register_graphql_object_type( 'OreillyKalturaBlock', [
			'description' => 'Attributes of oreilly/kaltura-video blocks on a node.',
			'fields'      => [
				'partnerId'       => [ 'type' => 'String' ],
				'entryId'         => [ 'type' => 'String' ],
				'poster'          => [ 'type' => 'String' ],
				'autoplay'        => [ 'type' => 'Boolean' ],
				'consentRequired' => [ 'type' => 'Boolean' ],
			],
		] );

		$resolver = function( $source ) {
			$post = isset( $source->ID ) ? get_post( (int) $source->ID ) : null;
			if ( ! $post ) { return []; }

			$blocks = function_exists( 'parse_blocks' ) ? parse_blocks( (string) $post->post_content ) : [];
			$out = [];

			$walk = function( array $items ) use ( &$walk, &$out ) {
				foreach ( $items as $b ) {
					$name = $b['blockName'] ?? null;
					if ( $name === 'oreilly/kaltura-video' ) {
						$attrs = is_array( $b['attrs'] ?? null ) ? $b['attrs'] : [];
						$out[] = [
							'partnerId'       => isset( $attrs['partnerId'] ) ? (string) $attrs['partnerId'] : '',
							'entryId'         => isset( $attrs['entryId'] ) ? (string) $attrs['entryId'] : '',
							'poster'          => isset( $attrs['poster'] ) ? (string) $attrs['poster'] : '',
							'autoplay'        => ! empty( $attrs['autoplay'] ),
							'consentRequired' => array_key_exists( 'consentRequired', $attrs ) ? (bool) $attrs['consentRequired'] : true,
						];
					}
					if ( ! empty( $b['innerBlocks'] ) && is_array( $b['innerBlocks'] ) ) {
						$walk( $b['innerBlocks'] );
					}
				}
			};
			$walk( is_array( $blocks ) ? $blocks : [] );
			return $out;
		};

		foreach ( [ 'Post', 'Page' ] as $type ) {
			register_graphql_field( $type, 'kalturaBlocks', [
				'type'    => [ 'list_of' => 'OreillyKalturaBlock' ],
				'resolve' => $resolver,
			] );
		}
	}
}
Plugin::instance();

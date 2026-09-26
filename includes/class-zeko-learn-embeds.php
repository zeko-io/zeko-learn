<?php
/**
 * Zeko Learn — privacy-aware video embeds.
 *
 * Normalizes lesson video URLs to privacy-enhanced hosts (YouTube "nocookie"),
 * enforces an allow-list of embed domains, and lets admins disable external
 * embeds entirely. Course/site owners must disclose these third-party
 * services in their privacy policy.
 *
 * @package Zeko_Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render a privacy-sandboxed iframe for a lesson video URL.
 * embeds are disabled).
 *
 * @return string Safe iframe HTML ('' when the URL is not embeddable or
 * @param string $video_url Raw lesson video URL.
 */
function zeko_learn_render_video_embed( string $video_url ): string {
	$url = trim( $video_url );
	if ( '' === $url ) {
		return '';
	}

	if ( ! get_option( 'zeko_learn_embeds_enabled', 1 ) ) {
		return '';
	}

	$host = (string) wp_parse_url( $url, PHP_URL_HOST );
	if ( '' === $host || 'https' !== (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) {
		return '';
	}

	// Rewrite YouTube to its privacy-enhanced domain unless the admin opted out.
	$nocookie = (bool) get_option( 'zeko_learn_embed_nocookie', 1 );

	$embed_url = '';
	$id        = '';

	if ( preg_match( '/(?:^|\.)youtube\.com$|(?:^|\.)youtu\.be$/', $host ) ) {
		if ( preg_match( '#(?:v=|/shorts/|/embed/|youtu\.be/)([a-zA-Z0-9_-]{6,20})#', $url, $m ) ) {
			$icon      = $nocookie ? 'www.youtube-nocookie.com' : 'www.youtube.com';
			$embed_url = 'https://' . $icon . '/embed/' . $m[1];
		}
	} elseif ( preg_match( '/(?:^|\.)vimeo\.com$/', $host ) ) {
		preg_match( '/vimeo\.com\/(?:video\/)?(\d+)/', $url, $m );
		if ( isset( $m[1] ) && '' !== $m[1] ) {
			$embed_url = 'https://player.vimeo.com/video/' . $m[1];
		}
	}

	if ( '' === $embed_url ) {
		return '';
	}

	// Allow-list enforcement: any embed URL's host must be permitted.
	static $allowed = null;
	if ( null === $allowed ) {
		$allowed = array(
			'www.youtube-nocookie.com',
			'www.youtube.com',
			'player.vimeo.com',
		);
	}
	$embed_host = (string) wp_parse_url( $embed_url, PHP_URL_HOST );
	if ( ! in_array( $embed_host, $allowed, true ) ) {
		return '';
	}

	return '<iframe src="' . esc_url( $embed_url ) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen title="' . esc_attr__( 'Lesson video', 'zeko-learn' ) . '"></iframe>';
}

/**
 * Register and sanitize the embed/privacy settings.
 */
function zeko_learn_register_embed_settings(): void {
	$bool = array(
		'default' => 1,
		'type'    => 'boolean',
	);
	register_setting( 'zeko_learn_settings', 'zeko_learn_embeds_enabled', $bool );
	register_setting( 'zeko_learn_settings', 'zeko_learn_embed_nocookie', $bool );
}
add_action( 'admin_init', 'zeko_learn_register_embed_settings' );

/**
 * Handle toggling from the settings screen: keep the document-body filter
 * simple so templates only ever call zeko_learn_render_video_embed().
 *
 * @return void
 */
function zeko_learn_embed_settings_fields(): void {
	echo '<tr><th>' . esc_html__( 'Video Embeds', 'zeko-learn' ) . '</th><td>';
	echo '<label><input type="checkbox" name="zeko_learn_embeds_enabled" value="1" ' . checked( (bool) get_option( 'zeko_learn_embeds_enabled', 1 ), true, false ) . ' /> ' . esc_html__( 'Allow external video embeds (YouTube/Vimeo)', 'zeko-learn' ) . '</label><br>';
	echo '<label><input type="checkbox" name="zeko_learn_embed_nocookie" value="1" ' . checked( (bool) get_option( 'zeko_learn_embed_nocookie', 1 ), true, false ) . ' /> ' . esc_html__( 'Use YouTube’s privacy-enhanced (nocookie) domain', 'zeko-learn' ) . '</label>';
	/* translators: %s: help text */
	echo '<p class="description">' . esc_html__( 'Embedding lesson videos on YouTube or Vimeo streams data to those third parties. Keep the privacy-enhanced domain enabled and disclose this in your privacy policy.', 'zeko-learn' ) . '</p>';
	echo '</td></tr>';
}

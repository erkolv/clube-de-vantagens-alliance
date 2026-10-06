<?php
/**
 * Tema filho do Hello Elementor para o clube de vantagens.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CLUBE_CHILD_VERSION', '0.1.0' );

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style(
		'clube-poppins',
		'https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400&display=swap',
		[],
		null
	);

	wp_enqueue_style(
		'clube-alliance-child',
		get_stylesheet_uri(),
		[ 'clube-poppins' ],
		CLUBE_CHILD_VERSION
	);

	// O plugin registra o handle "cav" (prioridade 10). Aqui, mais tarde, anexamos a
	// versão escura: o CSS só sai nas páginas em que um shortcode do plugin roda.
	$arquivo = get_stylesheet_directory() . '/assets/cav-escuro.css';

	if ( wp_style_is( 'cav', 'registered' ) && is_readable( $arquivo ) ) {
		wp_add_inline_style( 'cav', file_get_contents( $arquivo ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
}, 20 );

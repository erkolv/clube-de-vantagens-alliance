<?php
/**
 * Grava as páginas do clube no formato do Elementor.
 *
 *   wp eval-file /scripts/paginas-elementor.php          só páginas que ainda não foram montadas
 *   wp eval-file /scripts/paginas-elementor.php refazer  refaz todas (apaga o que você editou nelas)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

require __DIR__ . '/lib/paginas.php';

$refazer = isset( $args[0] ) && 'refazer' === $args[0];
$versao  = defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0';

foreach ( cav_paginas_elementor() as $slug => $pagina ) {
	$post = get_page_by_path( $slug );

	if ( ! $post ) {
		WP_CLI::warning( "Página /$slug/ não existe, pulei." );
		continue;
	}

	if ( ! $refazer && get_post_meta( $post->ID, '_elementor_data', true ) ) {
		WP_CLI::log( "   já montada, mantive: {$pagina['titulo']} (/$slug/)" );
		continue;
	}

	// Texto simples no corpo da página: aparece se o Elementor não conseguir desenhar o layout.
	wp_update_post( [
		'ID'           => $post->ID,
		'post_content' => wp_slash( cav_pg_html_simples( $pagina['dados'] ) ),
	] );

	update_post_meta( $post->ID, '_elementor_edit_mode', 'builder' );
	update_post_meta( $post->ID, '_elementor_template_type', 'wp-page' );
	update_post_meta( $post->ID, '_elementor_version', $versao );
	update_post_meta( $post->ID, '_wp_page_template', 'elementor_header_footer' );
	update_post_meta( $post->ID, '_elementor_data', wp_slash( wp_json_encode( $pagina['dados'] ) ) );
	delete_post_meta( $post->ID, '_elementor_css' );

	WP_CLI::log( "   montada: {$pagina['titulo']} (/$slug/)" );
}

if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
	\Elementor\Plugin::$instance->files_manager->clear_cache();
}

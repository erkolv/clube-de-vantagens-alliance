<?php
/**
 * Páginas da área do parceiro: Minhas promoções e Meu negócio, e o menu da área
 * no Terminal e no Painel. Pode rodar de novo: o que já está certo não é tocado.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CAV_AreaParceiro' ) ) {
	WP_CLI::error( 'O plugin clube-alliance não está ativo.' );
}

$menu = '[cav_menu_parceiro]';

$novas = [
	'minhas-promocoes' => [ 'Minhas promoções', $menu . "\n[cav_minhas_promocoes]" ],
	'meu-negocio'      => [ 'Meu negócio', $menu . "\n[cav_meu_negocio]" ],
];

foreach ( $novas as $slug => $dados ) {
	if ( get_page_by_path( $slug ) ) {
		WP_CLI::log( "   já existia: {$dados[0]} (/{$slug}/)" );
		continue;
	}

	$id = wp_insert_post( [
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => $dados[0],
		'post_name'    => $slug,
		'post_content' => $dados[1],
	] );

	if ( is_wp_error( $id ) ) {
		WP_CLI::warning( $id->get_error_message() );
		continue;
	}

	WP_CLI::log( "   criada: {$dados[0]} (/{$slug}/)" );
}

foreach ( [ 'terminal', 'painel-do-parceiro' ] as $slug ) {
	$pagina = get_page_by_path( $slug );

	if ( ! $pagina || false !== strpos( $pagina->post_content, 'cav_menu_parceiro' ) ) {
		continue;
	}

	wp_update_post( [
		'ID'           => $pagina->ID,
		'post_content' => $menu . "\n" . $pagina->post_content,
	] );
	WP_CLI::log( "   menu do parceiro incluído em /{$slug}/" );
}

WP_CLI::success( 'Páginas da área do parceiro prontas.' );

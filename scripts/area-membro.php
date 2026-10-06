<?php
/**
 * Páginas da área do aluno: Painel, Ofertas e Agenda, e o menu da área nas
 * páginas que já existiam (carteirinha, benefícios, sorteios, conteúdos).
 * Pode rodar de novo: o que já está certo não é tocado, e o conteúdo que você
 * editou nas páginas novas fica como está.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CAV_Area' ) ) {
	WP_CLI::error( 'O plugin clube-alliance não está ativo.' );
}

$menu = '[cav_menu_membro]';

$novas = [
	'area-do-membro' => [ 'Área do membro', $menu . "\n[cav_painel_membro]" ],
	'ofertas'        => [ 'Ofertas da academia', $menu . "\n[cav_ofertas]" ],
	'agenda'         => [ 'Agenda e eventos', $menu . "\n[cav_agenda]" ],
];

foreach ( $novas as $slug => $dados ) {
	$pagina = get_page_by_path( $slug );

	if ( $pagina ) {
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

// Páginas que já existiam ganham o menu no topo, se ainda não têm.
foreach ( [ 'minha-carteirinha', 'meus-beneficios', 'sorteios-do-clube', 'conteudos' ] as $slug ) {
	$pagina = get_page_by_path( $slug );

	if ( ! $pagina || false !== strpos( $pagina->post_content, 'cav_menu_membro' ) ) {
		continue;
	}

	wp_update_post( [
		'ID'           => $pagina->ID,
		'post_content' => $menu . "\n" . $pagina->post_content,
	] );
	WP_CLI::log( "   menu da área incluído em /{$slug}/" );
}

WP_CLI::success( 'Páginas da área do aluno prontas.' );

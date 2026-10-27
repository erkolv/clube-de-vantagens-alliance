<?php
/**
 * Sorteios mensais do clube, de novembro a junho, como RASCUNHO (um por mês, com o prêmio combinado).
 * Rascunho não aparece para ninguém: para abrir um mês, vá em Sorteios, confira o prêmio e as datas e publique.
 * A inscrição vem do dia 1 ao último dia do mês; ajuste se o sorteio tiver outras datas.
 *
 * Roda uma única vez para criar (opção cav_sorteios_calendario), então apagar um sorteio não faz ele voltar.
 * Uma segunda etapa (opção cav_sorteios_premios) preenche os prêmios dos rascunhos que já existiam sem prêmio,
 * sem mexer em nenhum que já tenha prêmio escrito.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// mês => [ nome do mês, prêmio ]
$calendario = [
	11 => [ 'novembro', 'Mochila Multibag Alliance JJ Team' ],
	12 => [ 'dezembro', 'Kimono Ultralight' ],
	1  => [ 'janeiro', 'Kit Nogi: bermuda e lycra' ],
	2  => [ 'fevereiro', 'Moletom / corta-vento' ],
	3  => [ 'março', 'Chinelo e garrafa' ],
	4  => [ 'abril', 'Kimono Ultralight' ],
	5  => [ 'maio', 'Mochila' ],
	6  => [ 'junho', 'Personal com Rodolfo ou Dimi' ],
];

// O primeiro novembro que ainda não passou.
$ano_nov = (int) wp_date( 'Y' );
if ( (int) wp_date( 'n' ) > 11 ) {
	$ano_nov++;
}

if ( ! get_option( 'cav_sorteios_calendario' ) ) {
	$criados = 0;
	foreach ( $calendario as $n => $dados ) {
		$ano = $n >= 11 ? $ano_nov : $ano_nov + 1;
		$ini = sprintf( '%04d-%02d-01', $ano, $n );
		$fim = gmdate( 'Y-m-t', strtotime( $ini ) );

		$id = wp_insert_post( [
			'post_type'    => 'cav_sorteio',
			'post_status'  => 'draft',
			'post_title'   => 'Sorteio de ' . $dados[0] . ' de ' . $ano,
			'post_content' => 'Sorteio do clube de ' . $dados[0] . '. Participe pela página do sorteio.',
		] );

		if ( is_wp_error( $id ) || ! $id ) {
			continue;
		}

		update_post_meta( $id, CAV_Acesso::META_PUBLICO, 'membros' );
		update_post_meta( $id, CAV_Conteudo::META_PREMIO, $dados[1] );
		update_post_meta( $id, CAV_Conteudo::META_INICIO, $ini );
		update_post_meta( $id, CAV_Conteudo::META_FIM, $fim );
		$criados++;
	}

	update_option( 'cav_sorteios_calendario', 1, false );
	update_option( 'cav_sorteios_premios', 1, false ); // já nasceram com prêmio
	echo "   sorteios de novembro a junho: $criados rascunhos criados, com o prêmio de cada mês\n";
	return;
}

if ( get_option( 'cav_sorteios_premios' ) ) {
	echo "   sorteios do calendário: já criados e com prêmio, nada a fazer\n";
	return;
}

// Rascunhos criados antes de existir a lista de prêmios: preenche só os que estão sem prêmio.
$preenchidos = 0;
$posts       = get_posts( [ 'post_type' => 'cav_sorteio', 'post_status' => 'any', 'numberposts' => 100 ] );
foreach ( $posts as $p ) {
	foreach ( $calendario as $dados ) {
		if ( 0 === stripos( $p->post_title, 'Sorteio de ' . $dados[0] . ' de ' ) && '' === trim( (string) get_post_meta( $p->ID, CAV_Conteudo::META_PREMIO, true ) ) ) {
			update_post_meta( $p->ID, CAV_Conteudo::META_PREMIO, $dados[1] );
			$preenchidos++;
		}
	}
}

update_option( 'cav_sorteios_premios', 1, false );
echo "   sorteios do calendário: prêmio preenchido em $preenchidos rascunho(s) que estavam sem\n";

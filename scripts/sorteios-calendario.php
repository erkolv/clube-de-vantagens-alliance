<?php
/**
 * Cria, uma única vez, os sorteios de novembro a junho como RASCUNHO, um por mês.
 * O produto e as datas exatas ficam para a academia preencher: abra Sorteios, escolha o mês,
 * escreva o prêmio, ajuste as datas se precisar e publique. Rascunho não aparece para ninguém.
 *
 * Como o script marca que já rodou (opção cav_sorteios_calendario), apagar um sorteio
 * não faz ele voltar na próxima atualização.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( get_option( 'cav_sorteios_calendario' ) ) {
	echo "   sorteios do calendário: já criados antes, nada a fazer\n";
	return;
}

$meses = [ 11 => 'novembro', 12 => 'dezembro', 1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril', 5 => 'maio', 6 => 'junho' ];

// O primeiro novembro que ainda não passou.
$ano_nov = (int) wp_date( 'Y' );
if ( (int) wp_date( 'n' ) > 11 ) {
	$ano_nov++;
}

$criados = 0;
foreach ( $meses as $n => $nome ) {
	$ano = $n >= 11 ? $ano_nov : $ano_nov + 1;
	$ini = sprintf( '%04d-%02d-01', $ano, $n );
	$fim = gmdate( 'Y-m-t', strtotime( $ini ) );

	$id = wp_insert_post( [
		'post_type'    => 'cav_sorteio',
		'post_status'  => 'draft',
		'post_title'   => 'Sorteio de ' . $nome . ' de ' . $ano,
		'post_content' => 'Sorteio do clube de ' . $nome . '. Participe pela página do sorteio.',
	] );

	if ( is_wp_error( $id ) || ! $id ) {
		continue;
	}

	update_post_meta( $id, CAV_Acesso::META_PUBLICO, 'membros' );
	update_post_meta( $id, CAV_Conteudo::META_PREMIO, '' );
	update_post_meta( $id, CAV_Conteudo::META_INICIO, $ini );
	update_post_meta( $id, CAV_Conteudo::META_FIM, $fim );
	$criados++;
}

update_option( 'cav_sorteios_calendario', 1, false );
echo "   sorteios de novembro a junho: $criados rascunhos criados (falta escrever o prêmio de cada um)\n";

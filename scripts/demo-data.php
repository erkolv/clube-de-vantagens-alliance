<?php
/**
 * Dados de demonstração: um parceiro com dois benefícios e três contas de teste.
 * Roda com: ./scripts/setup.sh --demo
 *
 * Só funciona em ambiente local (WP_ENVIRONMENT_TYPE = local). A trava existe
 * para ninguém criar contas com senha de teste num site de verdade.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( 'local' !== wp_get_environment_type() ) {
	WP_CLI::error( 'Os dados de demonstração só rodam em ambiente local.' );
}

if ( ! class_exists( 'CAV_Membro' ) ) {
	WP_CLI::error( 'O plugin clube-alliance não está ativo.' );
}

$senha = getenv( 'DEMO_PASSWORD' ) ?: 'demo123';

/** Cria o usuário se ainda não existir. */
$criar_usuario = static function ( $login, $email, $nome, $papel ) use ( $senha ) {
	$existente = get_user_by( 'login', $login );

	if ( $existente ) {
		return (int) $existente->ID;
	}

	$id = wp_insert_user( [
		'user_login'   => $login,
		'user_email'   => $email,
		'user_pass'    => $senha,
		'display_name' => $nome,
		'first_name'   => strtok( $nome, ' ' ),
		'role'         => $papel,
	] );

	if ( is_wp_error( $id ) ) {
		WP_CLI::error( $id->get_error_message() );
	}

	return (int) $id;
};

/* ---- Parceiro -------------------------------------------------------- */

$existentes = get_posts( [
	'post_type'   => cav_parceiro_post_type(),
	'title'       => 'Barbearia Corte Reto',
	'post_status' => 'any',
	'numberposts' => 1,
] );

if ( $existentes ) {
	$parceiro_id = (int) $existentes[0]->ID;
} else {
	$parceiro_id = wp_insert_post( [
		'post_type'    => cav_parceiro_post_type(),
		'post_status'  => 'publish',
		'post_title'   => 'Barbearia Corte Reto',
		'post_content' => 'Barbearia no Centro de Mogi das Cruzes. Segunda a sábado, das 9h às 20h.',
	] );
}

$beneficio = static function ( $titulo, $regra, $limite, $fim ) use ( $parceiro_id ) {
	$ja = get_posts( [
		'post_type'   => 'cav_beneficio',
		'title'       => $titulo,
		'post_status' => 'any',
		'numberposts' => 1,
	] );

	if ( $ja ) {
		return;
	}

	$id = wp_insert_post( [
		'post_type'   => 'cav_beneficio',
		'post_status' => 'publish',
		'post_title'  => $titulo,
	] );

	update_post_meta( $id, CAV_Parceiro::META_BEN_PARCEIRO, $parceiro_id );
	update_post_meta( $id, CAV_Parceiro::META_BEN_REGRA, $regra );
	update_post_meta( $id, CAV_Parceiro::META_BEN_LIMITE, $limite );
	update_post_meta( $id, CAV_Parceiro::META_BEN_ATIVO, '1' );
	update_post_meta( $id, CAV_Parceiro::META_BEN_FIM, $fim );
};

$beneficio( 'Corte de cabelo — 30% off', 'Promoção, segunda a quinta, uma vez por semana', 'semana', wp_date( 'Y-m-d', strtotime( '+30 days' ) ) );
$beneficio( 'Barba — 10% off', 'Permanente, sem limite de uso', 'nenhum', '' );

/* ---- Contas ---------------------------------------------------------- */

$parceiro_user = $criar_usuario( 'parceiro.demo', 'parceiro.demo@example.com', 'Corte Reto', 'cav_parceiro' );
update_user_meta( $parceiro_user, CAV_Parceiro::META_USER_PARCEIRO, $parceiro_id );

$aluno = $criar_usuario( 'aluno.demo', 'aluno.demo@example.com', 'Rafael Moreira', 'cav_membro' );
CAV_Membro::set_cpf( $aluno, '111.444.777-35' ); // CPF de teste válido
CAV_Membro::set_validade( $aluno, wp_date( 'Y-m-d', strtotime( '+40 days' ) ) );
update_user_meta( $aluno, 'cav_status', 'ativo' );

$vencido = $criar_usuario( 'aluno.vencido', 'aluno.vencido@example.com', 'Bruno Alves', 'cav_membro' );
CAV_Membro::set_cpf( $vencido, '529.982.247-25' ); // CPF de teste válido
CAV_Membro::set_validade( $vencido, wp_date( 'Y-m-d', strtotime( '-10 days' ) ) );
update_user_meta( $vencido, 'cav_status', 'ativo' );

$criar_usuario( 'recepcao.demo', 'recepcao.demo@example.com', 'Recepção Alliance', 'cav_recepcao' );

WP_CLI::success( 'Dados de demonstração prontos.' );
WP_CLI::log( '  aluno.demo      CPF 111.444.777-35  (ativo)' );
WP_CLI::log( '  aluno.vencido   CPF 529.982.247-25  (matrícula vencida)' );
WP_CLI::log( '  parceiro.demo   opera a Barbearia Corte Reto' );
WP_CLI::log( '  recepcao.demo   vê só a tela de aprovações' );
WP_CLI::log( '  senha das contas de teste: a do .env (DEMO_PASSWORD)' );

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CAV_Rest {

	const NS         = 'clube-alliance/v1';
	const TOKEN_TTL  = 300; // 5 minutos entre consultar e registrar

	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'registrar_rotas' ] );
	}

	public static function registrar_rotas() {
		register_rest_route( self::NS, '/consulta', [
			'methods'             => 'POST',
			'callback'            => [ __CLASS__, 'consulta' ],
			'permission_callback' => [ __CLASS__, 'pode_operar' ],
			'args'                => [
				'cpf' => [ 'required' => true, 'type' => 'string' ],
			],
		] );

		register_rest_route( self::NS, '/uso', [
			'methods'             => 'POST',
			'callback'            => [ __CLASS__, 'uso' ],
			'permission_callback' => [ __CLASS__, 'pode_operar' ],
			'args'                => [
				'token'        => [ 'required' => true, 'type' => 'string' ],
				'beneficio_id' => [ 'required' => true, 'type' => 'integer' ],
			],
		] );

		register_rest_route( self::NS, '/participar', [
			'methods'             => 'POST',
			'callback'            => [ __CLASS__, 'participar' ],
			'permission_callback' => 'is_user_logged_in',
			'args'                => [
				'sorteio_id' => [ 'required' => true, 'type' => 'integer' ],
			],
		] );
	}

	public static function participar( WP_REST_Request $req ) {
		$sorteio_id = absint( $req->get_param( 'sorteio_id' ) );
		$sorteio    = get_post( $sorteio_id );

		if ( ! $sorteio || 'cav_sorteio' !== $sorteio->post_type || 'publish' !== $sorteio->post_status ) {
			return new WP_Error( 'cav_sorteio', 'Sorteio não encontrado.', [ 'status' => 404 ] );
		}

		if ( ! CAV_Acesso::pode_ver_post( $sorteio_id ) ) {
			return new WP_Error( 'cav_sem_acesso', 'Este sorteio não está aberto para você.', [ 'status' => 403 ] );
		}

		if ( ! CAV_Acesso::e_membro_ativo() ) {
			return new WP_Error( 'cav_inativo', 'Sua matrícula precisa estar ativa para participar.', [ 'status' => 403 ] );
		}

		if ( ! CAV_Conteudo::inscricoes_abertas( $sorteio_id ) ) {
			return new WP_Error( 'cav_fechado', 'As inscrições deste sorteio estão encerradas.', [ 'status' => 409 ] );
		}

		$res = CAV_Sorteio::inscrever( $sorteio_id, get_current_user_id() );
		if ( is_wp_error( $res ) ) {
			return new WP_Error( $res->get_error_code(), $res->get_error_message(), [ 'status' => 409 ] );
		}

		return new WP_REST_Response( [ 'inscrito' => true ], 200 );
	}

	public static function pode_operar() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'cav_sem_login', 'Faça login para usar o terminal.', [ 'status' => 401 ] );
		}
		if ( ! current_user_can( 'cav_consultar' ) ) {
			return new WP_Error( 'cav_sem_permissao', 'Sua conta não tem acesso ao terminal.', [ 'status' => 403 ] );
		}
		return true;
	}

	/** Parceiro vinculado ao operador atual. */
	private static function contexto() {
		$operador_id = get_current_user_id();
		$parceiro_id = CAV_Parceiro::parceiro_do_usuario( $operador_id );

		// Admin pode operar apontando um parceiro manualmente na tela.
		if ( ! $parceiro_id && current_user_can( 'cav_gerenciar' ) ) {
			$parceiro_id = (int) ( $_REQUEST['parceiro_id'] ?? 0 );
		}

		return [ $operador_id, (int) $parceiro_id ];
	}

	public static function consulta( WP_REST_Request $req ) {
		list( $operador_id, $parceiro_id ) = self::contexto();

		if ( ! $parceiro_id ) {
			return new WP_Error( 'cav_sem_parceiro', 'Sua conta não está vinculada a nenhum estabelecimento.', [ 'status' => 400 ] );
		}

		if ( ! CAV_Usos::dentro_do_limite( $operador_id ) ) {
			return new WP_Error( 'cav_limite', 'Muitas consultas nesta hora. Tente de novo mais tarde.', [ 'status' => 429 ] );
		}

		$cpf = CAV_CPF::normalizar( $req->get_param( 'cpf' ) );

		if ( ! CAV_CPF::valido( $cpf ) ) {
			CAV_Usos::registrar_consulta( $operador_id, $cpf, 'cpf_invalido' );
			return new WP_REST_Response( [
				'elegivel' => false,
				'titulo'   => 'CPF inválido',
				'detalhe'  => 'Confira os números e digite de novo.',
			], 200 );
		}

		$user = CAV_CPF::buscar_usuario( $cpf );

		if ( ! $user ) {
			CAV_Usos::registrar_consulta( $operador_id, $cpf, 'nao_membro' );
			return new WP_REST_Response( [
				'elegivel' => false,
				'titulo'   => 'Sem benefício',
				'detalhe'  => CAV_Membro::motivo_legivel( 'nao_membro' ),
			], 200 );
		}

		$check = CAV_Membro::checar( $user );
		CAV_Usos::registrar_consulta( $operador_id, $cpf, $check['motivo'] );

		if ( ! $check['elegivel'] ) {
			return new WP_REST_Response( [
				'elegivel' => false,
				'titulo'   => 'Sem benefício',
				'detalhe'  => CAV_Membro::motivo_legivel( $check['motivo'] ),
				'nome'     => CAV_Membro::primeiro_nome( $user ),
			], 200 );
		}

		// Token curto amarra o registro de uso a esta consulta.
		$token = wp_generate_password( 24, false );
		set_transient( 'cav_tk_' . $token, [
			'membro_id'   => $user->ID,
			'operador_id' => $operador_id,
			'parceiro_id' => $parceiro_id,
		], self::TOKEN_TTL );

		$beneficios = [];
		foreach ( CAV_Parceiro::beneficios( $parceiro_id ) as $b ) {
			$limite = CAV_Usos::pode_usar( $user->ID, $b->ID );
			$beneficios[] = [
				'id'         => $b->ID,
				'titulo'     => get_the_title( $b ),
				'regra'      => get_post_meta( $b->ID, CAV_Parceiro::META_BEN_REGRA, true ),
				'vigencia'   => CAV_Parceiro::rotulo_vigencia( $b->ID ),
				'temporario' => CAV_Parceiro::e_temporario( $b->ID ),
				'disponivel' => $limite['pode'],
				'motivo'     => $limite['motivo'],
			];
		}

		return new WP_REST_Response( [
			'elegivel'   => true,
			'titulo'     => 'Tem benefício',
			'nome'       => CAV_Membro::primeiro_nome( $user ),
			'token'      => $token,
			'beneficios' => $beneficios,
		], 200 );
	}

	public static function uso( WP_REST_Request $req ) {
		$token = sanitize_text_field( (string) $req->get_param( 'token' ) );
		$dados = get_transient( 'cav_tk_' . $token );

		if ( ! $dados ) {
			return new WP_Error( 'cav_token', 'A consulta expirou. Digite o CPF de novo.', [ 'status' => 409 ] );
		}

		if ( (int) $dados['operador_id'] !== get_current_user_id() ) {
			return new WP_Error( 'cav_token_alheio', 'Consulta não pertence a esta conta.', [ 'status' => 403 ] );
		}

		$beneficio_id = absint( $req->get_param( 'beneficio_id' ) );
		$beneficio    = get_post( $beneficio_id );

		if ( ! $beneficio || 'cav_beneficio' !== $beneficio->post_type ) {
			return new WP_Error( 'cav_beneficio', 'Benefício não encontrado.', [ 'status' => 404 ] );
		}

		// O benefício precisa ser do mesmo parceiro da consulta.
		if ( (int) get_post_meta( $beneficio_id, CAV_Parceiro::META_BEN_PARCEIRO, true ) !== (int) $dados['parceiro_id'] ) {
			return new WP_Error( 'cav_beneficio_outro', 'Este benefício é de outro estabelecimento.', [ 'status' => 403 ] );
		}

		if ( ! CAV_Parceiro::em_vigencia( $beneficio_id ) ) {
			return new WP_Error( 'cav_fora_vigencia', 'Este benefício não está mais no ar.', [ 'status' => 409 ] );
		}

		if ( ! get_post_meta( $beneficio_id, CAV_Parceiro::META_BEN_ATIVO, true ) ) {
			return new WP_Error( 'cav_inativo', 'Este benefício está desativado.', [ 'status' => 409 ] );
		}

		$limite = CAV_Usos::pode_usar( $dados['membro_id'], $beneficio_id );
		if ( ! $limite['pode'] ) {
			return new WP_Error( 'cav_limite_uso', $limite['motivo'], [ 'status' => 409 ] );
		}

		$id = CAV_Usos::registrar_uso( $dados['membro_id'], $beneficio_id, $dados['parceiro_id'], $dados['operador_id'] );
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		// Um token = um registro.
		delete_transient( 'cav_tk_' . $token );

		return new WP_REST_Response( [
			'registrado' => true,
			'uso_id'     => $id,
			'titulo'     => get_the_title( $beneficio ),
			'hora'       => current_time( 'H:i' ),
		], 200 );
	}
}

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CAV_Usos {

	/** Limite de consultas por operador por hora. */
	const RATE_LIMIT = 120;

	public static function ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return substr( $ip, 0, 45 );
	}

	public static function registrar_consulta( $operador_id, $cpf, $resultado ) {
		global $wpdb;
		$wpdb->insert( CAV_Install::tabela_consultas(), [
			'operador_id' => (int) $operador_id,
			'cpf_hash'    => CAV_CPF::hash( $cpf ),
			'resultado'   => substr( $resultado, 0, 20 ),
			'criado_em'   => current_time( 'mysql' ),
			'ip'          => self::ip(),
		] );
	}

	/** true se o operador ainda pode consultar nesta hora. */
	public static function dentro_do_limite( $operador_id ) {
		$chave = 'cav_rl_' . (int) $operador_id . '_' . gmdate( 'YmdH' );
		$n     = (int) get_transient( $chave );

		if ( $n >= self::RATE_LIMIT ) {
			return false;
		}

		set_transient( $chave, $n + 1, HOUR_IN_SECONDS );
		return true;
	}

	/** Início do período de limite, em Y-m-d H:i:s local. */
	private static function inicio_periodo( $periodo ) {
		switch ( $periodo ) {
			case 'dia':
				return current_time( 'Y-m-d' ) . ' 00:00:00';
			case 'semana':
				$ts = current_time( 'timestamp' );
				$dow = (int) wp_date( 'N', $ts ); // 1 = segunda
				return wp_date( 'Y-m-d', $ts - ( ( $dow - 1 ) * DAY_IN_SECONDS ) ) . ' 00:00:00';
			case 'mes':
				return current_time( 'Y-m' ) . '-01 00:00:00';
			default:
				return null;
		}
	}

	/**
	 * O membro pode usar este benefício agora?
	 *
	 * @return array{pode:bool, motivo:string}
	 */
	public static function pode_usar( $membro_id, $beneficio_id ) {
		$periodo = get_post_meta( $beneficio_id, CAV_Parceiro::META_BEN_LIMITE, true ) ?: 'nenhum';

		if ( 'nenhum' === $periodo ) {
			return [ 'pode' => true, 'motivo' => '' ];
		}

		$inicio = self::inicio_periodo( $periodo );
		if ( ! $inicio ) {
			return [ 'pode' => true, 'motivo' => '' ];
		}

		global $wpdb;
		$tabela = CAV_Install::tabela_usos();

		$n = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$tabela} WHERE membro_id = %d AND beneficio_id = %d AND criado_em >= %s",
			$membro_id,
			$beneficio_id,
			$inicio
		) );

		if ( $n > 0 ) {
			$rotulo = [ 'dia' => 'hoje', 'semana' => 'esta semana', 'mes' => 'este mês' ];
			return [ 'pode' => false, 'motivo' => 'Já usado ' . ( $rotulo[ $periodo ] ?? '' ) ];
		}

		return [ 'pode' => true, 'motivo' => '' ];
	}

	public static function registrar_uso( $membro_id, $beneficio_id, $parceiro_id, $operador_id ) {
		global $wpdb;

		$ok = $wpdb->insert( CAV_Install::tabela_usos(), [
			'membro_id'    => (int) $membro_id,
			'beneficio_id' => (int) $beneficio_id,
			'parceiro_id'  => (int) $parceiro_id,
			'operador_id'  => (int) $operador_id,
			'criado_em'    => current_time( 'mysql' ),
			'ip'           => self::ip(),
		] );

		if ( ! $ok ) {
			return new WP_Error( 'cav_falha_registro', 'Não foi possível registrar o uso.' );
		}

		$id = (int) $wpdb->insert_id;
		do_action( 'cav_uso_registrado', $id, $membro_id, $beneficio_id, $parceiro_id );
		return $id;
	}

	/** Usos de um membro, mais recentes primeiro. */
	public static function do_membro( $membro_id, $limite = 50 ) {
		global $wpdb;
		$tabela = CAV_Install::tabela_usos();
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$tabela} WHERE membro_id = %d ORDER BY criado_em DESC LIMIT %d",
			$membro_id,
			$limite
		) );
	}

	/** Usos de um parceiro, mais recentes primeiro. */
	public static function do_parceiro( $parceiro_id, $limite = 100 ) {
		global $wpdb;
		$tabela = CAV_Install::tabela_usos();
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$tabela} WHERE parceiro_id = %d ORDER BY criado_em DESC LIMIT %d",
			$parceiro_id,
			$limite
		) );
	}

	/** Contadores do painel do parceiro. */
	public static function resumo_parceiro( $parceiro_id ) {
		global $wpdb;
		$tabela = CAV_Install::tabela_usos();

		$hoje = current_time( 'Y-m-d' ) . ' 00:00:00';
		$mes  = current_time( 'Y-m' ) . '-01 00:00:00';

		return [
			'hoje'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tabela} WHERE parceiro_id = %d AND criado_em >= %s", $parceiro_id, $hoje ) ),
			'mes'     => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tabela} WHERE parceiro_id = %d AND criado_em >= %s", $parceiro_id, $mes ) ),
			'total'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tabela} WHERE parceiro_id = %d", $parceiro_id ) ),
			'membros' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT membro_id) FROM {$tabela} WHERE parceiro_id = %d", $parceiro_id ) ),
		];
	}
}

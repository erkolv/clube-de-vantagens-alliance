<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CAV_Sorteio {

	public static function tabela() {
		global $wpdb;
		return $wpdb->prefix . 'cav_inscricoes';
	}

	public static function contar( $sorteio_id ) {
		global $wpdb;
		$t = self::tabela();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE sorteio_id = %d", $sorteio_id ) );
	}

	public static function inscrito( $sorteio_id, $membro_id ) {
		global $wpdb;
		$t = self::tabela();
		return (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$t} WHERE sorteio_id = %d AND membro_id = %d",
			$sorteio_id,
			$membro_id
		) );
	}

	public static function inscrever( $sorteio_id, $membro_id ) {
		global $wpdb;

		if ( self::inscrito( $sorteio_id, $membro_id ) ) {
			return new WP_Error( 'cav_ja_inscrito', 'Você já está participando deste sorteio.' );
		}

		$ok = $wpdb->insert( self::tabela(), [
			'sorteio_id' => (int) $sorteio_id,
			'membro_id'  => (int) $membro_id,
			'criado_em'  => current_time( 'mysql' ),
			'ip'         => CAV_Usos::ip(),
		] );

		if ( ! $ok ) {
			return new WP_Error( 'cav_falha_inscricao', 'Não foi possível registrar sua participação.' );
		}

		do_action( 'cav_sorteio_inscrito', $sorteio_id, $membro_id );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Sorteia entre os inscritos que ainda estão com a matrícula ativa.
	 * Usa random_int, não rand — a apuração precisa ser defensável.
	 */
	public static function sortear( $sorteio_id ) {
		global $wpdb;
		$t = self::tabela();

		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT membro_id FROM {$t} WHERE sorteio_id = %d", $sorteio_id ) );
		$ids = array_values( array_filter( $ids, function ( $id ) {
			return CAV_Acesso::e_membro_ativo( (int) $id );
		} ) );

		if ( ! $ids ) {
			return 0;
		}

		return (int) $ids[ random_int( 0, count( $ids ) - 1 ) ];
	}

	public static function inscritos( $sorteio_id, $limite = 500 ) {
		global $wpdb;
		$t = self::tabela();
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$t} WHERE sorteio_id = %d ORDER BY criado_em ASC LIMIT %d",
			$sorteio_id,
			$limite
		) );
	}

	/** Sorteios em que o membro se inscreveu. */
	public static function do_membro( $membro_id ) {
		global $wpdb;
		$t = self::tabela();
		return $wpdb->get_col( $wpdb->prepare( "SELECT sorteio_id FROM {$t} WHERE membro_id = %d", $membro_id ) );
	}
}

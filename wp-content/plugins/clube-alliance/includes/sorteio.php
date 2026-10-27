<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CAV_Sorteio {

	public static function tabela() {
		global $wpdb;
		return $wpdb->prefix . 'cav_inscricoes';
	}

	/**
	 * Quem participa dos sorteios: todo membro com a matrícula ativa na hora. Não existe inscrição,
	 * não precisa clicar em nada. Quem está bloqueado, aguardando pagamento ou com a matrícula vencida fica de fora.
	 */
	public static function participantes() {
		$ids = get_users( [ 'role' => 'cav_membro', 'fields' => 'ID', 'number' => 20000 ] );
		return array_values( array_filter( array_map( 'intval', $ids ), function ( $id ) {
			return CAV_Acesso::e_membro_ativo( $id );
		} ) );
	}

	public static function contar() {
		return count( self::participantes() );
	}

	/**
	 * Sorteia entre todos os membros com a matrícula ativa.
	 * Usa random_int, não rand — a apuração precisa ser defensável.
	 */
	public static function sortear( $sorteio_id = 0 ) {
		$ids = self::participantes();

		if ( ! $ids ) {
			return 0;
		}

		return (int) $ids[ random_int( 0, count( $ids ) - 1 ) ];
	}

	/** "Rafael M.": nome e inicial do sobrenome. É como o ganhador aparece para os outros alunos. */
	public static function nome_publico( $user_id ) {
		$user = get_userdata( (int) $user_id );
		if ( ! $user ) {
			return 'Aluno';
		}

		$partes = preg_split( '/\s+/', trim( $user->display_name ) );
		$nome   = $partes ? $partes[0] : 'Aluno';

		if ( count( $partes ) > 1 ) {
			$nome .= ' ' . mb_strtoupper( mb_substr( end( $partes ), 0, 1 ) ) . '.';
		}

		return $nome;
	}

	/** Sorteios já apurados, o mais recente primeiro. */
	public static function apurados( $qtd = 6 ) {
		return get_posts( [
			'post_type'      => 'cav_sorteio',
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, (int) $qtd ),
			'meta_key'       => CAV_Conteudo::META_APURADO,
			'orderby'        => 'meta_value',
			'order'          => 'DESC',
			'meta_query'     => [ [ 'key' => CAV_Conteudo::META_GANHADOR, 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ] ],
		] );
	}

	/** Sorteios que o membro ganhou, o mais recente primeiro. */
	public static function ganhos_do_membro( $membro_id, $qtd = 5 ) {
		if ( ! $membro_id ) {
			return [];
		}
		return get_posts( [
			'post_type'      => 'cav_sorteio',
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, (int) $qtd ),
			'meta_key'       => CAV_Conteudo::META_APURADO,
			'orderby'        => 'meta_value',
			'order'          => 'DESC',
			'meta_query'     => [ [ 'key' => CAV_Conteudo::META_GANHADOR, 'value' => (int) $membro_id, 'type' => 'NUMERIC' ] ],
		] );
	}

	/** Data da apuração, "d/m/Y", ou ''. */
	public static function data_apuracao( $sorteio_id ) {
		$quando = get_post_meta( $sorteio_id, CAV_Conteudo::META_APURADO, true );
		return $quando ? mysql2date( 'd/m/Y', $quando ) : '';
	}
}

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

	/** Meta do sorteio com quem foi tirado da lista na tela de sorteio ao vivo (testes, equipe...). */
	const META_EXCLUIDOS = '_cav_excluidos';

	/** Quem foi tirado deste sorteio. */
	public static function excluidos( $sorteio_id ) {
		$ids = get_post_meta( (int) $sorteio_id, self::META_EXCLUIDOS, true );
		return is_array( $ids ) ? array_map( 'intval', $ids ) : [];
	}

	/**
	 * Quem concorre neste sorteio: membros ativos, menos quem foi tirado da lista
	 * e menos quem já ganhou este sorteio antes de ele ser refeito.
	 */
	public static function participantes_do_sorteio( $sorteio_id ) {
		$fora = self::excluidos( $sorteio_id );
		$hist = get_post_meta( (int) $sorteio_id, '_cav_sorteio_refeito', true );
		if ( is_array( $hist ) ) {
			foreach ( $hist as $h ) {
				$fora[] = (int) $h['ganhador'];
			}
		}
		return array_values( array_diff( self::participantes(), $fora ) );
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

	/*
	 * Confirmação do resultado.
	 * Sortear grava o ganhador como "aguardando confirmação": ninguém é avisado e nada aparece
	 * para os alunos. Só depois de "Confirmar e publicar" o ganhador recebe o e-mail e o
	 * resultado aparece no site. Antes disso dá para sortear de novo (fica registrado no histórico).
	 */
	const META_PENDENTE   = '_cav_aguardando_confirmacao';
	const META_CONFIRMADO = '_cav_confirmado'; // [ 'em' => mysql, 'por' => user_id ]
	const META_HISTORICO  = '_cav_sorteio_refeito';

	public static function pendente( $sorteio_id ) {
		return '1' === (string) get_post_meta( (int) $sorteio_id, self::META_PENDENTE, true );
	}

	/** Ganhador que pode aparecer para os alunos: 0 enquanto não foi confirmado. */
	public static function ganhador_publico( $sorteio_id ) {
		return self::pendente( $sorteio_id ) ? 0 : (int) get_post_meta( (int) $sorteio_id, CAV_Conteudo::META_GANHADOR, true );
	}

	/** Sorteia entre $participantes e grava como "aguardando confirmação". Devolve o ganhador. */
	public static function registrar( $sorteio_id, array $participantes ) {
		$participantes = array_values( $participantes );
		if ( ! $participantes ) {
			return 0;
		}
		$ganhador = (int) $participantes[ random_int( 0, count( $participantes ) - 1 ) ];
		update_post_meta( $sorteio_id, CAV_Conteudo::META_GANHADOR, $ganhador );
		update_post_meta( $sorteio_id, '_cav_participantes_total', count( $participantes ) );
		update_post_meta( $sorteio_id, CAV_Conteudo::META_APURADO, current_time( 'mysql' ) );
		update_post_meta( $sorteio_id, self::META_PENDENTE, '1' );
		delete_post_meta( $sorteio_id, self::META_CONFIRMADO );
		return $ganhador;
	}

	/** Confirma o resultado: avisa o ganhador e publica para os alunos. */
	public static function confirmar( $sorteio_id, $por = 0 ) {
		$ganhador = (int) get_post_meta( $sorteio_id, CAV_Conteudo::META_GANHADOR, true );
		if ( ! $ganhador || ! self::pendente( $sorteio_id ) ) {
			return false;
		}
		delete_post_meta( $sorteio_id, self::META_PENDENTE );
		update_post_meta( $sorteio_id, self::META_CONFIRMADO, [ 'em' => current_time( 'mysql' ), 'por' => (int) $por ] );
		CAV_Emails::sorteio_ganhador( $sorteio_id, $ganhador );
		do_action( 'cav_sorteio_apurado', $sorteio_id, $ganhador );
		return true;
	}

	/**
	 * Sorteia de novo: o ganhador atual vai para o histórico (com o motivo) e não concorre mais.
	 * Devolve o novo ganhador, ou 0 se não sobrou ninguém (nesse caso nada muda).
	 */
	public static function sortear_de_novo( $sorteio_id, $motivo, $por = 0 ) {
		$anterior = (int) get_post_meta( $sorteio_id, CAV_Conteudo::META_GANHADOR, true );
		if ( ! $anterior ) {
			return 0;
		}
		$hist   = get_post_meta( $sorteio_id, self::META_HISTORICO, true );
		$hist   = is_array( $hist ) ? $hist : [];
		$novo   = [
			'ganhador'   => $anterior,
			'apurado_em' => (string) get_post_meta( $sorteio_id, CAV_Conteudo::META_APURADO, true ),
			'total'      => (int) get_post_meta( $sorteio_id, '_cav_participantes_total', true ),
			'motivo'     => $motivo,
			'por'        => (int) $por,
			'em'         => current_time( 'mysql' ),
			'confirmado' => ! self::pendente( $sorteio_id ),
		];
		$fora          = array_merge( self::excluidos( $sorteio_id ), array_map( static function ( $h ) { return (int) $h['ganhador']; }, $hist ), [ $anterior ] );
		$participantes = array_values( array_diff( self::participantes(), $fora ) );
		if ( ! $participantes ) {
			return 0;
		}
		$hist[] = $novo;
		update_post_meta( $sorteio_id, self::META_HISTORICO, $hist );
		$g = self::registrar( $sorteio_id, $participantes );
		do_action( 'cav_sorteio_refeito', $sorteio_id, $g, $anterior, $motivo );
		return $g;
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
			'meta_query'     => [
				[ 'key' => CAV_Conteudo::META_GANHADOR, 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ],
				[ 'key' => self::META_PENDENTE, 'compare' => 'NOT EXISTS' ],
			],
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
			'meta_query'     => [
				[ 'key' => CAV_Conteudo::META_GANHADOR, 'value' => (int) $membro_id, 'type' => 'NUMERIC' ],
				[ 'key' => self::META_PENDENTE, 'compare' => 'NOT EXISTS' ],
			],
		] );
	}

	/** Data da apuração, "d/m/Y", ou ''. */
	public static function data_apuracao( $sorteio_id ) {
		$quando = get_post_meta( $sorteio_id, CAV_Conteudo::META_APURADO, true );
		return $quando ? mysql2date( 'd/m/Y', $quando ) : '';
	}
}

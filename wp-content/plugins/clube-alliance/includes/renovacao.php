<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renovação do clube.
 *
 * O clube não é assinatura: o aluno paga uma vez e o acesso vale o prazo (hoje 12 meses).
 * Aqui ficam três coisas:
 *  - os lembretes por e-mail: 30 dias antes, 7 dias antes e no dia seguinte ao vencimento,
 *    com o link de pagamento; e um resumo semanal para a equipe com quem está vencendo;
 *  - o aviso "vamos renovar?" na área do aluno;
 *  - o botão "Renovou" em Aprovações → Participantes, que joga a validade para mais um prazo.
 */
class CAV_Renovacao {

	const CRON      = 'cav_renovacao_diaria';
	const META_AVISO = 'cav_aviso_renovacao';   // [ 'validade' => Y-m-d, 'enviados' => [ '30', '7', 'venceu' ] ]
	const META_HIST  = 'cav_renovacoes';        // lista de [ em, por, de, ate ]
	const OPT_RESUMO = 'cav_renovacao_resumo';  // semana (o-W) do último resumo mandado para a equipe
	const DIAS_AVISO = 30;                      // a partir de quantos dias antes o aviso aparece na área do aluno

	public static function init() {
		add_action( self::CRON, [ __CLASS__, 'rodar' ] );
		add_action( 'admin_post_cav_renovar', [ __CLASS__, 'renovar_acao' ] );

		add_action( 'init', static function () {
			if ( ! wp_next_scheduled( self::CRON ) ) {
				// Todo dia às 9h no horário do site.
				$prox = new DateTimeImmutable( 'tomorrow 09:00', wp_timezone() );
				wp_schedule_event( $prox->getTimestamp(), 'daily', self::CRON );
			}
		} );
	}

	public static function desativar() {
		wp_clear_scheduled_hook( self::CRON );
	}

	/* ------------------------------ Contas ------------------------------ */

	/** Dias até a validade (0 = vence hoje, negativo = já venceu). null sem validade. */
	public static function dias_para_vencer( $user_id ) {
		$val = (string) CAV_Membro::validade( $user_id );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $val ) ) {
			return null;
		}
		$tz   = wp_timezone();
		$hoje = new DateTimeImmutable( current_time( 'Y-m-d' ), $tz );
		$fim  = new DateTimeImmutable( $val, $tz );
		return (int) $hoje->diff( $fim )->format( '%r%a' );
	}

	/** Nova validade ao renovar: um prazo a partir do vencimento atual, ou de hoje se já venceu. */
	public static function nova_validade( $user_id ) {
		$val  = (string) CAV_Membro::validade( $user_id );
		$hoje = current_time( 'Y-m-d' );
		$base = ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $val ) && $val >= $hoje ) ? $val : $hoje;
		return CAV_Config::somar_prazo( $base );
	}

	/** Só participante de verdade: aprovado, não bloqueado. */
	private static function conta( $user_id ) {
		if ( get_user_meta( $user_id, CAV_Membro::META_BLOQUEADO, true ) === '1' ) {
			return false;
		}
		$st = (string) get_user_meta( $user_id, 'cav_status', true );
		return ! in_array( $st, [ 'pendente', 'recusado' ], true );
	}

	private static function data_br( $ymd ) {
		return mysql2date( 'd/m/Y', $ymd . ' 00:00:00' );
	}

	/* ---------------------------- Lembretes ---------------------------- */

	/** Roda uma vez por dia. */
	public static function rodar() {
		$users = get_users( [
			'role'     => 'cav_membro',
			'number'   => 20000,
			'meta_key' => CAV_Membro::META_VALIDADE, // phpcs:ignore WordPress.DB.SlowDBQuery
			'fields'   => [ 'ID' ],
		] );

		$vencendo = [];
		$vencidos = [];

		foreach ( $users as $u ) {
			$id   = (int) $u->ID;
			$dias = self::dias_para_vencer( $id );
			if ( null === $dias || ! self::conta( $id ) ) {
				continue;
			}

			$qual = null;
			if ( $dias >= 8 && $dias <= 30 ) {
				$qual = '30';
			} elseif ( $dias >= 0 && $dias <= 7 ) {
				$qual = '7';
			} elseif ( $dias <= -1 && $dias >= -7 ) {
				$qual = 'venceu';
			}

			if ( $dias >= 0 && $dias <= 30 ) {
				$vencendo[] = [ $id, $dias ];
			} elseif ( $dias <= -1 && $dias >= -7 ) {
				$vencidos[] = [ $id, $dias ];
			}

			if ( null === $qual ) {
				continue;
			}

			$val   = (string) CAV_Membro::validade( $id );
			$aviso = get_user_meta( $id, self::META_AVISO, true );
			if ( ! is_array( $aviso ) || ( $aviso['validade'] ?? '' ) !== $val ) {
				$aviso = [ 'validade' => $val, 'enviados' => [] ];
			}
			// Quem já recebeu o de 7 dias não recebe o de 30 depois (validade mexida para trás, por exemplo).
			$ja = (array) $aviso['enviados'];
			if ( in_array( $qual, $ja, true ) || ( '30' === $qual && in_array( '7', $ja, true ) ) ) {
				continue;
			}

			if ( self::lembrete( $id, $qual, $dias ) ) {
				$aviso['enviados'][] = $qual;
				update_user_meta( $id, self::META_AVISO, $aviso );
			}
		}

		// Resumo para a equipe, toda segunda-feira (ou no primeiro dia da semana em que o agendamento rodar).
		$semana = wp_date( 'o-W' );
		if ( get_option( self::OPT_RESUMO ) !== $semana ) {
			update_option( self::OPT_RESUMO, $semana, false );
			if ( $vencendo || $vencidos ) {
				self::resumo_equipe( $vencendo, $vencidos );
			}
		}
	}

	/** Manda um lembrete. $qual: '30', '7' ou 'venceu'. */
	public static function lembrete( $user_id, $qual, $dias = null ) {
		$user = get_userdata( $user_id );
		if ( ! $user || ! is_email( $user->user_email ) ) {
			return false;
		}

		$tipo  = CAV_Solicitacoes::tipo( $user_id );
		$link  = CAV_Config::link_pagamento( $tipo );
		$dias  = null === $dias ? self::dias_para_vencer( $user_id ) : $dias;
		$chave = [ '30' => 'renovacao_30', '7' => 'renovacao_7', 'venceu' => 'renovacao_venceu' ][ $qual ] ?? '';
		if ( ! $chave ) {
			return false;
		}

		$m = CAV_Modelos::montar( $chave, [
			'nome'     => CAV_Membro::primeiro_nome( $user ),
			'validade' => self::data_br( CAV_Membro::validade( $user_id ) ),
			'dias'     => null === $dias ? '' : ( 1 === abs( $dias ) ? '1 dia' : abs( $dias ) . ' dias' ),
			'valor'    => CAV_Config::formatar( CAV_Config::valor_para( $tipo ) ),
			'prazo'    => CAV_Config::prazo_formatado(),
		], (string) $link );

		return $m ? wp_mail( $user->user_email, $m['assunto'], $m['html'], CAV_Emails::cabecalhos() ) : false;
	}

	/** Lista semanal para a recepção cobrar pessoalmente. */
	private static function resumo_equipe( array $vencendo, array $vencidos ) {
		$para = CAV_SMTP::equipe();
		if ( ! $para ) {
			return false;
		}

		$linha = static function ( $par ) {
			list( $id, $dias ) = $par;
			$u   = get_userdata( $id );
			$zap = (string) get_user_meta( $id, CAV_Solicitacoes::META_WHATSAPP, true );
			$val = self::data_br( CAV_Membro::validade( $id ) );
			return '- ' . ( $u ? $u->display_name : '#' . $id ) . ' · ' . $val . ( $zap ? ' · ' . $zap : '' );
		};
		usort( $vencendo, static function ( $a, $b ) { return $a[1] <=> $b[1]; } );
		usort( $vencidos, static function ( $a, $b ) { return $b[1] <=> $a[1]; } );

		$m = CAV_Modelos::montar( 'equipe_vencimentos', [
			'vencendo' => implode( "\n", array_map( $linha, $vencendo ) ),
			'vencidos' => implode( "\n", array_map( $linha, $vencidos ) ),
			'total'    => (string) ( count( $vencendo ) + count( $vencidos ) ),
		], admin_url( 'admin.php?page=cav-alunos' ) );

		return $m ? wp_mail( $para, $m['assunto'], $m['html'], CAV_Emails::cabecalhos() ) : false;
	}

	/* -------------------------- Área do aluno -------------------------- */

	/** Aviso "vamos renovar?" para o topo da área do aluno. Vazio quando falta mais de 30 dias. */
	public static function aviso_area( WP_User $user ) {
		$dias = self::dias_para_vencer( $user->ID );
		if ( null === $dias || $dias > self::DIAS_AVISO || $dias < -120 || ! self::conta( $user->ID ) ) {
			return '';
		}

		$tipo  = CAV_Solicitacoes::tipo( $user->ID );
		$link  = CAV_Config::link_pagamento( $tipo );
		$valor = CAV_Config::formatar( CAV_Config::valor_para( $tipo ) );
		$data  = self::data_br( CAV_Membro::validade( $user->ID ) );

		if ( $dias < 0 ) {
			$titulo = 'Seu acesso venceu em ' . $data . '. Bora voltar?';
			$texto  = 'Renove o clube para continuar com os descontos nos parceiros, a entrada VIP nos seminários e os sorteios.';
		} elseif ( 0 === $dias ) {
			$titulo = 'Seu acesso vence hoje. Vamos renovar?';
			$texto  = 'Renove agora e continue com tudo sem parar um dia.';
		} else {
			$titulo = 'Seu acesso vence em ' . ( 1 === $dias ? '1 dia' : $dias . ' dias' ) . ' (' . $data . '). Vamos renovar?';
			$texto  = 'Renovando antes, os ' . CAV_Config::prazo_formatado() . ' novos começam a contar só depois do vencimento. Você não perde nenhum dia.';
		}

		ob_start();
		?>
		<div class="cav-renovar" role="status">
			<div class="cav-renovar__texto">
				<strong><?php echo esc_html( $titulo ); ?></strong>
				<span><?php echo esc_html( $texto ); ?> <?php echo esc_html( $valor . ' por ' . CAV_Config::prazo_formatado() . ', em até 12x no cartão.' ); ?></span>
			</div>
			<?php if ( $link ) : ?>
				<a class="cav-renovar__botao" href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener">Renovar agora</a>
			<?php else : ?>
				<span class="cav-renovar__nota">Para renovar, fale com a recepção da Alliance.</span>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ------------------------- Botão "Renovou" ------------------------- */

	/** Botão para a tela de participantes. */
	public static function botao( $user_id, $volta ) {
		$nova = self::nova_validade( $user_id );
		$u    = get_userdata( $user_id );
		ob_start();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:6px">
			<input type="hidden" name="action" value="cav_renovar">
			<input type="hidden" name="user_id" value="<?php echo (int) $user_id; ?>">
			<input type="hidden" name="volta" value="<?php echo esc_attr( $volta ); ?>">
			<?php wp_nonce_field( 'cav_renovar_' . $user_id ); ?>
			<button class="button button-small"
			        onclick="return confirm('<?php echo esc_js( ( $u ? $u->display_name : 'Participante' ) . ' renovou? O acesso passa a valer até ' . self::data_br( $nova ) . '.' ); ?>');">Renovou</button>
		</form>
		<?php
		return ob_get_clean();
	}

	/** Joga a validade para mais um prazo e avisa o aluno. Recepção e administração. */
	public static function renovar_acao() {
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;

		if ( ! current_user_can( 'cav_aprovar' ) ) {
			wp_die( 'Sem permissão.', '', [ 'response' => 403 ] );
		}
		check_admin_referer( 'cav_renovar_' . $user_id );

		$padrao = admin_url( 'admin.php?page=cav-alunos' );
		$volta  = isset( $_POST['volta'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['volta'] ) ), $padrao ) : $padrao;
		$volta  = remove_query_arg( [ 'cav_msg', 'cav_ate' ], $volta );
		$u      = get_userdata( $user_id );

		// Só quem já foi aprovado (ativo ou vencido). Pedido novo segue pelas Aprovações.
		if ( ! $u || [ 'cav_membro' ] !== array_values( (array) $u->roles ) || ! in_array( CAV_Membro::checar( $u )['motivo'], [ 'ok', 'expirado' ], true ) ) {
			wp_safe_redirect( add_query_arg( 'cav_msg', 'invalido', $volta ) );
			exit;
		}

		$ate = self::renovar( $user_id, get_current_user_id() );
		wp_safe_redirect( add_query_arg( [ 'cav_msg' => $ate ? 'renovado' : 'invalido', 'cav_ate' => $ate ], $volta ) );
		exit;
	}

	/** Renova e devolve a nova validade (Y-m-d), ou '' se não deu. */
	public static function renovar( $user_id, $por = 0 ) {
		$de  = (string) CAV_Membro::validade( $user_id );
		$ate = self::nova_validade( $user_id );
		if ( ! $ate || ! CAV_Membro::set_validade( $user_id, $ate ) ) {
			return '';
		}

		$hist   = get_user_meta( $user_id, self::META_HIST, true );
		$hist   = is_array( $hist ) ? $hist : [];
		$hist[] = [ 'em' => current_time( 'mysql' ), 'por' => (int) $por, 'de' => $de, 'ate' => $ate ];
		update_user_meta( $user_id, self::META_HIST, array_slice( $hist, -20 ) );
		delete_user_meta( $user_id, self::META_AVISO );

		$user = get_userdata( $user_id );
		if ( $user && is_email( $user->user_email ) ) {
			$m = CAV_Modelos::montar( 'renovacao_confirmada', [
				'nome'     => CAV_Membro::primeiro_nome( $user ),
				'validade' => self::data_br( $ate ),
			], CAV_Area::url( 'area-do-membro' ) );
			if ( $m ) {
				wp_mail( $user->user_email, $m['assunto'], $m['html'], CAV_Emails::cabecalhos() );
			}
		}

		return $ate;
	}

	/** Última renovação, para mostrar na lista. */
	public static function ultima( $user_id ) {
		$hist = get_user_meta( $user_id, self::META_HIST, true );
		return is_array( $hist ) && $hist ? end( $hist ) : null;
	}
}

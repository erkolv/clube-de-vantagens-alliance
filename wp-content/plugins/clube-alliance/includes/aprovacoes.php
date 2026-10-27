<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CAV_Aprovacoes {

	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
		add_action( 'admin_post_cav_aprovar_membro', [ __CLASS__, 'acao_membro' ] );
		add_action( 'admin_post_cav_aprovar_candidatura', [ __CLASS__, 'acao_candidatura' ] );

		// A recepção entra no admin só para isso: nada de barra em outras telas.
		add_filter( 'show_admin_bar', [ __CLASS__, 'esconder_barra' ] );
		add_action( 'admin_init', [ __CLASS__, 'travar_recepcao' ] );
	}

	public static function esconder_barra( $mostrar ) {
		if ( current_user_can( 'cav_aprovar' ) && ! current_user_can( 'edit_posts' ) ) {
			return false;
		}
		return $mostrar;
	}

	/** Recepção só acessa as telas de aprovações e de aniversariantes. */
	public static function travar_recepcao() {
		if ( wp_doing_ajax() || ! is_user_logged_in() ) {
			return;
		}
		if ( ! current_user_can( 'cav_aprovar' ) || current_user_can( 'cav_gerenciar' ) ) {
			return;
		}

		$pagina = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$tela   = isset( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : '';

		if ( 'admin.php' === $tela && in_array( $pagina, [ 'cav-aprovacoes', 'cav-aniversarios', 'cav-alunos' ], true ) ) {
			return;
		}
		if ( in_array( $tela, [ 'profile.php', 'admin-post.php', 'admin-ajax.php' ], true ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=cav-aprovacoes' ) );
		exit;
	}

	public static function menu() {
		add_menu_page(
			'Aprovações do clube',
			'Aprovações',
			'cav_aprovar',
			'cav-aprovacoes',
			[ __CLASS__, 'tela' ],
			'dashicons-yes-alt',
			25
		);
	}

	private static function aviso() {
		if ( empty( $_GET['cav_msg'] ) ) {
			return;
		}

		$codigo = sanitize_text_field( wp_unslash( $_GET['cav_msg'] ) );
		$mapa   = [
			'aprovado'  => [ 'success', 'Acesso liberado e e-mail enviado.' ],
			'recusado'  => [ 'warning', 'Pedido recusado e e-mail enviado.' ],
			'reenviado' => [ 'success', 'Link de pagamento reenviado por e-mail.' ],
			'pagamento' => [ 'error', 'Informe o código do pagamento (o número da transação no Pagar.me ou do comprovante) antes de aprovar.' ],
			'pag_usado' => [ 'error', 'Este código de pagamento já foi usado para liberar outro participante. Confira o comprovante.' ],
			'semlink'   => [ 'error', 'Ainda não há link de pagamento configurado. Cole os links em Clube → Configurações.' ],
			'parceiro'  => [ 'success', 'Estabelecimento aprovado. E-mail com os dados de acesso enviado.' ],
			'validade'  => [ 'error', 'Informe a data de validade antes de aprovar.' ],
		];

		$m = $mapa[ $codigo ] ?? null;

		if ( ! $m ) {
			$m = [ 'error', 'Não foi possível concluir: ' . $codigo ];
		}

		printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $m[0] ), esc_html( $m[1] ) );
	}

	public static function tela() {
		if ( ! current_user_can( 'cav_aprovar' ) ) {
			wp_die( 'Sem permissão.' );
		}

		$membros = CAV_Solicitacoes::pendentes_membros();
		$cands   = CAV_Solicitacoes::pendentes_candidaturas();
		$padrao  = CAV_Config::somar_prazo( wp_date( 'Y-m-d' ) );
		?>
		<div class="wrap">
			<h1>Aprovações do clube</h1>
			<?php self::aviso(); ?>

			<h2>Participantes aguardando <span class="count">(<?php echo count( $membros ); ?>)</span></h2>
			<p class="description">
				Aprove só depois de confirmar o pagamento, na recepção ou pelo link (aluno: <?php echo esc_html( CAV_Config::valor_formatado() ); ?>; não aluno: <?php echo esc_html( CAV_Config::valor_nao_aluno_formatado() ); ?>; por <?php echo esc_html( CAV_Config::prazo_formatado() ); ?>).
				O botão "WhatsApp" abre a conversa com o link de pagamento já escrito; é só enviar.
				A validade já vem com <?php echo esc_html( CAV_Config::prazo_formatado() ); ?> a partir de hoje; mude a data se o pagamento foi em outro dia.
			</p>

			<?php if ( ! $membros ) : ?>
				<p>Nenhum pedido na fila.</p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr><th>Aluno</th><th>CPF</th><th>Contato</th><th style="width:340px">Ação</th></tr>
					</thead>
					<tbody>
					<?php foreach ( $membros as $u ) :
						$cpf   = CAV_Membro::cpf_do_usuario( $u->ID );
						$zap   = get_user_meta( $u->ID, CAV_Solicitacoes::META_WHATSAPP, true );
						$plano = CAV_Solicitacoes::rotulo_plano( get_user_meta( $u->ID, CAV_Solicitacoes::META_PLANO, true ) );
						$nasc  = get_user_meta( $u->ID, CAV_Solicitacoes::META_NASC, true );
						$cep   = CAV_Solicitacoes::formatar_cep( get_user_meta( $u->ID, CAV_Solicitacoes::META_CEP, true ) );
						$idade = $nasc ? CAV_Solicitacoes::idade( $nasc ) : null;
						$resp  = get_user_meta( $u->ID, CAV_Solicitacoes::META_RESP, true );
						$turma = get_user_meta( $u->ID, CAV_Solicitacoes::META_TURMA, true ); // pedidos antigos
						$aceite = get_user_meta( $u->ID, CAV_Solicitacoes::META_ACEITE, true );
						$tipo   = CAV_Solicitacoes::tipo( $u->ID );
						$texto_pg = CAV_Emails::texto_pagamento( $u );
						$zap_num  = self::numero_whatsapp( $zap );
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( $u->display_name ); ?></strong>
								<br><span class="description"><?php echo esc_html( CAV_Solicitacoes::rotulo_tipo( $tipo ) ); ?>: <?php echo esc_html( CAV_Config::formatar( CAV_Config::valor_para( $tipo ) ) ); ?></span>
								<?php if ( $plano ) : ?><br><span class="description">Plano: <?php echo esc_html( $plano ); ?></span><?php endif; ?>
								<?php if ( null !== $idade ) : ?><br><span class="description"><?php echo (int) $idade; ?> anos</span><?php endif; ?>
								<?php if ( is_array( $resp ) && ! empty( $resp['nome'] ) ) : ?>
									<br><span class="description" style="color:#996800">Menor de idade. Responsável: <?php echo esc_html( $resp['nome'] ); ?> (CPF <?php echo esc_html( CAV_CPF::formatar( CAV_Cripto::decifrar( $resp['cpf'] ) ) ); ?>), autorizou em <?php echo esc_html( mysql2date( 'd/m/Y', $resp['em'] ) ); ?></span>
								<?php endif; ?>
								<?php if ( $turma ) : ?><br><span class="description">Turma: <?php echo esc_html( $turma ); ?></span><?php endif; ?>
								<?php if ( is_array( $aceite ) && ! empty( $aceite['valor'] ) ) : ?>
									<br><span class="description" style="color:#1a7f37">
										Aceitou <?php echo esc_html( CAV_Config::formatar( $aceite['valor'] ) ); ?>
										em <?php echo esc_html( mysql2date( 'd/m/Y H:i', $aceite['em'] ) ); ?>
									</span>
								<?php else : ?>
									<br><span class="description" style="color:#b32d2e">Sem aceite do valor registrado (pedido antigo)</span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( CAV_CPF::formatar( $cpf ) ); ?></td>
							<td>
								<?php echo esc_html( $u->user_email ); ?>
								<?php if ( $zap ) : ?><br><span class="description"><?php echo esc_html( $zap ); ?></span><?php endif; ?>
								<?php if ( $cep ) : ?><br><span class="description">CEP <?php echo esc_html( $cep ); ?></span><?php endif; ?>
							</td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
									<input type="hidden" name="action" value="cav_aprovar_membro">
									<input type="hidden" name="user_id" value="<?php echo esc_attr( $u->ID ); ?>">
									<?php wp_nonce_field( 'cav_aprovar_membro_' . $u->ID ); ?>
									<label style="font-size:12px">Código do pagamento
										<input type="text" name="pagamento" maxlength="60" placeholder="Pagar.me ou comprovante" required style="width:170px">
									</label>
									<label style="font-size:12px">Válido até
										<input type="date" name="validade" value="<?php echo esc_attr( $padrao ); ?>" required>
									</label>
									<button class="button button-primary" name="decisao" value="aprovar">Aprovar</button>
									<button class="button" name="decisao" value="recusar" formnovalidate
									        onclick="return confirm('Recusar este pedido? O aluno recebe um e-mail.');">Recusar</button>
									<?php if ( $texto_pg ) : ?>
										<button class="button" name="decisao" value="reenviar" formnovalidate>Reenviar link</button>
										<?php if ( $zap_num ) : ?>
											<a class="button" target="_blank" rel="noopener noreferrer"
											   href="<?php echo esc_url( 'https://wa.me/' . $zap_num . '?text=' . rawurlencode( $texto_pg ) ); ?>">WhatsApp</a>
										<?php endif; ?>
									<?php endif; ?>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h2 style="margin-top:2.5rem">Estabelecimentos aguardando <span class="count">(<?php echo count( $cands ); ?>)</span></h2>
			<p class="description">Ao aprovar, o sistema cria o estabelecimento e o benefício como rascunho, gera o login e envia o e-mail de acesso.</p>

			<?php if ( ! $cands ) : ?>
				<p>Nenhuma candidatura na fila.</p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr><th>Estabelecimento</th><th>Responsável</th><th>Benefício proposto</th><th style="width:220px">Ação</th></tr>
					</thead>
					<tbody>
					<?php foreach ( $cands as $c ) :
						$cat = get_post_meta( $c->ID, CAV_Solicitacoes::CAND_CAT, true );
						$end = get_post_meta( $c->ID, CAV_Solicitacoes::CAND_END, true );
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( $c->post_title ); ?></strong>
								<?php if ( $cat ) : ?><br><span class="description"><?php echo esc_html( $cat ); ?></span><?php endif; ?>
								<?php if ( $end ) : ?><br><span class="description"><?php echo esc_html( $end ); ?></span><?php endif; ?>
							</td>
							<td>
								<?php echo esc_html( get_post_meta( $c->ID, CAV_Solicitacoes::CAND_RESP, true ) ); ?><br>
								<span class="description"><?php echo esc_html( get_post_meta( $c->ID, CAV_Solicitacoes::CAND_EMAIL, true ) ); ?></span>
							</td>
							<td><?php echo esc_html( get_post_meta( $c->ID, CAV_Solicitacoes::CAND_BEN, true ) ); ?></td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:6px;flex-wrap:wrap">
									<input type="hidden" name="action" value="cav_aprovar_candidatura">
									<input type="hidden" name="post_id" value="<?php echo esc_attr( $c->ID ); ?>">
									<?php wp_nonce_field( 'cav_aprovar_candidatura_' . $c->ID ); ?>
									<button class="button button-primary" name="decisao" value="aprovar">Aprovar</button>
									<button class="button" name="decisao" value="recusar"
									        onclick="return confirm('Recusar esta candidatura? O responsável recebe um e-mail.');">Recusar</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function volta( $codigo ) {
		wp_safe_redirect( admin_url( 'admin.php?page=cav-aprovacoes&cav_msg=' . rawurlencode( $codigo ) ) );
		exit;
	}

	/** Número no formato do wa.me (só dígitos, com 55). Vazio se não parecer um telefone do Brasil. */
	public static function numero_whatsapp( $texto ) {
		$n = preg_replace( '/\D+/', '', (string) $texto );
		$n = ltrim( $n, '0' );
		if ( strlen( $n ) === 10 || strlen( $n ) === 11 ) {
			$n = '55' . $n;
		}
		return ( 0 === strpos( $n, '55' ) && strlen( $n ) >= 12 && strlen( $n ) <= 13 ) ? $n : '';
	}

	public static function acao_membro() {
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;

		if ( ! current_user_can( 'cav_aprovar' ) ) {
			wp_die( 'Sem permissão.' );
		}
		check_admin_referer( 'cav_aprovar_membro_' . $user_id );

		$decisao = isset( $_POST['decisao'] ) ? sanitize_text_field( wp_unslash( $_POST['decisao'] ) ) : '';

		if ( 'recusar' === $decisao ) {
			CAV_Solicitacoes::recusar_membro( $user_id, 'Não encontramos o pagamento do clube. Fale com a recepção da Alliance.' );
			self::volta( 'recusado' );
		}

		if ( 'reenviar' === $decisao ) {
			$u = get_userdata( $user_id );
			if ( ! $u || ! in_array( 'cav_membro', (array) $u->roles, true ) || CAV_Solicitacoes::STATUS_PENDENTE !== CAV_Solicitacoes::status( $user_id ) ) {
				wp_die( 'Pedido não encontrado.' );
			}
			if ( '' === CAV_Config::link_pagamento( CAV_Solicitacoes::tipo( $user_id ) ) ) {
				self::volta( 'semlink' );
			}
			CAV_Emails::pedido_recebido( $u );
			self::volta( 'reenviado' );
		}

		// O código do pagamento (Pagar.me ou comprovante) é o que liga o acesso ao pagamento. Obrigatório, e não pode se repetir.
		$codigo = isset( $_POST['pagamento'] ) ? strtoupper( preg_replace( '/\s+/', '', sanitize_text_field( wp_unslash( $_POST['pagamento'] ) ) ) ) : '';
		$codigo = substr( $codigo, 0, 60 );
		if ( '' === $codigo ) {
			self::volta( 'pagamento' );
		}
		$outros = get_users( [ 'meta_key' => CAV_Membro::META_PAG_COD, 'meta_value' => $codigo, 'exclude' => [ $user_id ], 'number' => 1, 'fields' => 'ids' ] );
		if ( $outros ) {
			self::volta( 'pag_usado' );
		}

		$validade = isset( $_POST['validade'] ) ? sanitize_text_field( wp_unslash( $_POST['validade'] ) ) : '';
		$res      = CAV_Solicitacoes::aprovar_membro( $user_id, $validade );

		if ( is_wp_error( $res ) ) {
			self::volta( 'cav_validade' === $res->get_error_code() ? 'validade' : $res->get_error_message() );
		}

		$aceite = get_user_meta( $user_id, CAV_Solicitacoes::META_ACEITE, true );
		update_user_meta( $user_id, CAV_Membro::META_PAG_COD, $codigo );
		update_user_meta( $user_id, CAV_Membro::META_PAGAMENTO, [
			'codigo' => $codigo,
			'em'     => current_time( 'mysql' ),
			'por'    => get_current_user_id(),
			'valor'  => is_array( $aceite ) && isset( $aceite['valor'] ) ? $aceite['valor'] : '',
			'tipo'   => CAV_Solicitacoes::tipo( $user_id ),
		] );

		self::volta( 'aprovado' );
	}

	public static function acao_candidatura() {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! current_user_can( 'cav_aprovar' ) ) {
			wp_die( 'Sem permissão.' );
		}
		check_admin_referer( 'cav_aprovar_candidatura_' . $post_id );

		$decisao = isset( $_POST['decisao'] ) ? sanitize_text_field( wp_unslash( $_POST['decisao'] ) ) : '';

		if ( 'recusar' === $decisao ) {
			CAV_Solicitacoes::recusar_candidatura( $post_id );
			self::volta( 'recusado' );
		}

		$url = get_option( 'cav_url_terminal', '' );
		$res = CAV_Solicitacoes::aprovar_candidatura( $post_id, $url );

		if ( is_wp_error( $res ) ) {
			self::volta( $res->get_error_message() );
		}

		self::volta( 'parceiro' );
	}
}

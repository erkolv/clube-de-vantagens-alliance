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

	/** Recepção só acessa a tela de aprovações. */
	public static function travar_recepcao() {
		if ( wp_doing_ajax() || ! is_user_logged_in() ) {
			return;
		}
		if ( ! current_user_can( 'cav_aprovar' ) || current_user_can( 'cav_gerenciar' ) ) {
			return;
		}

		$pagina = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$tela   = isset( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : '';

		if ( 'admin.php' === $tela && 'cav-aprovacoes' === $pagina ) {
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
		$padrao  = wp_date( 'Y-m-d', strtotime( '+40 days' ) );
		?>
		<div class="wrap">
			<h1>Aprovações do clube</h1>
			<?php self::aviso(); ?>

			<h2>Alunos aguardando <span class="count">(<?php echo count( $membros ); ?>)</span></h2>

			<?php if ( ! $membros ) : ?>
				<p>Nenhum pedido na fila.</p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr><th>Aluno</th><th>CPF</th><th>Contato</th><th style="width:340px">Ação</th></tr>
					</thead>
					<tbody>
					<?php foreach ( $membros as $u ) :
						$cpf   = get_user_meta( $u->ID, CAV_Membro::META_CPF, true );
						$zap   = get_user_meta( $u->ID, CAV_Solicitacoes::META_WHATSAPP, true );
						$turma = get_user_meta( $u->ID, CAV_Solicitacoes::META_TURMA, true );
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( $u->display_name ); ?></strong>
								<?php if ( $turma ) : ?><br><span class="description"><?php echo esc_html( $turma ); ?></span><?php endif; ?>
							</td>
							<td><?php echo esc_html( CAV_CPF::formatar( $cpf ) ); ?></td>
							<td>
								<?php echo esc_html( $u->user_email ); ?>
								<?php if ( $zap ) : ?><br><span class="description"><?php echo esc_html( $zap ); ?></span><?php endif; ?>
							</td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
									<input type="hidden" name="action" value="cav_aprovar_membro">
									<input type="hidden" name="user_id" value="<?php echo esc_attr( $u->ID ); ?>">
									<?php wp_nonce_field( 'cav_aprovar_membro_' . $u->ID ); ?>
									<label style="font-size:12px">Válido até
										<input type="date" name="validade" value="<?php echo esc_attr( $padrao ); ?>" required>
									</label>
									<button class="button button-primary" name="decisao" value="aprovar">Aprovar</button>
									<button class="button" name="decisao" value="recusar"
									        onclick="return confirm('Recusar este pedido? O aluno recebe um e-mail.');">Recusar</button>
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

	public static function acao_membro() {
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;

		if ( ! current_user_can( 'cav_aprovar' ) ) {
			wp_die( 'Sem permissão.' );
		}
		check_admin_referer( 'cav_aprovar_membro_' . $user_id );

		$decisao = isset( $_POST['decisao'] ) ? sanitize_text_field( wp_unslash( $_POST['decisao'] ) ) : '';

		if ( 'recusar' === $decisao ) {
			CAV_Solicitacoes::recusar_membro( $user_id, 'Matrícula não localizada.' );
			self::volta( 'recusado' );
		}

		$validade = isset( $_POST['validade'] ) ? sanitize_text_field( wp_unslash( $_POST['validade'] ) ) : '';
		$res      = CAV_Solicitacoes::aprovar_membro( $user_id, $validade );

		if ( is_wp_error( $res ) ) {
			self::volta( 'cav_validade' === $res->get_error_code() ? 'validade' : $res->get_error_message() );
		}

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

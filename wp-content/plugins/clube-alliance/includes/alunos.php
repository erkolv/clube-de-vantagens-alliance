<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Aprovações → Participantes: todos os participantes do clube (alunos e quem não é aluno), com os dados do cadastro,
 * filtros e busca. A recepção e a administração veem a lista; só a administração baixa a planilha, bloqueia e exclui.
 */
class CAV_Alunos {

	const POR_PAGINA = 50;

	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'menu' ], 10 );
		add_action( 'admin_post_cav_alunos_csv', [ __CLASS__, 'baixar' ] );
		add_action( 'admin_post_cav_participante', [ __CLASS__, 'acao' ] );
	}

	public static function menu() {
		add_submenu_page(
			'cav-aprovacoes',
			'Participantes do clube',
			'Participantes',
			'cav_aprovar',
			'cav-alunos',
			[ __CLASS__, 'tela' ]
		);
	}

	private static function rotulos() {
		return [
			'ok'           => 'Ativo',
			'expirado'     => 'Vencido',
			'pendente'     => 'Aguardando pagamento',
			'recusado'     => 'Recusado',
			'bloqueado'    => 'Bloqueado',
			'sem_validade' => 'Sem validade',
		];
	}

	/** Todos os alunos com os dados já prontos para mostrar. */
	private static function todos() {
		$users = get_users( [ 'role' => 'cav_membro', 'number' => 20000, 'orderby' => 'display_name', 'order' => 'ASC' ] );
		$out   = [];

		foreach ( $users as $u ) {
			$st    = CAV_Membro::checar( $u );
			$nasc  = (string) get_user_meta( $u->ID, CAV_Solicitacoes::META_NASC, true );
			$val   = (string) get_user_meta( $u->ID, CAV_Membro::META_VALIDADE, true );
			$resp  = get_user_meta( $u->ID, CAV_Solicitacoes::META_RESP, true );
			$idade = $nasc ? CAV_Solicitacoes::idade( $nasc ) : null;

			$out[] = [
				'id'       => $u->ID,
				'nome'     => $u->display_name,
				'email'    => $u->user_email,
				'status'   => $st['motivo'],
				'tipo'     => CAV_Solicitacoes::tipo( $u->ID ),
				'zap'      => (string) get_user_meta( $u->ID, CAV_Solicitacoes::META_WHATSAPP, true ),
				'cep'      => (string) get_user_meta( $u->ID, CAV_Solicitacoes::META_CEP, true ),
				'nasc'     => $nasc,
				'idade'    => $idade,
				'validade' => $val,
				'pedido'   => (string) get_user_meta( $u->ID, CAV_Solicitacoes::META_PEDIDO, true ),
				'resp'     => is_array( $resp ) && ! empty( $resp['nome'] ) ? (string) $resp['nome'] : '',
			];
		}

		return $out;
	}

	/** Aplica busca, situação e tipo. */
	private static function filtrar( array $lista, $busca, $situacao, $tipo ) {
		$cpf_busca = CAV_CPF::normalizar( $busca );
		$achado_cpf = 0;
		if ( 11 === strlen( $cpf_busca ) ) {
			$dono       = CAV_CPF::buscar_usuario( $cpf_busca );
			$achado_cpf = $dono ? (int) $dono->ID : 0;
		}
		$q = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $busca ) ) : strtolower( trim( $busca ) );

		return array_values( array_filter( $lista, static function ( $a ) use ( $q, $achado_cpf, $situacao, $tipo ) {
			if ( $situacao && $a['status'] !== $situacao ) {
				return false;
			}
			if ( $tipo && $a['tipo'] !== $tipo ) {
				return false;
			}
			if ( '' !== $q ) {
				if ( $achado_cpf && $a['id'] === $achado_cpf ) {
					return true;
				}
				$nome  = function_exists( 'mb_strtolower' ) ? mb_strtolower( $a['nome'] ) : strtolower( $a['nome'] );
				$email = strtolower( $a['email'] );
				$zap   = preg_replace( '/\D+/', '', $a['zap'] );
				$so_n  = preg_replace( '/\D+/', '', $q );
				return false !== strpos( $nome, $q ) || false !== strpos( $email, $q ) || ( '' !== $so_n && strlen( $so_n ) >= 4 && false !== strpos( $zap, $so_n ) );
			}
			return true;
		} ) );
	}

	private static function data( $ymd ) {
		return preg_match( '/^\d{4}-\d{2}-\d{2}/', (string) $ymd ) ? mysql2date( 'd/m/Y', $ymd . ( strlen( $ymd ) === 10 ? ' 00:00:00' : '' ) ) : '—';
	}

	private static function parametros() {
		// phpcs:disable WordPress.Security.NonceVerification
		$rot = self::rotulos();
		$sit = isset( $_GET['situacao'] ) ? sanitize_key( wp_unslash( $_GET['situacao'] ) ) : '';
		$tip = isset( $_GET['tipo'] ) ? sanitize_key( wp_unslash( $_GET['tipo'] ) ) : '';
		$bus = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		// phpcs:enable
		return [
			'situacao' => isset( $rot[ $sit ] ) ? $sit : '',
			'tipo'     => in_array( $tip, [ 'aluno', 'nao_aluno' ], true ) ? $tip : '',
			'q'        => substr( $bus, 0, 80 ),
		];
	}

	public static function tela() {
		if ( ! current_user_can( 'cav_aprovar' ) ) {
			wp_die( 'Sem permissão.' );
		}

		$p       = self::parametros();
		$todos   = self::todos();
		$rot     = self::rotulos();
		$conta   = array_fill_keys( array_keys( $rot ), 0 );
		foreach ( $todos as $a ) {
			if ( isset( $conta[ $a['status'] ] ) ) {
				$conta[ $a['status'] ]++;
			}
		}

		$lista = self::filtrar( $todos, $p['q'], $p['situacao'], $p['tipo'] );
		$total = count( $lista );
		// phpcs:ignore WordPress.Security.NonceVerification
		$pag   = isset( $_GET['pag'] ) ? max( 1, (int) $_GET['pag'] ) : 1;
		$paginas = max( 1, (int) ceil( $total / self::POR_PAGINA ) );
		$pag   = min( $pag, $paginas );
		$visiveis = array_slice( $lista, ( $pag - 1 ) * self::POR_PAGINA, self::POR_PAGINA );

		$base = admin_url( 'admin.php?page=cav-alunos' );
		$pode = current_user_can( 'cav_gerenciar' );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">Participantes do clube</h1>
			<?php if ( current_user_can( 'cav_gerenciar' ) ) : ?>
				<a class="page-title-action" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array_filter( [ 'action' => 'cav_alunos_csv', 'situacao' => $p['situacao'], 'tipo' => $p['tipo'], 'q' => $p['q'] ] ), admin_url( 'admin-post.php' ) ), 'cav_alunos_csv' ) ); ?>">Baixar planilha (Excel)</a>
			<?php endif; ?>
			<hr class="wp-header-end">
			<?php self::aviso(); ?>

			<ul class="subsubsub">
				<li><a href="<?php echo esc_url( add_query_arg( array_filter( [ 'tipo' => $p['tipo'], 'q' => $p['q'] ] ), $base ) ); ?>" class="<?php echo $p['situacao'] ? '' : 'current'; ?>">Todos <span class="count">(<?php echo (int) count( $todos ); ?>)</span></a></li>
				<?php foreach ( $rot as $chave => $nome ) :
					if ( ! $conta[ $chave ] ) {
						continue;
					}
					?>
					| <li><a href="<?php echo esc_url( add_query_arg( array_filter( [ 'situacao' => $chave, 'tipo' => $p['tipo'], 'q' => $p['q'] ] ), $base ) ); ?>" class="<?php echo $p['situacao'] === $chave ? 'current' : ''; ?>"><?php echo esc_html( $nome ); ?> <span class="count">(<?php echo (int) $conta[ $chave ]; ?>)</span></a></li>
				<?php endforeach; ?>
			</ul>

			<form method="get" style="margin:14px 0;clear:both">
				<input type="hidden" name="page" value="cav-alunos">
				<?php if ( $p['situacao'] ) : ?><input type="hidden" name="situacao" value="<?php echo esc_attr( $p['situacao'] ); ?>"><?php endif; ?>
				<input type="search" name="q" value="<?php echo esc_attr( $p['q'] ); ?>" placeholder="Nome, e-mail, CPF ou WhatsApp" style="min-width:280px">
				<select name="tipo">
					<option value="">Aluno e não aluno</option>
					<option value="aluno" <?php selected( $p['tipo'], 'aluno' ); ?>>Só alunos</option>
					<option value="nao_aluno" <?php selected( $p['tipo'], 'nao_aluno' ); ?>>Só não alunos</option>
				</select>
				<button class="button">Filtrar</button>
				<?php if ( $p['q'] || $p['tipo'] || $p['situacao'] ) : ?>
					<a href="<?php echo esc_url( $base ); ?>">Limpar</a>
				<?php endif; ?>
			</form>

			<?php if ( ! $visiveis ) : ?>
				<p>Nenhum participante encontrado.</p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th>Participante</th><th>Situação</th><th>Válido até</th><th>CPF</th><th>Contato</th><th>Nascimento</th><th>CEP</th><th>Tipo</th><?php echo $pode ? '<th>Ações</th>' : ''; ?>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $visiveis as $a ) :
						$cor = [ 'ok' => '#1a7f37', 'expirado' => '#b32d2e', 'pendente' => '#996800', 'recusado' => '#8c8f94', 'bloqueado' => '#b32d2e' ][ $a['status'] ] ?? '#50575e';
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( $a['nome'] ); ?></strong>
								<?php if ( $a['resp'] ) : ?><br><span class="description">Responsável: <?php echo esc_html( $a['resp'] ); ?></span><?php endif; ?>
							</td>
							<td><span style="color:<?php echo esc_attr( $cor ); ?>;font-weight:600"><?php echo esc_html( $rot[ $a['status'] ] ?? '—' ); ?></span></td>
							<td><?php echo esc_html( self::data( $a['validade'] ) ); ?></td>
							<td><?php echo esc_html( CAV_CPF::formatar( CAV_Membro::cpf_do_usuario( $a['id'] ) ) ?: '—' ); ?></td>
							<td>
								<?php echo esc_html( $a['email'] ); ?>
								<?php if ( $a['zap'] ) : ?><br><span class="description"><?php echo esc_html( $a['zap'] ); ?></span><?php endif; ?>
							</td>
							<td><?php echo esc_html( self::data( $a['nasc'] ) ); ?><?php echo null !== $a['idade'] ? ' <span class="description">(' . (int) $a['idade'] . ' anos)</span>' : ''; ?></td>
							<td><?php echo esc_html( CAV_Solicitacoes::formatar_cep( $a['cep'] ) ?: '—' ); ?></td>
							<td><?php echo esc_html( CAV_Solicitacoes::rotulo_tipo( $a['tipo'] ) ); ?></td>
							<?php if ( $pode ) : ?>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:6px;flex-wrap:wrap">
										<input type="hidden" name="action" value="cav_participante">
										<input type="hidden" name="user_id" value="<?php echo (int) $a['id']; ?>">
										<input type="hidden" name="volta" value="<?php echo esc_attr( add_query_arg( array_filter( [ 'situacao' => $p['situacao'], 'tipo' => $p['tipo'], 'q' => $p['q'], 'pag' => $pag > 1 ? $pag : null ] ), $base ) ); ?>">
										<?php wp_nonce_field( 'cav_participante_' . $a['id'] ); ?>
										<?php if ( 'bloqueado' === $a['status'] ) : ?>
											<button class="button" name="decisao" value="desbloquear">Desbloquear</button>
										<?php else : ?>
											<button class="button" name="decisao" value="bloquear"
											        onclick="return confirm('Bloquear este participante? Ele deixa de ter os descontos até você desbloquear.');">Bloquear</button>
										<?php endif; ?>
										<button class="button" name="decisao" value="excluir" style="color:#b32d2e;border-color:#b32d2e"
										        onclick="return confirm('Excluir <?php echo esc_js( $a['nome'] ); ?> de vez? Os dados do cadastro (CPF, contato, inscrições em sorteios) são apagados e não dá para desfazer.');">Excluir</button>
									</form>
								</td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<p class="description" style="margin-top:10px">
					<?php echo (int) $total; ?> participante(s)<?php echo $paginas > 1 ? ', página ' . (int) $pag . ' de ' . (int) $paginas : ''; ?>.
				</p>
				<?php if ( $paginas > 1 ) : ?>
					<p>
						<?php if ( $pag > 1 ) : ?>
							<a class="button" href="<?php echo esc_url( add_query_arg( array_filter( [ 'situacao' => $p['situacao'], 'tipo' => $p['tipo'], 'q' => $p['q'], 'pag' => $pag - 1 ] ), $base ) ); ?>">‹ Anterior</a>
						<?php endif; ?>
						<?php if ( $pag < $paginas ) : ?>
							<a class="button" href="<?php echo esc_url( add_query_arg( array_filter( [ 'situacao' => $p['situacao'], 'tipo' => $p['tipo'], 'q' => $p['q'], 'pag' => $pag + 1 ] ), $base ) ); ?>">Próxima ›</a>
						<?php endif; ?>
					</p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function aviso() {
		// phpcs:ignore WordPress.Security.NonceVerification
		$c = isset( $_GET['cav_msg'] ) ? sanitize_key( wp_unslash( $_GET['cav_msg'] ) ) : '';
		$mapa = [
			'bloqueado'    => [ 'warning', 'Participante bloqueado.' ],
			'desbloqueado' => [ 'success', 'Participante desbloqueado.' ],
			'excluido'     => [ 'success', 'Participante excluído, com os dados do cadastro.' ],
			'invalido'     => [ 'error', 'Não foi possível: só dá para atuar em participantes do clube.' ],
		];
		if ( isset( $mapa[ $c ] ) ) {
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $mapa[ $c ][0] ), esc_html( $mapa[ $c ][1] ) );
		}
	}

	/** Bloquear, desbloquear e excluir. Só a administração. */
	public static function acao() {
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;

		if ( ! current_user_can( 'cav_gerenciar' ) ) {
			wp_die( 'Sem permissão.', '', [ 'response' => 403 ] );
		}
		check_admin_referer( 'cav_participante_' . $user_id );

		$padrao = admin_url( 'admin.php?page=cav-alunos' );
		$volta  = isset( $_POST['volta'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['volta'] ) ), $padrao ) : $padrao;
		$volta  = remove_query_arg( 'cav_msg', $volta );
		$u      = get_userdata( $user_id );

		// Só participante do clube: nunca administrador, recepção, parceiro nem a própria conta.
		if ( ! $u || $user_id === get_current_user_id() || [ 'cav_membro' ] !== array_values( (array) $u->roles ) ) {
			wp_safe_redirect( add_query_arg( 'cav_msg', 'invalido', $volta ) );
			exit;
		}

		$decisao = isset( $_POST['decisao'] ) ? sanitize_key( wp_unslash( $_POST['decisao'] ) ) : '';

		if ( 'bloquear' === $decisao ) {
			update_user_meta( $user_id, CAV_Membro::META_BLOQUEADO, '1' );
			$msg = 'bloqueado';
		} elseif ( 'desbloquear' === $decisao ) {
			delete_user_meta( $user_id, CAV_Membro::META_BLOQUEADO );
			$msg = 'desbloqueado';
		} elseif ( 'excluir' === $decisao ) {
			self::excluir( $user_id );
			$msg = 'excluido';
		} else {
			$msg = 'invalido';
		}

		wp_safe_redirect( add_query_arg( 'cav_msg', $msg, $volta ) );
		exit;
	}

	/**
	 * Apaga o participante e o que o identifica. Os usos de benefício ficam só como número nos relatórios
	 * dos parceiros, sem ligação com a pessoa (membro 0, sem IP).
	 */
	public static function excluir( $user_id ) {
		global $wpdb;
		$user_id = (int) $user_id;

		$wpdb->update( CAV_Install::tabela_usos(), [ 'membro_id' => 0, 'ip' => null ], [ 'membro_id' => $user_id ] );
		$wpdb->delete( CAV_Install::tabela_inscricoes(), [ 'membro_id' => $user_id ] );

		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user_id );
	}

	/** Planilha para Excel (separada por ponto e vírgula, com acento certo). Só a administração. */
	public static function baixar() {
		if ( ! current_user_can( 'cav_gerenciar' ) ) {
			wp_die( 'Sem permissão.', '', [ 'response' => 403 ] );
		}
		check_admin_referer( 'cav_alunos_csv' );

		$p     = self::parametros();
		$lista = self::filtrar( self::todos(), $p['q'], $p['situacao'], $p['tipo'] );
		$rot   = self::rotulos();

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="participantes-clube-' . wp_date( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // Excel reconhece o acento
		// Evita que o Excel execute o que começa com = + - @ (nome ou e-mail malicioso).
		$seguro = static function ( $v ) {
			$v = (string) $v;
			return ( '' !== $v && in_array( $v[0], [ '=', '+', '-', '@', "\t", "\r" ], true ) ) ? "'" . $v : $v;
		};

		fputcsv( $out, [ 'Nome', 'Situação', 'Válido até', 'CPF', 'E-mail', 'WhatsApp', 'Nascimento', 'CEP', 'Tipo', 'Responsável', 'Pedido em' ], ';' );
		foreach ( $lista as $a ) {
			fputcsv( $out, [
				$seguro( $a['nome'] ),
				$rot[ $a['status'] ] ?? '',
				self::data( $a['validade'] ),
				CAV_CPF::formatar( CAV_Membro::cpf_do_usuario( $a['id'] ) ),
				$seguro( $a['email'] ),
				$seguro( $a['zap'] ),
				self::data( $a['nasc'] ),
				CAV_Solicitacoes::formatar_cep( $a['cep'] ),
				CAV_Solicitacoes::rotulo_tipo( $a['tipo'] ),
				$seguro( $a['resp'] ),
				self::data( $a['pedido'] ),
			], ';' );
		}
		fclose( $out );
		exit;
	}
}

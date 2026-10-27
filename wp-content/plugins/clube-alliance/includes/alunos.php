<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Aprovações → Alunos: a lista de todos os alunos do clube, com os dados do cadastro, filtros e busca.
 * A recepção e a administração veem a lista; só a administração baixa a planilha (Excel).
 */
class CAV_Alunos {

	const POR_PAGINA = 50;

	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'menu' ], 10 );
		add_action( 'admin_post_cav_alunos_csv', [ __CLASS__, 'baixar' ] );
	}

	public static function menu() {
		add_submenu_page(
			'cav-aprovacoes',
			'Alunos do clube',
			'Alunos',
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
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">Alunos do clube</h1>
			<?php if ( current_user_can( 'cav_gerenciar' ) ) : ?>
				<a class="page-title-action" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array_filter( [ 'action' => 'cav_alunos_csv', 'situacao' => $p['situacao'], 'tipo' => $p['tipo'], 'q' => $p['q'] ] ), admin_url( 'admin-post.php' ) ), 'cav_alunos_csv' ) ); ?>">Baixar planilha (Excel)</a>
			<?php endif; ?>
			<hr class="wp-header-end">

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
				<p>Nenhum aluno encontrado.</p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th>Aluno</th><th>Situação</th><th>Válido até</th><th>CPF</th><th>Contato</th><th>Nascimento</th><th>CEP</th><th>Tipo</th>
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
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<p class="description" style="margin-top:10px">
					<?php echo (int) $total; ?> aluno(s)<?php echo $paginas > 1 ? ', página ' . (int) $pag . ' de ' . (int) $paginas : ''; ?>.
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
		header( 'Content-Disposition: attachment; filename="alunos-clube-' . wp_date( 'Y-m-d' ) . '.csv"' );

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

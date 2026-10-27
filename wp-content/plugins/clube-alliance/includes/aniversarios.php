<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Aprovações → Aniversariantes: quem faz aniversário no mês, para a recepção entregar o brinde.
 * Mostra só alunos com o clube ativo, e só o que a recepção precisa (nome, dia, idade, WhatsApp).
 */
class CAV_Aniversarios {

	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'menu' ], 11 );
	}

	public static function menu() {
		add_submenu_page(
			'cav-aprovacoes',
			'Aniversariantes do mês',
			'Aniversariantes',
			'cav_aprovar',
			'cav-aniversarios',
			[ __CLASS__, 'tela' ]
		);
	}

	/**
	 * Alunos ativos que fazem aniversário no mês (1 a 12), em ordem de dia.
	 *
	 * @return array<int, array{user:WP_User, dia:int, idade:int, zap:string}>
	 */
	public static function do_mes( $mes, $ano = null ) {
		$mes = max( 1, min( 12, (int) $mes ) );
		$ano = $ano ? (int) $ano : (int) current_time( 'Y' );

		$users = get_users( [
			'role'       => 'cav_membro',
			'meta_query' => [
				[
					'key'     => CAV_Solicitacoes::META_NASC,
					'value'   => sprintf( '-%02d-', $mes ), // datas são Y-m-d
					'compare' => 'LIKE',
				],
			],
			'number'     => 2000,
		] );

		$lista = [];
		foreach ( $users as $u ) {
			if ( ! CAV_Membro::checar( $u )['elegivel'] ) {
				continue;
			}
			$nasc = (string) get_user_meta( $u->ID, CAV_Solicitacoes::META_NASC, true );
			if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $nasc, $m ) || (int) $m[2] !== $mes ) {
				continue;
			}
			$lista[] = [
				'user'  => $u,
				'dia'   => (int) $m[3],
				'idade' => $ano - (int) $m[1],
				'zap'   => (string) get_user_meta( $u->ID, CAV_Solicitacoes::META_WHATSAPP, true ),
			];
		}

		usort( $lista, static function ( $a, $b ) {
			return $a['dia'] <=> $b['dia'] ?: strcasecmp( $a['user']->display_name, $b['user']->display_name );
		} );

		return $lista;
	}

	private static function nome_do_mes( $mes ) {
		$nomes = [ 1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro' ];
		return $nomes[ max( 1, min( 12, (int) $mes ) ) ];
	}

	public static function tela() {
		if ( ! current_user_can( 'cav_aprovar' ) ) {
			wp_die( 'Sem permissão.' );
		}

		// phpcs:ignore WordPress.Security.NonceVerification
		$mes   = isset( $_GET['mes'] ) ? max( 1, min( 12, (int) $_GET['mes'] ) ) : (int) current_time( 'n' );
		$lista = self::do_mes( $mes );
		$hoje  = (int) current_time( 'j' );
		$agora = (int) current_time( 'n' );
		?>
		<div class="wrap">
			<h1>Aniversariantes de <?php echo esc_html( self::nome_do_mes( $mes ) ); ?></h1>
			<p>Alunos com o clube ativo que fazem aniversário neste mês. Quem está com o clube vencido não entra na lista.</p>

			<form method="get" style="margin:12px 0">
				<input type="hidden" name="page" value="cav-aniversarios">
				<label for="cav-mes" class="screen-reader-text">Mês</label>
				<select id="cav-mes" name="mes">
					<?php for ( $i = 1; $i <= 12; $i++ ) : ?>
						<option value="<?php echo (int) $i; ?>" <?php selected( $mes, $i ); ?>><?php echo esc_html( ucfirst( self::nome_do_mes( $i ) ) ); ?></option>
					<?php endfor; ?>
				</select>
				<button class="button">Ver</button>
			</form>

			<?php if ( ! $lista ) : ?>
				<p>Ninguém faz aniversário neste mês.</p>
			<?php else : ?>
				<table class="widefat striped" style="max-width:720px">
					<thead><tr><th style="width:70px">Dia</th><th>Aluno</th><th style="width:90px">Faz</th><th>WhatsApp</th></tr></thead>
					<tbody>
					<?php foreach ( $lista as $l ) :
						$e_hoje = ( $mes === $agora && $l['dia'] === $hoje );
						?>
						<tr<?php echo $e_hoje ? ' style="background:#fff8d6"' : ''; ?>>
							<td><strong><?php echo esc_html( str_pad( (string) $l['dia'], 2, '0', STR_PAD_LEFT ) ); ?></strong><?php echo $e_hoje ? ' <span class="description">hoje</span>' : ''; ?></td>
							<td><?php echo esc_html( $l['user']->display_name ); ?></td>
							<td><?php echo (int) $l['idade']; ?> anos</td>
							<td><?php echo esc_html( $l['zap'] ?: '—' ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description"><?php echo (int) count( $lista ); ?> aniversariante(s).</p>
			<?php endif; ?>
		</div>
		<?php
	}
}

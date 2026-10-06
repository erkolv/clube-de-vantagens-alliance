<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CAV_Admin {

	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
		add_filter( 'manage_cav_beneficio_posts_columns', [ __CLASS__, 'colunas_beneficio' ] );
		add_action( 'manage_cav_beneficio_posts_custom_column', [ __CLASS__, 'coluna_beneficio' ], 10, 2 );
	}

	public static function colunas_beneficio( $cols ) {
		$novas = [];
		foreach ( $cols as $chave => $rotulo ) {
			$novas[ $chave ] = $rotulo;
			if ( 'title' === $chave ) {
				$novas['cav_parceiro']  = 'Parceiro';
				$novas['cav_vigencia']  = 'Vigência';
				$novas['cav_situacao']  = 'No ar';
			}
		}
		return $novas;
	}

	public static function coluna_beneficio( $col, $post_id ) {
		if ( 'cav_parceiro' === $col ) {
			$pid = (int) get_post_meta( $post_id, CAV_Parceiro::META_BEN_PARCEIRO, true );
			echo esc_html( $pid ? get_the_title( $pid ) : '—' );
			return;
		}

		if ( 'cav_vigencia' === $col ) {
			echo esc_html( CAV_Parceiro::rotulo_vigencia( $post_id ) );
			return;
		}

		if ( 'cav_situacao' === $col ) {
			$ativo = get_post_meta( $post_id, CAV_Parceiro::META_BEN_ATIVO, true );
			if ( ! $ativo ) {
				echo '<span style="color:#b32d2e">Desativado</span>';
			} elseif ( ! CAV_Parceiro::em_vigencia( $post_id ) ) {
				echo '<span style="color:#b32d2e">Fora da vigência</span>';
			} else {
				echo '<span style="color:#1a7f37">Sim</span>';
			}
		}
	}

	public static function menu() {
		add_menu_page(
			'Clube de Vantagens',
			'Clube',
			'cav_gerenciar',
			'cav-relatorio',
			[ __CLASS__, 'tela_relatorio' ],
			'dashicons-awards',
			26
		);
	}

	public static function tela_relatorio() {
		if ( ! current_user_can( 'cav_gerenciar' ) ) {
			wp_die( 'Sem permissão.' );
		}

		global $wpdb;
		$tabela = CAV_Install::tabela_usos();

		$hoje = current_time( 'Y-m-d' ) . ' 00:00:00';
		$mes  = current_time( 'Y-m' ) . '-01 00:00:00';

		$total_hoje = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tabela} WHERE criado_em >= %s", $hoje ) );
		$total_mes  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tabela} WHERE criado_em >= %s", $mes ) );
		$total      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tabela}" );

		$por_parceiro = $wpdb->get_results( $wpdb->prepare(
			"SELECT parceiro_id, COUNT(*) AS n, COUNT(DISTINCT membro_id) AS membros
			 FROM {$tabela} WHERE criado_em >= %s
			 GROUP BY parceiro_id ORDER BY n DESC LIMIT 50",
			$mes
		) );

		$recentes = $wpdb->get_results( "SELECT * FROM {$tabela} ORDER BY criado_em DESC LIMIT 40" );
		?>
		<div class="wrap">
			<h1>Clube de Vantagens</h1>

			<p style="font-size:15px">
				<strong><?php echo esc_html( $total_hoje ); ?></strong> usos hoje ·
				<strong><?php echo esc_html( $total_mes ); ?></strong> no mês ·
				<strong><?php echo esc_html( $total ); ?></strong> desde o início
			</p>

			<h2>Por parceiro (mês atual)</h2>
			<table class="widefat striped">
				<thead><tr><th>Parceiro</th><th>Usos</th><th>Membros distintos</th></tr></thead>
				<tbody>
				<?php if ( $por_parceiro ) : foreach ( $por_parceiro as $l ) : ?>
					<tr>
						<td><?php echo esc_html( get_the_title( $l->parceiro_id ) ?: '#' . $l->parceiro_id ); ?></td>
						<td><?php echo esc_html( $l->n ); ?></td>
						<td><?php echo esc_html( $l->membros ); ?></td>
					</tr>
				<?php endforeach; else : ?>
					<tr><td colspan="3">Nenhum uso registrado neste mês.</td></tr>
				<?php endif; ?>
				</tbody>
			</table>

			<h2>Últimos registros</h2>
			<table class="widefat striped">
				<thead><tr><th>Quando</th><th>Parceiro</th><th>Benefício</th><th>Membro</th></tr></thead>
				<tbody>
				<?php if ( $recentes ) : foreach ( $recentes as $u ) :
					$membro = get_userdata( $u->membro_id ); ?>
					<tr>
						<td><?php echo esc_html( mysql2date( 'd/m/Y H:i', $u->criado_em ) ); ?></td>
						<td><?php echo esc_html( get_the_title( $u->parceiro_id ) ?: '—' ); ?></td>
						<td><?php echo esc_html( get_the_title( $u->beneficio_id ) ?: '—' ); ?></td>
						<td><?php echo esc_html( $membro ? $membro->display_name : '—' ); ?></td>
					</tr>
				<?php endforeach; else : ?>
					<tr><td colspan="4">Nada registrado ainda.</td></tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Agenda da academia: seminários, campeonatos, graduação e outros eventos.
 * Cadastro pelo painel; só membros com matrícula em dia enxergam.
 */
class CAV_Agenda {

	const CPT       = 'cav_evento';
	const META_TIPO = '_cav_ev_tipo';
	const META_DATA = '_cav_ev_data';     // Y-m-d, primeiro dia
	const META_ATE  = '_cav_ev_ate';      // Y-m-d, último dia (igual ao primeiro se não houver)
	const META_HORA = '_cav_ev_hora';     // H:i, opcional
	const META_LOCAL = '_cav_ev_local';
	const META_LINK = '_cav_ev_link';
	const META_LINK_TEXTO = '_cav_ev_link_texto';

	public static function init() {
		add_action( 'init', [ __CLASS__, 'registrar' ] );
		add_action( 'add_meta_boxes', [ __CLASS__, 'metabox' ] );
		add_action( 'save_post_' . self::CPT, [ __CLASS__, 'salvar' ] );
		add_filter( 'manage_' . self::CPT . '_posts_columns', [ __CLASS__, 'colunas' ] );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', [ __CLASS__, 'coluna_valor' ], 10, 2 );
	}

	public static function tipos() {
		return [
			'seminario'  => 'Seminário',
			'campeonato' => 'Campeonato',
			'graduacao'  => 'Graduação',
			'treino'     => 'Treino especial',
			'evento'     => 'Evento',
		];
	}

	public static function rotulo_tipo( $tipo ) {
		$t = self::tipos();
		return $t[ $tipo ] ?? $t['evento'];
	}

	public static function registrar() {
		register_post_type( self::CPT, [
			'labels' => [
				'name'          => 'Agenda e eventos',
				'singular_name' => 'Evento',
				'add_new_item'  => 'Adicionar evento',
				'edit_item'     => 'Editar evento',
				'menu_name'     => 'Agenda',
			],
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => false,
			'exclude_from_search' => true,
			'menu_icon'           => 'dashicons-calendar-alt',
			'menu_position'       => 28,
			'supports'            => [ 'title', 'editor', 'thumbnail' ],
		] );
	}

	/* ------------------------------ Cadastro ------------------------------ */

	public static function metabox() {
		add_meta_box( 'cav_evento_dados', 'Dados do evento', [ __CLASS__, 'render_metabox' ], self::CPT, 'normal', 'high' );
	}

	public static function render_metabox( $post ) {
		$tipo  = get_post_meta( $post->ID, self::META_TIPO, true ) ?: 'seminario';
		$data  = get_post_meta( $post->ID, self::META_DATA, true );
		$ate   = get_post_meta( $post->ID, self::META_ATE, true );
		$hora  = get_post_meta( $post->ID, self::META_HORA, true );
		$local = get_post_meta( $post->ID, self::META_LOCAL, true );
		$link  = get_post_meta( $post->ID, self::META_LINK, true );
		$texto = get_post_meta( $post->ID, self::META_LINK_TEXTO, true );

		if ( $ate === $data ) {
			$ate = '';
		}

		wp_nonce_field( 'cav_salvar_evento', 'cav_evento_nonce' );
		?>
		<p>
			<label><strong>Tipo</strong></label><br>
			<select name="cav_ev_tipo">
				<?php foreach ( self::tipos() as $valor => $rotulo ) : ?>
					<option value="<?php echo esc_attr( $valor ); ?>" <?php selected( $tipo, $valor ); ?>><?php echo esc_html( $rotulo ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label style="margin-right:1.5rem"><strong>Data</strong> (obrigatória)<br>
				<input type="date" name="cav_ev_data" value="<?php echo esc_attr( $data ); ?>" required>
			</label>
			<label style="margin-right:1.5rem"><strong>Até</strong> (se durar mais de um dia)<br>
				<input type="date" name="cav_ev_ate" value="<?php echo esc_attr( $ate ); ?>">
			</label>
			<label><strong>Horário</strong> (opcional)<br>
				<input type="time" name="cav_ev_hora" value="<?php echo esc_attr( $hora ); ?>">
			</label>
		</p>
		<p>
			<label><strong>Local</strong></label><br>
			<input type="text" name="cav_ev_local" value="<?php echo esc_attr( $local ); ?>" placeholder="Ex.: Alliance Mogi das Cruzes, tatame principal" style="width:100%;max-width:600px">
		</p>
		<p>
			<label><strong>Link</strong> (inscrição ou mais informações, opcional)</label><br>
			<input type="url" name="cav_ev_link" value="<?php echo esc_attr( $link ); ?>" placeholder="https://" style="width:100%;max-width:600px">
		</p>
		<p>
			<label><strong>Texto do botão</strong> (padrão: Mais informações)</label><br>
			<input type="text" name="cav_ev_link_texto" value="<?php echo esc_attr( $texto ); ?>" placeholder="Fazer inscrição" style="width:100%;max-width:300px">
		</p>
		<p class="description">A descrição do evento vai no campo de texto principal. Eventos passados saem da lista de próximos, mas continuam no calendário do mês.</p>
		<?php
	}

	public static function salvar( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['cav_evento_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cav_evento_nonce'] ) ), 'cav_salvar_evento' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$data_ok = static function ( $v ) {
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : '';
		};

		$tipo = isset( $_POST['cav_ev_tipo'] ) ? sanitize_key( wp_unslash( $_POST['cav_ev_tipo'] ) ) : 'evento';
		update_post_meta( $post_id, self::META_TIPO, array_key_exists( $tipo, self::tipos() ) ? $tipo : 'evento' );

		$data = $data_ok( isset( $_POST['cav_ev_data'] ) ? sanitize_text_field( wp_unslash( $_POST['cav_ev_data'] ) ) : '' );
		$ate  = $data_ok( isset( $_POST['cav_ev_ate'] ) ? sanitize_text_field( wp_unslash( $_POST['cav_ev_ate'] ) ) : '' );
		if ( ! $ate || $ate < $data ) {
			$ate = $data;
		}
		update_post_meta( $post_id, self::META_DATA, $data );
		update_post_meta( $post_id, self::META_ATE, $ate );

		$hora = isset( $_POST['cav_ev_hora'] ) ? sanitize_text_field( wp_unslash( $_POST['cav_ev_hora'] ) ) : '';
		update_post_meta( $post_id, self::META_HORA, preg_match( '/^\d{2}:\d{2}$/', $hora ) ? $hora : '' );

		update_post_meta( $post_id, self::META_LOCAL, isset( $_POST['cav_ev_local'] ) ? sanitize_text_field( wp_unslash( $_POST['cav_ev_local'] ) ) : '' );
		update_post_meta( $post_id, self::META_LINK, isset( $_POST['cav_ev_link'] ) ? esc_url_raw( wp_unslash( $_POST['cav_ev_link'] ) ) : '' );
		update_post_meta( $post_id, self::META_LINK_TEXTO, isset( $_POST['cav_ev_link_texto'] ) ? sanitize_text_field( wp_unslash( $_POST['cav_ev_link_texto'] ) ) : '' );
	}

	public static function colunas( $cols ) {
		$novas = [];
		foreach ( $cols as $chave => $rotulo ) {
			$novas[ $chave ] = $rotulo;
			if ( 'title' === $chave ) {
				$novas['cav_quando'] = 'Quando';
				$novas['cav_tipo']   = 'Tipo';
			}
		}
		unset( $novas['date'] );
		return $novas;
	}

	public static function coluna_valor( $coluna, $post_id ) {
		if ( 'cav_quando' === $coluna ) {
			$data = get_post_meta( $post_id, self::META_DATA, true );
			echo $data ? esc_html( mysql2date( 'd/m/Y', $data . ' 00:00:00' ) ) : '<em>sem data</em>';
		} elseif ( 'cav_tipo' === $coluna ) {
			echo esc_html( self::rotulo_tipo( get_post_meta( $post_id, self::META_TIPO, true ) ) );
		}
	}

	/** Sem depender da extensão calendar do PHP, que a imagem do WordPress não traz. */
	public static function dias_no_mes( $ano, $mes ) {
		return (int) gmdate( 't', gmmktime( 0, 0, 0, $mes, 1, $ano ) );
	}

	/* ------------------------------ Consulta ------------------------------ */

	/** Eventos que ainda não acabaram, do mais próximo ao mais distante. */
	public static function proximos( $qtd = 6 ) {
		return get_posts( [
			'post_type'      => self::CPT,
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, (int) $qtd ),
			'meta_key'       => self::META_DATA,
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'meta_query'     => [
				[ 'key' => self::META_ATE, 'value' => current_time( 'Y-m-d' ), 'compare' => '>=', 'type' => 'DATE' ],
			],
		] );
	}

	/** Eventos que tocam o mês, passados ou não. */
	public static function do_mes( $ano, $mes ) {
		$primeiro = sprintf( '%04d-%02d-01', $ano, $mes );
		$ultimo   = sprintf( '%04d-%02d-%02d', $ano, $mes, self::dias_no_mes( $ano, $mes ) );

		return get_posts( [
			'post_type'      => self::CPT,
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'meta_key'       => self::META_DATA,
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'meta_query'     => [
				'relation' => 'AND',
				[ 'key' => self::META_DATA, 'value' => $ultimo, 'compare' => '<=', 'type' => 'DATE' ],
				[ 'key' => self::META_ATE, 'value' => $primeiro, 'compare' => '>=', 'type' => 'DATE' ],
			],
		] );
	}

	/* ----------------------------- Apresentação ----------------------------- */

	const MESES = [ 1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro' ];
	const MESES_CURTOS = [ 1 => 'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez' ];

	/**
	 * Mês do calendário como lista de semanas; cada dia é null (fora do mês) ou
	 * [ 'dia' => 12, 'data' => '2026-11-12', 'eventos' => [ID, ID] ].
	 *
	 * @param array $eventos lista de [ 'id' => , 'data' => , 'ate' => ]
	 */
	public static function grade_mes( $ano, $mes, array $eventos ) {
		$dias      = self::dias_no_mes( $ano, $mes );
		$deslocar  = (int) gmdate( 'w', gmmktime( 0, 0, 0, $mes, 1, $ano ) ); // 0 = domingo
		$semanas   = [];
		$semana    = array_fill( 0, $deslocar, null );

		for ( $d = 1; $d <= $dias; $d++ ) {
			$data  = sprintf( '%04d-%02d-%02d', $ano, $mes, $d );
			$cai   = [];
			foreach ( $eventos as $e ) {
				if ( $e['data'] <= $data && $e['ate'] >= $data ) {
					$cai[] = $e['id'];
				}
			}
			$semana[] = [ 'dia' => $d, 'data' => $data, 'eventos' => $cai ];

			if ( 7 === count( $semana ) ) {
				$semanas[] = $semana;
				$semana    = [];
			}
		}

		if ( $semana ) {
			$semanas[] = array_pad( $semana, 7, null );
		}

		return $semanas;
	}

	/** Quando acontece, em texto: "12 de novembro, às 9h" ou "12 a 14 de novembro". */
	public static function quando( $post_id ) {
		$data = get_post_meta( $post_id, self::META_DATA, true );
		$ate  = get_post_meta( $post_id, self::META_ATE, true );
		$hora = get_post_meta( $post_id, self::META_HORA, true );
		if ( ! $data ) {
			return '';
		}

		[ $a1, $m1, $d1 ] = array_map( 'intval', explode( '-', $data ) );
		$texto = $d1 . ' de ' . self::MESES[ $m1 ];

		if ( $ate && $ate !== $data ) {
			[ $a2, $m2, $d2 ] = array_map( 'intval', explode( '-', $ate ) );
			$texto = ( $m1 === $m2 && $a1 === $a2 )
				? $d1 . ' a ' . $d2 . ' de ' . self::MESES[ $m2 ]
				: $d1 . ' de ' . self::MESES[ $m1 ] . ' a ' . $d2 . ' de ' . self::MESES[ $m2 ];
		}

		if ( $hora ) {
			$h      = (int) substr( $hora, 0, 2 );
			$m      = substr( $hora, 3, 2 );
			$texto .= ', às ' . $h . ( '00' === $m ? 'h' : 'h' . $m );
		}

		return $texto;
	}

	/** Cartão de um evento para a lista. */
	public static function item( $post, $ancora = '' ) {
		$tipo  = get_post_meta( $post->ID, self::META_TIPO, true ) ?: 'evento';
		$data  = get_post_meta( $post->ID, self::META_DATA, true );
		$local = get_post_meta( $post->ID, self::META_LOCAL, true );
		$link  = get_post_meta( $post->ID, self::META_LINK, true );
		$texto = get_post_meta( $post->ID, self::META_LINK_TEXTO, true ) ?: 'Mais informações';
		$desc  = wp_trim_words( wp_strip_all_tags( $post->post_content ), 32 );

		[ , $mes, $dia ] = array_map( 'intval', explode( '-', $data ?: '0000-01-01' ) );

		ob_start();
		?>
		<article class="cav-evento"<?php echo $ancora ? ' id="' . esc_attr( $ancora ) . '"' : ''; ?>>
			<div class="cav-evento__data" aria-hidden="true">
				<strong><?php echo esc_html( $dia ); ?></strong>
				<span><?php echo esc_html( self::MESES_CURTOS[ max( 1, $mes ) ] ); ?></span>
			</div>
			<div class="cav-evento__corpo">
				<span class="cav-tag cav-tag--<?php echo esc_attr( $tipo ); ?>"><?php echo esc_html( self::rotulo_tipo( $tipo ) ); ?></span>
				<h3 class="cav-evento__titulo"><?php echo esc_html( get_the_title( $post ) ); ?></h3>
				<p class="cav-evento__quando"><?php echo esc_html( self::quando( $post->ID ) ); ?><?php echo $local ? ' · ' . esc_html( $local ) : ''; ?></p>
				<?php if ( $desc ) : ?>
					<p class="cav-evento__desc"><?php echo esc_html( $desc ); ?></p>
				<?php endif; ?>
				<?php if ( $link ) : ?>
					<p><a class="cav-link-botao" href="<?php echo esc_url( $link ); ?>" rel="noopener"><?php echo esc_html( $texto ); ?></a></p>
				<?php endif; ?>
			</div>
		</article>
		<?php
		return ob_get_clean();
	}

	/** Calendário do mês (HTML). $url_base é a página atual, sem o parâmetro mes. */
	public static function calendario( $ano, $mes, array $posts, $url_base ) {
		$eventos = [];
		foreach ( $posts as $p ) {
			$data = get_post_meta( $p->ID, self::META_DATA, true );
			$ate  = get_post_meta( $p->ID, self::META_ATE, true ) ?: $data;
			if ( $data ) {
				$eventos[] = [ 'id' => $p->ID, 'data' => $data, 'ate' => $ate ];
			}
		}

		$semanas = self::grade_mes( $ano, $mes, $eventos );
		$hoje    = current_time( 'Y-m-d' );

		$anterior = gmdate( 'Y-m', gmmktime( 0, 0, 0, $mes - 1, 1, $ano ) );
		$proximo  = gmdate( 'Y-m', gmmktime( 0, 0, 0, $mes + 1, 1, $ano ) );

		ob_start();
		?>
		<div class="cav-calendario">
			<div class="cav-calendario__topo">
				<a class="cav-calendario__nav" href="<?php echo esc_url( add_query_arg( 'mes', $anterior, $url_base ) ); ?>" aria-label="Mês anterior">&larr;</a>
				<h3><?php echo esc_html( ucfirst( self::MESES[ $mes ] ) . ' de ' . $ano ); ?></h3>
				<a class="cav-calendario__nav" href="<?php echo esc_url( add_query_arg( 'mes', $proximo, $url_base ) ); ?>" aria-label="Próximo mês">&rarr;</a>
			</div>
			<table class="cav-calendario__grade">
				<thead>
					<tr>
						<?php foreach ( [ 'Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb' ] as $d ) : ?>
							<th scope="col"><?php echo esc_html( $d ); ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $semanas as $semana ) : ?>
					<tr>
					<?php foreach ( $semana as $dia ) : ?>
						<?php if ( null === $dia ) : ?>
							<td class="is-fora"></td>
						<?php else :
							$classes = [];
							if ( $dia['eventos'] ) {
								$classes[] = 'tem-evento';
							}
							if ( $dia['data'] === $hoje ) {
								$classes[] = 'is-hoje';
							}
							?>
							<td class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
								<?php if ( $dia['eventos'] ) : ?>
									<a href="#cav-evento-<?php echo esc_attr( $dia['eventos'][0] ); ?>" title="<?php echo esc_attr( get_the_title( $dia['eventos'][0] ) ); ?>">
										<?php echo esc_html( $dia['dia'] ); ?>
									</a>
								<?php else : ?>
									<?php echo esc_html( $dia['dia'] ); ?>
								<?php endif; ?>
							</td>
						<?php endif; ?>
					<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
		return ob_get_clean();
	}
}

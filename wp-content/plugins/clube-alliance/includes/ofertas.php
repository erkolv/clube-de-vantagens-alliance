<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ofertas da própria academia para quem é do clube: produtos com preço de/por
 * e descontos exclusivos (plano, aula, loja). Quem cuida do cadastro é a Alliance;
 * só membros com matrícula em dia enxergam.
 */
class CAV_Ofertas {

	const CPT        = 'cav_oferta';
	const META_TIPO  = '_cav_of_tipo';   // produto | desconto
	const META_DE    = '_cav_of_de';     // preço cheio, "389.90"
	const META_POR   = '_cav_of_por';    // preço do clube
	const META_SELO  = '_cav_of_selo';   // texto do selo; vazio = calcula pelo preço
	const META_FIM   = '_cav_of_fim';    // vazio = não expira
	const META_ATIVO = '_cav_of_ativo';
	const META_COMO  = '_cav_of_como';   // como pegar

	public static function init() {
		add_action( 'init', [ __CLASS__, 'registrar' ] );
		add_action( 'add_meta_boxes', [ __CLASS__, 'metabox' ] );
		add_action( 'save_post_' . self::CPT, [ __CLASS__, 'salvar' ] );
	}

	public static function tipos() {
		return [
			'produto'  => 'Produto com desconto',
			'desconto' => 'Desconto exclusivo na academia',
		];
	}

	public static function registrar() {
		register_post_type( self::CPT, [
			'labels' => [
				'name'          => 'Ofertas da academia',
				'singular_name' => 'Oferta',
				'add_new_item'  => 'Adicionar oferta',
				'edit_item'     => 'Editar oferta',
				'menu_name'     => 'Ofertas da academia',
			],
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => false,
			'exclude_from_search' => true,
			'menu_icon'           => 'dashicons-cart',
			'menu_position'       => 27,
			'supports'            => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
		] );
	}

	/* ------------------------------ Cadastro ------------------------------ */

	public static function metabox() {
		add_meta_box( 'cav_oferta_dados', 'Dados da oferta', [ __CLASS__, 'render_metabox' ], self::CPT, 'normal', 'high' );
	}

	public static function render_metabox( $post ) {
		$tipo  = get_post_meta( $post->ID, self::META_TIPO, true ) ?: 'produto';
		$de    = get_post_meta( $post->ID, self::META_DE, true );
		$por   = get_post_meta( $post->ID, self::META_POR, true );
		$selo  = get_post_meta( $post->ID, self::META_SELO, true );
		$fim   = get_post_meta( $post->ID, self::META_FIM, true );
		$como  = get_post_meta( $post->ID, self::META_COMO, true );
		$ativo = get_post_meta( $post->ID, self::META_ATIVO, true );
		$ativo = ( '' === $ativo ) ? '1' : $ativo;

		wp_nonce_field( 'cav_salvar_oferta', 'cav_oferta_nonce' );
		?>
		<p>
			<label><strong>Tipo</strong></label><br>
			<select name="cav_of_tipo">
				<?php foreach ( self::tipos() as $valor => $rotulo ) : ?>
					<option value="<?php echo esc_attr( $valor ); ?>" <?php selected( $tipo, $valor ); ?>><?php echo esc_html( $rotulo ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label style="margin-right:1.5rem">
				<strong>Preço sem desconto</strong> (R$)<br>
				<input type="text" name="cav_of_de" value="<?php echo esc_attr( self::formatar_campo( $de ) ); ?>" placeholder="389,90" size="12">
			</label>
			<label>
				<strong>Preço para o clube</strong> (R$)<br>
				<input type="text" name="cav_of_por" value="<?php echo esc_attr( self::formatar_campo( $por ) ); ?>" placeholder="329,90" size="12">
			</label><br>
			<span class="description">Para produto, preencha os dois e a porcentagem aparece sozinha. Para desconto sem preço, deixe em branco e use o selo.</span>
		</p>
		<p>
			<label><strong>Selo</strong> (opcional)</label><br>
			<input type="text" name="cav_of_selo" value="<?php echo esc_attr( $selo ); ?>" placeholder="Ex.: 10% OFF, 1 aula grátis" style="width:100%;max-width:320px">
		</p>
		<p>
			<label><strong>Como pegar</strong></label><br>
			<input type="text" name="cav_of_como" value="<?php echo esc_attr( $como ); ?>" placeholder="Padrão: Peça na recepção da academia" style="width:100%;max-width:520px">
		</p>
		<p>
			<label><strong>Vale até</strong></label>
			<input type="date" name="cav_of_fim" value="<?php echo esc_attr( $fim ); ?>"><br>
			<span class="description">Em branco, a oferta fica no ar até você desativar. Depois da data ela some sozinha.</span>
		</p>
		<p>
			<label><input type="checkbox" name="cav_of_ativo" value="1" <?php checked( $ativo, '1' ); ?>> <strong>Oferta ativa</strong></label>
		</p>
		<p class="description">A foto é a Imagem destacada (coluna da direita). O texto do campo principal aparece como descrição.</p>
		<?php
	}

	public static function salvar( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['cav_oferta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cav_oferta_nonce'] ) ), 'cav_salvar_oferta' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$tipo = isset( $_POST['cav_of_tipo'] ) ? sanitize_key( wp_unslash( $_POST['cav_of_tipo'] ) ) : 'produto';
		update_post_meta( $post_id, self::META_TIPO, array_key_exists( $tipo, self::tipos() ) ? $tipo : 'produto' );

		foreach ( [ 'cav_of_de' => self::META_DE, 'cav_of_por' => self::META_POR ] as $campo => $meta ) {
			$valor = isset( $_POST[ $campo ] ) ? self::ler_preco( sanitize_text_field( wp_unslash( $_POST[ $campo ] ) ) ) : null;
			update_post_meta( $post_id, $meta, null === $valor ? '' : number_format( $valor, 2, '.', '' ) );
		}

		update_post_meta( $post_id, self::META_SELO, isset( $_POST['cav_of_selo'] ) ? sanitize_text_field( wp_unslash( $_POST['cav_of_selo'] ) ) : '' );
		update_post_meta( $post_id, self::META_COMO, isset( $_POST['cav_of_como'] ) ? sanitize_text_field( wp_unslash( $_POST['cav_of_como'] ) ) : '' );

		$fim = isset( $_POST['cav_of_fim'] ) ? sanitize_text_field( wp_unslash( $_POST['cav_of_fim'] ) ) : '';
		update_post_meta( $post_id, self::META_FIM, preg_match( '/^\d{4}-\d{2}-\d{2}$/', $fim ) ? $fim : '' );

		update_post_meta( $post_id, self::META_ATIVO, empty( $_POST['cav_of_ativo'] ) ? '0' : '1' );
	}

	/* ------------------------------- Preços ------------------------------- */

	/** "R$ 1.389,90", "389,90" ou "389.90" viram float; vazio ou inválido vira null. */
	public static function ler_preco( $texto ) {
		$t = trim( str_replace( [ 'R$', ' ', "\xc2\xa0" ], '', (string) $texto ) );
		if ( '' === $t ) {
			return null;
		}
		if ( false !== strpos( $t, ',' ) ) {
			$t = str_replace( '.', '', $t );
			$t = str_replace( ',', '.', $t );
		}
		if ( ! is_numeric( $t ) || (float) $t < 0 ) {
			return null;
		}
		return (float) $t;
	}

	public static function formatar( $valor ) {
		return 'R$ ' . number_format( (float) $valor, 2, ',', '.' );
	}

	private static function formatar_campo( $guardado ) {
		return '' === $guardado || null === $guardado ? '' : number_format( (float) $guardado, 2, ',', '' );
	}

	/** Porcentagem de desconto entre dois preços, ou 0. */
	public static function porcentagem( $de, $por ) {
		$de  = (float) $de;
		$por = (float) $por;
		if ( $de <= 0 || $por <= 0 || $por >= $de ) {
			return 0;
		}
		return (int) round( ( 1 - $por / $de ) * 100 );
	}

	/* ------------------------------ Consulta ------------------------------ */

	public static function vigente( $post_id ) {
		$fim = get_post_meta( $post_id, self::META_FIM, true );
		return ! $fim || current_time( 'Y-m-d' ) <= $fim;
	}

	/** Ofertas no ar, mais novas primeiro. */
	public static function listar( $tipo = '', $qtd = 24 ) {
		$meta = [
			'relation' => 'OR',
			[ 'key' => self::META_ATIVO, 'value' => '1' ],
			[ 'key' => self::META_ATIVO, 'compare' => 'NOT EXISTS' ],
		];

		$consulta = [
			'post_type'      => self::CPT,
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => [ $meta ],
		];

		if ( $tipo ) {
			$consulta['meta_query'][] = [ 'key' => self::META_TIPO, 'value' => $tipo ];
		}

		$posts = array_values( array_filter( get_posts( $consulta ), function ( $p ) {
			return self::vigente( $p->ID );
		} ) );

		return array_slice( $posts, 0, max( 1, (int) $qtd ) );
	}

	/* ------------------------------- Cartão ------------------------------- */

	public static function cartao( $post ) {
		$tipo = get_post_meta( $post->ID, self::META_TIPO, true ) ?: 'produto';
		$de   = get_post_meta( $post->ID, self::META_DE, true );
		$por  = get_post_meta( $post->ID, self::META_POR, true );
		$fim  = get_post_meta( $post->ID, self::META_FIM, true );
		$como = get_post_meta( $post->ID, self::META_COMO, true ) ?: 'Peça na recepção da academia';

		$selo = get_post_meta( $post->ID, self::META_SELO, true );
		if ( '' === $selo ) {
			$pct  = self::porcentagem( $de, $por );
			$selo = $pct ? '-' . $pct . '%' : '';
		}

		$resumo = $post->post_excerpt ?: wp_strip_all_tags( $post->post_content );
		$resumo = wp_trim_words( $resumo, 28 );

		ob_start();
		?>
		<article class="cav-oferta cav-oferta--<?php echo esc_attr( $tipo ); ?>">
			<?php if ( 'produto' === $tipo ) : ?>
				<div class="cav-oferta__foto">
					<?php if ( has_post_thumbnail( $post ) ) : ?>
						<?php echo get_the_post_thumbnail( $post, 'medium_large', [ 'loading' => 'lazy' ] ); ?>
					<?php else : ?>
						<span class="cav-oferta__inicial" aria-hidden="true"><?php echo esc_html( mb_strtoupper( mb_substr( get_the_title( $post ), 0, 1 ) ) ); ?></span>
					<?php endif; ?>
					<?php if ( $selo ) : ?>
						<span class="cav-oferta__selo"><?php echo esc_html( $selo ); ?></span>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="cav-oferta__corpo">
				<?php if ( 'desconto' === $tipo && $selo ) : ?>
					<span class="cav-oferta__selo-grande"><?php echo esc_html( $selo ); ?></span>
				<?php endif; ?>

				<h3 class="cav-oferta__titulo"><?php echo esc_html( get_the_title( $post ) ); ?></h3>

				<?php if ( $resumo ) : ?>
					<p class="cav-oferta__resumo"><?php echo esc_html( $resumo ); ?></p>
				<?php endif; ?>

				<?php if ( '' !== $por ) : ?>
					<p class="cav-oferta__precos">
						<?php if ( '' !== $de ) : ?>
							<span class="cav-oferta__de"><span class="cav-so-leitor">De </span><s><?php echo esc_html( self::formatar( $de ) ); ?></s></span>
						<?php endif; ?>
						<span class="cav-oferta__por"><span class="cav-so-leitor">Por </span><?php echo esc_html( self::formatar( $por ) ); ?></span>
					</p>
				<?php endif; ?>

				<p class="cav-oferta__como">
					<?php echo esc_html( $como ); ?>
					<?php if ( $fim ) : ?>
						<span> · até <?php echo esc_html( mysql2date( 'd/m/Y', $fim . ' 00:00:00' ) ); ?></span>
					<?php endif; ?>
				</p>
			</div>
		</article>
		<?php
		return ob_get_clean();
	}
}

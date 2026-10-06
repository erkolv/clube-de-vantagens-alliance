<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CAV_Parceiro {

	const META_USER_PARCEIRO = 'cav_parceiro_id';   // no usuário: ID do post do parceiro
	const META_BEN_PARCEIRO  = '_cav_parceiro_id';  // no benefício: ID do post do parceiro
	const META_BEN_REGRA     = '_cav_regra';
	const META_BEN_LIMITE    = '_cav_limite_periodo'; // nenhum | dia | semana | mes
	const META_BEN_ATIVO     = '_cav_ativo';
	const META_BEN_INICIO    = '_cav_vigencia_inicio'; // vazio = já vale
	const META_BEN_FIM       = '_cav_vigencia_fim';    // vazio = não expira

	public static function init() {
		add_action( 'init', [ __CLASS__, 'registrar_cpts' ] );
		add_action( 'add_meta_boxes', [ __CLASS__, 'metabox' ] );
		add_action( 'save_post_cav_beneficio', [ __CLASS__, 'salvar_beneficio' ], 10, 2 );

		add_action( 'show_user_profile', [ __CLASS__, 'campo_vinculo' ] );
		add_action( 'edit_user_profile', [ __CLASS__, 'campo_vinculo' ] );
		add_action( 'personal_options_update', [ __CLASS__, 'salvar_vinculo' ] );
		add_action( 'edit_user_profile_update', [ __CLASS__, 'salvar_vinculo' ] );
	}

	public static function registrar_cpts() {
		// Só registra o CPT próprio se ninguém apontou para outro post type.
		if ( 'cav_parceiro' === cav_parceiro_post_type() ) {
			register_post_type( 'cav_parceiro', [
				'labels' => [
					'name'          => 'Parceiros',
					'singular_name' => 'Parceiro',
					'add_new_item'  => 'Adicionar parceiro',
					'edit_item'     => 'Editar parceiro',
				],
				'public'       => true,
				'has_archive'  => true,
				'menu_icon'    => 'dashicons-store',
				'supports'     => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
				'rewrite'      => [ 'slug' => 'parceiros' ],
				'show_in_rest' => true,
			] );

			register_taxonomy( 'cav_categoria', 'cav_parceiro', [
				'labels'       => [ 'name' => 'Categorias', 'singular_name' => 'Categoria' ],
				'hierarchical' => true,
				'public'       => true,
				'rewrite'      => [ 'slug' => 'categoria-parceiro' ],
				'show_in_rest' => true,
			] );
		}

		register_post_type( 'cav_beneficio', [
			'labels' => [
				'name'          => 'Benefícios',
				'singular_name' => 'Benefício',
				'add_new_item'  => 'Adicionar benefício',
				'edit_item'     => 'Editar benefício',
			],
			'public'       => true,
			'has_archive'  => false,
			'menu_icon'    => 'dashicons-tag',
			'supports'     => [ 'title', 'editor', 'thumbnail' ],
			'rewrite'      => [ 'slug' => 'beneficios' ],
			'show_in_rest' => true,
		] );
	}

	public static function metabox() {
		add_meta_box( 'cav_beneficio_dados', 'Dados do benefício', [ __CLASS__, 'render_metabox' ], 'cav_beneficio', 'normal', 'high' );
	}

	public static function render_metabox( $post ) {
		$parceiro = get_post_meta( $post->ID, self::META_BEN_PARCEIRO, true );
		$regra    = get_post_meta( $post->ID, self::META_BEN_REGRA, true );
		$limite   = get_post_meta( $post->ID, self::META_BEN_LIMITE, true ) ?: 'nenhum';
		$ativo    = get_post_meta( $post->ID, self::META_BEN_ATIVO, true );
		$ativo    = ( '' === $ativo ) ? '1' : $ativo;
		$inicio   = get_post_meta( $post->ID, self::META_BEN_INICIO, true );
		$fim      = get_post_meta( $post->ID, self::META_BEN_FIM, true );

		$parceiros = get_posts( [
			'post_type'      => cav_parceiro_post_type(),
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => [ 'publish', 'draft', 'pending' ],
		] );

		wp_nonce_field( 'cav_salvar_beneficio', 'cav_beneficio_nonce' );
		?>
		<p>
			<label><strong>Parceiro</strong></label><br>
			<select name="cav_parceiro_id" style="width:100%;max-width:420px">
				<option value="">— selecione —</option>
				<?php foreach ( $parceiros as $p ) : ?>
					<option value="<?php echo esc_attr( $p->ID ); ?>" <?php selected( $parceiro, $p->ID ); ?>>
						<?php echo esc_html( $p->post_title ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label><strong>Regra</strong> — o que o parceiro vê no terminal</label><br>
			<input type="text" name="cav_regra" style="width:100%;max-width:600px"
			       value="<?php echo esc_attr( $regra ); ?>"
			       placeholder="Ex.: 15% de desconto no corte, de segunda a quinta">
		</p>
		<p>
			<label><strong>Limite por membro</strong></label><br>
			<select name="cav_limite_periodo">
				<option value="nenhum" <?php selected( $limite, 'nenhum' ); ?>>Sem limite</option>
				<option value="dia"    <?php selected( $limite, 'dia' ); ?>>1x por dia</option>
				<option value="semana" <?php selected( $limite, 'semana' ); ?>>1x por semana</option>
				<option value="mes"    <?php selected( $limite, 'mes' ); ?>>1x por mês</option>
			</select>
		</p>
		<p>
			<label><strong>Vigência</strong></label><br>
			<label style="margin-right:1.5rem">
				Começa em
				<input type="date" name="cav_vigencia_inicio" value="<?php echo esc_attr( $inicio ); ?>">
			</label>
			<label>
				Termina em
				<input type="date" name="cav_vigencia_fim" value="<?php echo esc_attr( $fim ); ?>">
			</label>
			<br>
			<span class="description">
				Deixe em branco para benefício permanente. Preencha só o fim para uma campanha
				com data para acabar — depois dela o benefício some sozinho do terminal.
			</span>
		</p>
		<p>
			<label>
				<input type="checkbox" name="cav_ativo" value="1" <?php checked( $ativo, '1' ); ?>>
				<strong>Benefício ativo</strong>
			</label>
			<br>
			<span class="description">Desmarque para tirar do ar imediatamente, independente da vigência.</span>
		</p>
		<?php
	}

	public static function salvar_beneficio( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['cav_beneficio_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cav_beneficio_nonce'] ) ), 'cav_salvar_beneficio' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		update_post_meta( $post_id, self::META_BEN_PARCEIRO, isset( $_POST['cav_parceiro_id'] ) ? absint( $_POST['cav_parceiro_id'] ) : 0 );
		update_post_meta( $post_id, self::META_BEN_REGRA, isset( $_POST['cav_regra'] ) ? sanitize_text_field( wp_unslash( $_POST['cav_regra'] ) ) : '' );

		$limite = isset( $_POST['cav_limite_periodo'] ) ? sanitize_text_field( wp_unslash( $_POST['cav_limite_periodo'] ) ) : 'nenhum';
		if ( ! in_array( $limite, [ 'nenhum', 'dia', 'semana', 'mes' ], true ) ) {
			$limite = 'nenhum';
		}
		update_post_meta( $post_id, self::META_BEN_LIMITE, $limite );
		update_post_meta( $post_id, self::META_BEN_ATIVO, isset( $_POST['cav_ativo'] ) ? '1' : '' );

		foreach ( [ 'cav_vigencia_inicio' => self::META_BEN_INICIO, 'cav_vigencia_fim' => self::META_BEN_FIM ] as $campo => $meta ) {
			$valor = isset( $_POST[ $campo ] ) ? sanitize_text_field( wp_unslash( $_POST[ $campo ] ) ) : '';
			if ( $valor && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $valor ) ) {
				$valor = '';
			}
			update_post_meta( $post_id, $meta, $valor );
		}
	}

	/** ID do post de parceiro vinculado ao usuário logado (ou 0). */
	public static function parceiro_do_usuario( $user_id ) {
		return (int) get_user_meta( $user_id, self::META_USER_PARCEIRO, true );
	}

	/**
	 * O benefício está dentro da vigência hoje?
	 * Datas em branco = permanente, então o padrão é sempre true.
	 */
	public static function em_vigencia( $beneficio_id ) {
		$hoje   = current_time( 'Y-m-d' );
		$inicio = get_post_meta( $beneficio_id, self::META_BEN_INICIO, true );
		$fim    = get_post_meta( $beneficio_id, self::META_BEN_FIM, true );

		if ( $inicio && $hoje < $inicio ) {
			return false;
		}
		if ( $fim && $hoje > $fim ) {
			return false;
		}
		return true;
	}

	/** Texto curto da vigência, para exibir ao parceiro. */
	public static function rotulo_vigencia( $beneficio_id ) {
		$inicio = get_post_meta( $beneficio_id, self::META_BEN_INICIO, true );
		$fim    = get_post_meta( $beneficio_id, self::META_BEN_FIM, true );

		if ( ! $inicio && ! $fim ) {
			return 'Permanente';
		}
		if ( $fim && ! $inicio ) {
			return 'Até ' . mysql2date( 'd/m/Y', $fim . ' 00:00:00' );
		}
		if ( $inicio && ! $fim ) {
			return 'A partir de ' . mysql2date( 'd/m/Y', $inicio . ' 00:00:00' );
		}
		return mysql2date( 'd/m', $inicio . ' 00:00:00' ) . ' a ' . mysql2date( 'd/m/Y', $fim . ' 00:00:00' );
	}

	/**
	 * Benefícios de um parceiro disponíveis agora.
	 *
	 * @param bool $so_vigentes false traz também os fora de vigência (para o admin).
	 */
	public static function beneficios( $parceiro_id, $so_vigentes = true ) {
		$posts = get_posts( [
			'post_type'      => 'cav_beneficio',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
			'meta_query'     => [
				[ 'key' => self::META_BEN_PARCEIRO, 'value' => (int) $parceiro_id ],
				[ 'key' => self::META_BEN_ATIVO, 'value' => '1' ],
			],
		] );

		if ( ! $so_vigentes ) {
			return $posts;
		}

		$posts = array_values( array_filter( $posts, function ( $p ) {
			return self::em_vigencia( $p->ID );
		} ) );

		// Campanha com data para acabar vem antes do permanente:
		// no balcão, o desconto maior costuma ser o temporário.
		usort( $posts, function ( $a, $b ) {
			$ta = self::e_temporario( $a->ID ) ? 0 : 1;
			$tb = self::e_temporario( $b->ID ) ? 0 : 1;
			if ( $ta !== $tb ) {
				return $ta - $tb;
			}
			return strcasecmp( $a->post_title, $b->post_title );
		} );

		return $posts;
	}

	/** Tem data para acabar? */
	public static function e_temporario( $beneficio_id ) {
		return (bool) get_post_meta( $beneficio_id, self::META_BEN_FIM, true );
	}

	public static function campo_vinculo( $user ) {
		if ( ! current_user_can( 'edit_users' ) ) {
			return;
		}
		$atual = self::parceiro_do_usuario( $user->ID );
		$posts = get_posts( [
			'post_type'      => cav_parceiro_post_type(),
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => [ 'publish', 'draft', 'pending' ],
		] );
		?>
		<h2>Vínculo com parceiro</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="cav_parceiro_vinculo">Estabelecimento</label></th>
				<td>
					<select name="cav_parceiro_vinculo" id="cav_parceiro_vinculo">
						<option value="0">— nenhum —</option>
						<?php foreach ( $posts as $p ) : ?>
							<option value="<?php echo esc_attr( $p->ID ); ?>" <?php selected( $atual, $p->ID ); ?>>
								<?php echo esc_html( $p->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">Define qual estabelecimento este usuário opera no terminal.</p>
				</td>
			</tr>
		</table>
		<?php wp_nonce_field( 'cav_salvar_vinculo', 'cav_vinculo_nonce' ); ?>
		<?php
	}

	public static function salvar_vinculo( $user_id ) {
		if ( ! current_user_can( 'edit_users' ) ) {
			return;
		}
		if ( ! isset( $_POST['cav_vinculo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cav_vinculo_nonce'] ) ), 'cav_salvar_vinculo' ) ) {
			return;
		}
		update_user_meta( $user_id, self::META_USER_PARCEIRO, isset( $_POST['cav_parceiro_vinculo'] ) ? absint( $_POST['cav_parceiro_vinculo'] ) : 0 );
	}
}

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CAV_Conteudo {

	const META_INICIO   = '_cav_inscricao_inicio';
	const META_FIM      = '_cav_inscricao_fim';
	const META_PREMIO   = '_cav_premio';
	const META_GANHADOR = '_cav_ganhador';
	const META_APURADO  = '_cav_apurado_em';

	public static function init() {
		add_action( 'init', [ __CLASS__, 'registrar_cpts' ] );
		add_action( 'add_meta_boxes', [ __CLASS__, 'metaboxes' ] );
		add_action( 'save_post_cav_conteudo', [ __CLASS__, 'salvar_conteudo' ] );
		add_action( 'save_post_cav_sorteio', [ __CLASS__, 'salvar_sorteio' ] );
		add_action( 'admin_post_cav_apurar', [ __CLASS__, 'apurar' ] );
	}

	public static function registrar_cpts() {
		register_post_type( 'cav_conteudo', [
			'labels' => [
				'name'          => 'Conteúdos exclusivos',
				'singular_name' => 'Conteúdo',
				'add_new_item'  => 'Adicionar conteúdo',
				'edit_item'     => 'Editar conteúdo',
			],
			'public'      => true,
			'has_archive' => true,
			'menu_icon'   => 'dashicons-lock',
			'supports'    => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
			'rewrite'     => [ 'slug' => 'exclusivo' ],
		] );

		register_post_type( 'cav_sorteio', [
			'labels' => [
				'name'          => 'Sorteios',
				'singular_name' => 'Sorteio',
				'add_new_item'  => 'Adicionar sorteio',
				'edit_item'     => 'Editar sorteio',
			],
			'public'      => true,
			'has_archive' => true,
			'menu_icon'   => 'dashicons-tickets-alt',
			'supports'    => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
			'rewrite'     => [ 'slug' => 'sorteios' ],
		] );
	}

	public static function metaboxes() {
		add_meta_box( 'cav_publico', 'Quem pode ver', [ __CLASS__, 'box_publico' ], [ 'cav_conteudo', 'cav_sorteio' ], 'side', 'high' );
		add_meta_box( 'cav_sorteio_dados', 'Dados do sorteio', [ __CLASS__, 'box_sorteio' ], 'cav_sorteio', 'normal', 'high' );
	}

	public static function box_publico( $post ) {
		$atual = CAV_Acesso::publico_do_post( $post->ID );
		wp_nonce_field( 'cav_salvar_publico', 'cav_publico_nonce' );
		foreach ( [ 'membros', 'parceiros', 'ambos' ] as $op ) {
			printf(
				'<p><label><input type="radio" name="cav_publico" value="%s" %s> %s</label></p>',
				esc_attr( $op ),
				checked( $atual, $op, false ),
				esc_html( CAV_Acesso::rotulo_publico( $op ) )
			);
		}
		echo '<p class="description">Quem não se encaixa não vê o item nem nas listagens.</p>';
	}

	public static function box_sorteio( $post ) {
		$inicio   = get_post_meta( $post->ID, self::META_INICIO, true );
		$fim      = get_post_meta( $post->ID, self::META_FIM, true );
		$premio   = get_post_meta( $post->ID, self::META_PREMIO, true );
		$ganhador = (int) get_post_meta( $post->ID, self::META_GANHADOR, true );
		$apurado  = get_post_meta( $post->ID, self::META_APURADO, true );
		$inscritos = CAV_Sorteio::contar( $post->ID );

		wp_nonce_field( 'cav_salvar_sorteio', 'cav_sorteio_nonce' );
		?>
		<p>
			<label><strong>Prêmio</strong></label><br>
			<input type="text" name="cav_premio" style="width:100%;max-width:600px"
			       value="<?php echo esc_attr( $premio ); ?>"
			       placeholder="Ex.: 1 kimono Alliance + 1 mês de mensalidade">
		</p>
		<p>
			<label><strong>Inscrições</strong></label><br>
			<label style="margin-right:1.5rem">De
				<input type="date" name="cav_inscricao_inicio" value="<?php echo esc_attr( $inicio ); ?>">
			</label>
			<label>Até
				<input type="date" name="cav_inscricao_fim" value="<?php echo esc_attr( $fim ); ?>">
			</label>
			<br>
			<span class="description">Fora desse período o botão de participar não aparece.</span>
		</p>
		<hr>
		<p>
			<strong><?php echo esc_html( $inscritos ); ?></strong> inscrito(s).
			<?php if ( $ganhador ) :
				$g = get_userdata( $ganhador ); ?>
				<br><strong>Ganhador:</strong> <?php echo esc_html( $g ? $g->display_name : '#' . $ganhador ); ?>
				<?php if ( $apurado ) : ?>
					<span class="description">— apurado em <?php echo esc_html( mysql2date( 'd/m/Y H:i', $apurado ) ); ?></span>
				<?php endif; ?>
			<?php elseif ( $inscritos > 0 && $post->post_status === 'publish' ) : ?>
				<br>
				<a class="button button-primary" style="margin-top:.5rem"
				   href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cav_apurar&sorteio=' . $post->ID ), 'cav_apurar_' . $post->ID ) ); ?>"
				   onclick="return confirm('Sortear agora? A apuração é registrada e não pode ser refeita.');">
					Sortear ganhador
				</a>
			<?php endif; ?>
		</p>
		<?php
	}

	private static function salvar_publico( $post_id ) {
		if ( ! isset( $_POST['cav_publico_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cav_publico_nonce'] ) ), 'cav_salvar_publico' ) ) {
			return false;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}
		$p = isset( $_POST['cav_publico'] ) ? sanitize_text_field( wp_unslash( $_POST['cav_publico'] ) ) : 'membros';
		if ( ! in_array( $p, [ 'membros', 'parceiros', 'ambos' ], true ) ) {
			$p = 'membros';
		}
		update_post_meta( $post_id, CAV_Acesso::META_PUBLICO, $p );
		return true;
	}

	public static function salvar_conteudo( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		self::salvar_publico( $post_id );
	}

	public static function salvar_sorteio( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		self::salvar_publico( $post_id );

		if ( ! isset( $_POST['cav_sorteio_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cav_sorteio_nonce'] ) ), 'cav_salvar_sorteio' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		update_post_meta( $post_id, self::META_PREMIO, isset( $_POST['cav_premio'] ) ? sanitize_text_field( wp_unslash( $_POST['cav_premio'] ) ) : '' );

		foreach ( [ 'cav_inscricao_inicio' => self::META_INICIO, 'cav_inscricao_fim' => self::META_FIM ] as $campo => $meta ) {
			$v = isset( $_POST[ $campo ] ) ? sanitize_text_field( wp_unslash( $_POST[ $campo ] ) ) : '';
			if ( $v && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ) {
				$v = '';
			}
			update_post_meta( $post_id, $meta, $v );
		}
	}

	/** Inscrições abertas hoje? */
	public static function inscricoes_abertas( $sorteio_id ) {
		if ( get_post_meta( $sorteio_id, self::META_GANHADOR, true ) ) {
			return false;
		}
		$hoje   = current_time( 'Y-m-d' );
		$inicio = get_post_meta( $sorteio_id, self::META_INICIO, true );
		$fim    = get_post_meta( $sorteio_id, self::META_FIM, true );

		if ( $inicio && $hoje < $inicio ) {
			return false;
		}
		if ( $fim && $hoje > $fim ) {
			return false;
		}
		return true;
	}

	public static function apurar() {
		$sorteio_id = isset( $_GET['sorteio'] ) ? absint( $_GET['sorteio'] ) : 0;

		if ( ! $sorteio_id || ! current_user_can( 'cav_gerenciar' ) ) {
			wp_die( 'Sem permissão.' );
		}
		check_admin_referer( 'cav_apurar_' . $sorteio_id );

		if ( get_post_meta( $sorteio_id, self::META_GANHADOR, true ) ) {
			wp_die( 'Este sorteio já foi apurado.' );
		}

		$ganhador = CAV_Sorteio::sortear( $sorteio_id );

		if ( ! $ganhador ) {
			wp_die( 'Nenhum inscrito para sortear.' );
		}

		update_post_meta( $sorteio_id, self::META_GANHADOR, $ganhador );
		update_post_meta( $sorteio_id, self::META_APURADO, current_time( 'mysql' ) );

		// Avisa o ganhador. Se o e-mail falhar, o resultado continua valendo e aparece na área dele.
		CAV_Emails::sorteio_ganhador( $sorteio_id, $ganhador );
		do_action( 'cav_sorteio_apurado', $sorteio_id, $ganhador );

		wp_safe_redirect( get_edit_post_link( $sorteio_id, 'url' ) );
		exit;
	}
}

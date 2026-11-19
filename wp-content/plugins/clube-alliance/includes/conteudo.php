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
	const META_REFEITOS = '_cav_sorteio_refeito'; // histórico: igual a CAV_Sorteio::META_HISTORICO

	public static function init() {
		add_action( 'init', [ __CLASS__, 'registrar_cpts' ] );
		add_action( 'add_meta_boxes', [ __CLASS__, 'metaboxes' ] );
		add_action( 'save_post_cav_conteudo', [ __CLASS__, 'salvar_conteudo' ] );
		add_action( 'save_post_cav_sorteio', [ __CLASS__, 'salvar_sorteio' ] );
		add_action( 'admin_post_cav_apurar', [ __CLASS__, 'apurar' ] );
		add_action( 'admin_post_cav_refazer_sorteio', [ __CLASS__, 'refazer' ] );
		add_action( 'admin_post_cav_confirmar_sorteio', [ __CLASS__, 'confirmar' ] );
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
			// Conteúdo de membro não aparece na busca do site nem no mapa do site.
			'exclude_from_search' => true,
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
			'exclude_from_search' => true,
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
		$inscritos = count( CAV_Sorteio::participantes_do_sorteio( $post->ID ) );
		$tirados   = count( array_intersect( CAV_Sorteio::excluidos( $post->ID ), CAV_Sorteio::participantes() ) );

		wp_nonce_field( 'cav_salvar_sorteio', 'cav_sorteio_nonce' );
		?>
		<p>
			<label><strong>Prêmio</strong></label><br>
			<input type="text" name="cav_premio" style="width:100%;max-width:600px"
			       value="<?php echo esc_attr( $premio ); ?>"
			       placeholder="Ex.: 1 kimono Alliance + 1 mês de mensalidade">
		</p>
		<p>
			<label><strong>Período do sorteio</strong></label><br>
			<label style="margin-right:1.5rem">De
				<input type="date" name="cav_inscricao_inicio" value="<?php echo esc_attr( $inicio ); ?>">
			</label>
			<label>Até
				<input type="date" name="cav_inscricao_fim" value="<?php echo esc_attr( $fim ); ?>">
			</label>
			<br>
			<span class="description">Dentro desse período o sorteio aparece como "em andamento" para os alunos. Não há inscrição: todo membro com a matrícula ativa na hora do sorteio participa.</span>
		</p>
		<hr>
		<p>
			<strong><?php echo esc_html( $inscritos ); ?></strong> participante(s) agora (membros com matrícula ativa).
			<?php if ( $tirados ) : ?>
				<span class="description">(<?php echo (int) $tirados; ?> tirado(s) da lista)</span>
			<?php endif; ?>
			<?php if ( current_user_can( 'cav_gerenciar' ) && 'publish' === $post->post_status ) : ?>
				<br><a href="<?php echo esc_url( admin_url( 'admin.php?page=cav-sorteio-ao-vivo&sorteio=' . $post->ID ) ); ?>">Sorteio ao vivo: escolher quem participa e abrir a tela do sorteio</a>
			<?php endif; ?>
			<?php if ( $ganhador ) :
				$g = get_userdata( $ganhador ); ?>
				<br><strong>Ganhador:</strong> <?php echo esc_html( $g ? $g->display_name : '#' . $ganhador ); ?>
				<?php if ( $apurado ) : ?>
					<span class="description">— apurado em <?php echo esc_html( mysql2date( 'd/m/Y H:i', $apurado ) ); ?></span>
				<?php endif; ?>
				<?php self::historico_e_refazer( $post ); ?>
			<?php elseif ( $inscritos > 0 && $post->post_status === 'publish' ) : ?>
				<br>
				<a class="button button-primary" style="margin-top:.5rem"
				   href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cav_apurar&sorteio=' . $post->ID ), 'cav_apurar_' . $post->ID ) ); ?>"
				   onclick="return confirm('Sortear agora? Depois você confere o nome e confirma o resultado.');">
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

	/** Histórico, confirmação e "sortear de novo" (só administração). */
	private static function historico_e_refazer( $post ) {
		$hist = get_post_meta( $post->ID, CAV_Sorteio::META_HISTORICO, true );
		$hist = is_array( $hist ) ? $hist : [];
		$pend = CAV_Sorteio::pendente( $post->ID );
		$pode = current_user_can( 'cav_gerenciar' ) && 'publish' === $post->post_status;
		$conf = get_post_meta( $post->ID, CAV_Sorteio::META_CONFIRMADO, true );

		if ( $pend ) {
			echo '<div style="margin-top:1rem;padding:.9rem 1rem;background:#fff8e5;border-left:4px solid #dba617">';
			echo '<strong>Aguardando sua confirmação.</strong> O ganhador ainda não foi avisado e o resultado não aparece para os alunos.';
			if ( $pode ) {
				printf(
					'<div style="margin-top:.75rem;display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-start">
						<button type="submit" form="cav-confirmar-%1$d" class="button button-primary" onclick="return confirm(\'Confirmar o resultado? O ganhador recebe o e-mail e o resultado aparece no site.\');">Confirmar e publicar o resultado</button>
						<button type="submit" form="cav-refazer-%1$d" class="button" onclick="return confirm(\'Sortear de novo? Este ganhador sai e um novo nome é sorteado.\');">Sortear de novo</button>
						<input type="text" form="cav-refazer-%1$d" name="cav_motivo" placeholder="Motivo (opcional)" style="min-width:240px">
					</div>',
					(int) $post->ID
				);
			}
			echo '</div>';
		} elseif ( is_array( $conf ) && ! empty( $conf['em'] ) ) {
			$quem = get_userdata( (int) ( $conf['por'] ?? 0 ) );
			echo '<br><span class="description" style="color:#1a7f37">Resultado confirmado em ' . esc_html( mysql2date( 'd/m/Y H:i', $conf['em'] ) ) . ( $quem ? ' por ' . esc_html( $quem->display_name ) : '' ) . '.</span>';
		}

		if ( $hist ) {
			echo '<div style="margin-top:1rem;padding:.75rem 1rem;background:#f6f7f7;border-left:4px solid #8c8f94">';
			echo '<strong>Sorteado de novo ' . count( $hist ) . 'x</strong>';
			echo '<ul style="margin:.5rem 0 0;list-style:disc;padding-left:1.25rem">';
			foreach ( $hist as $h ) {
				$g   = get_userdata( (int) $h['ganhador'] );
				$por = get_userdata( (int) $h['por'] );
				printf(
					'<li>%s, sorteado em %s%s. Sorteado de novo em %s por %s. Motivo: %s</li>',
					esc_html( $g ? $g->display_name : '#' . (int) $h['ganhador'] ),
					esc_html( $h['apurado_em'] ? mysql2date( 'd/m/Y H:i', $h['apurado_em'] ) : '—' ),
					empty( $h['confirmado'] ) ? ' (não confirmado)' : ' (já tinha sido confirmado)',
					esc_html( mysql2date( 'd/m/Y H:i', $h['em'] ) ),
					esc_html( $por ? $por->display_name : '—' ),
					esc_html( $h['motivo'] )
				);
			}
			echo '</ul></div>';
		}

		if ( ! $pode ) {
			return;
		}

		if ( ! $pend ) {
			?>
			<details style="margin-top:1rem">
				<summary style="cursor:pointer;color:#b32d2e">Refazer o sorteio</summary>
				<div style="margin-top:.75rem">
					<p class="description" style="margin-top:0">O resultado já foi confirmado e o ganhador avisado. Use só se ele não puder receber o prêmio (desistiu, não apareceu, saiu da academia). O ganhador atual fica registrado aqui com o motivo, e o novo resultado também precisa ser confirmado.</p>
					<textarea form="cav-refazer-<?php echo (int) $post->ID; ?>" name="cav_motivo" rows="2" style="width:100%;max-width:600px" placeholder="Motivo (obrigatório). Ex.: não retirou o prêmio até 30/11" required></textarea>
					<br>
					<button type="submit" form="cav-refazer-<?php echo (int) $post->ID; ?>" class="button" style="margin-top:.5rem"
					        onclick="return confirm('Refazer o sorteio? O ganhador atual sai e um novo nome é sorteado entre os outros participantes.');">Refazer sorteio</button>
				</div>
			</details>
			<?php
		}

		// Os formulários ficam fora do formulário do editor (não dá para aninhar <form>): vão para o rodapé.
		add_action( 'admin_footer', static function () use ( $post ) {
			$acao = esc_url( admin_url( 'admin-post.php' ) );
			printf(
				'<form id="cav-refazer-%1$d" method="post" action="%2$s" style="display:none"><input type="hidden" name="action" value="cav_refazer_sorteio"><input type="hidden" name="sorteio" value="%1$d">%3$s</form>
				<form id="cav-confirmar-%1$d" method="post" action="%2$s" style="display:none"><input type="hidden" name="action" value="cav_confirmar_sorteio"><input type="hidden" name="sorteio" value="%1$d">%4$s</form>',
				(int) $post->ID,
				$acao,
				wp_nonce_field( 'cav_refazer_' . $post->ID, '_wpnonce', true, false ),
				wp_nonce_field( 'cav_confirmar_' . $post->ID, '_wpnonce', true, false )
			);
		}, 99 );
	}

	/** Sorteia de novo. Antes da confirmação o motivo é opcional; depois, obrigatório. */
	public static function refazer() {
		$sorteio_id = isset( $_POST['sorteio'] ) ? absint( $_POST['sorteio'] ) : 0;

		if ( ! $sorteio_id || ! current_user_can( 'cav_gerenciar' ) ) {
			wp_die( 'Sem permissão.' );
		}
		check_admin_referer( 'cav_refazer_' . $sorteio_id );

		if ( ! get_post_meta( $sorteio_id, self::META_GANHADOR, true ) ) {
			wp_die( 'Este sorteio ainda não foi apurado. Use o botão "Sortear ganhador".' );
		}
		$pend   = CAV_Sorteio::pendente( $sorteio_id );
		$motivo = isset( $_POST['cav_motivo'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['cav_motivo'] ) ) ) : '';
		if ( '' === $motivo ) {
			if ( ! $pend ) {
				wp_die( 'Escreva o motivo para refazer o sorteio.', '', [ 'back_link' => true ] );
			}
			$motivo = 'Sorteado de novo antes de confirmar';
		}

		if ( ! CAV_Sorteio::sortear_de_novo( $sorteio_id, $motivo, get_current_user_id() ) ) {
			wp_die( 'Não sobrou nenhum participante com matrícula ativa para sortear.', '', [ 'back_link' => true ] );
		}

		wp_safe_redirect( get_edit_post_link( $sorteio_id, 'url' ) );
		exit;
	}

	/** Confirma o resultado: avisa o ganhador e publica para os alunos. */
	public static function confirmar() {
		$sorteio_id = isset( $_POST['sorteio'] ) ? absint( $_POST['sorteio'] ) : 0;

		if ( ! $sorteio_id || ! current_user_can( 'cav_gerenciar' ) ) {
			wp_die( 'Sem permissão.' );
		}
		check_admin_referer( 'cav_confirmar_' . $sorteio_id );
		CAV_Sorteio::confirmar( $sorteio_id, get_current_user_id() );

		wp_safe_redirect( get_edit_post_link( $sorteio_id, 'url' ) );
		exit;
	}

	/** Sorteio em andamento hoje (dentro do período e ainda sem ganhador)? */
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

		if ( ! CAV_Sorteio::registrar( $sorteio_id, CAV_Sorteio::participantes_do_sorteio( $sorteio_id ) ) ) {
			wp_die( 'Nenhum participante com matrícula ativa para sortear.' );
		}
		// Ninguém é avisado ainda: o resultado fica aguardando a confirmação.

		wp_safe_redirect( get_edit_post_link( $sorteio_id, 'url' ) );
		exit;
	}
}

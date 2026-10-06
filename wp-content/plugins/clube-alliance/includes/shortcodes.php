<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CAV_Shortcodes {

	public static function init() {
		add_shortcode( 'cav_terminal', [ __CLASS__, 'terminal' ] );
		add_shortcode( 'cav_painel_parceiro', [ __CLASS__, 'painel_parceiro' ] );
		add_shortcode( 'cav_carteirinha', [ __CLASS__, 'carteirinha' ] );
		add_shortcode( 'cav_meus_usos', [ __CLASS__, 'meus_usos' ] );
		add_shortcode( 'cav_conteudos', [ __CLASS__, 'conteudos' ] );
		add_shortcode( 'cav_sorteios', [ __CLASS__, 'sorteios' ] );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'assets' ] );
	}

	public static function assets() {
		wp_register_style( 'cav', CAV_URL . 'assets/cav.css', [], CAV_VERSION );

		/**
		 * Sobrescreve qualquer token sem editar o plugin — sobrevive a updates.
		 * No functions.php do tema filho:
		 *
		 *   add_filter( 'cav_tokens', function ( $t ) {
		 *       $t['--cav-marca']    = '#0B0B0B';
		 *       $t['--cav-destaque'] = '#C8102E';
		 *       return $t;
		 *   } );
		 */
		$tokens = apply_filters( 'cav_tokens', [] );

		if ( $tokens ) {
			$linhas = '';
			foreach ( $tokens as $nome => $valor ) {
				$nome = preg_replace( '/[^a-z0-9\-]/i', '', (string) $nome );
				if ( '' === $nome ) {
					continue;
				}
				$linhas .= $nome . ':' . esc_attr( $valor ) . ';';
			}
			if ( $linhas ) {
				wp_add_inline_style( 'cav', '.cav-terminal,.cav-painel,.cav-carteirinha,.cav-card,.cav-tabela{' . $linhas . '}' );
			}
		}

		wp_register_script( 'cav-sorteio', CAV_URL . 'assets/cav-sorteio.js', [], CAV_VERSION, true );
		wp_localize_script( 'cav-sorteio', 'CAVS', [
			'endpoint' => esc_url_raw( rest_url( CAV_Rest::NS . '/participar' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
		] );

		wp_register_script( 'cav-terminal', CAV_URL . 'assets/cav-terminal.js', [], CAV_VERSION, true );
		wp_localize_script( 'cav-terminal', 'CAV', [
			'consulta' => esc_url_raw( rest_url( CAV_Rest::NS . '/consulta' ) ),
			'uso'      => esc_url_raw( rest_url( CAV_Rest::NS . '/uso' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
		] );
	}

	private static function aviso( $texto ) {
		wp_enqueue_style( 'cav' );
		return '<div class="cav-card cav-aviso">' . esc_html( $texto ) . '</div>';
	}

	/**
	 * Lista de conteúdos exclusivos visíveis para quem está logado.
	 * [cav_conteudos publico="parceiros" qtd="12"]
	 */
	public static function conteudos( $atts ) {
		$atts = shortcode_atts( [ 'publico' => '', 'qtd' => 12 ], $atts, 'cav_conteudos' );
		wp_enqueue_style( 'cav' );

		$publicos = self::publicos_visiveis( $atts['publico'] );
		if ( ! $publicos ) {
			return CAV_Acesso::bloqueio( $atts['publico'] ?: 'membros' );
		}

		$posts = get_posts( [
			'post_type'      => 'cav_conteudo',
			'posts_per_page' => absint( $atts['qtd'] ),
			'post_status'    => 'publish',
			'meta_query'     => [ [ 'key' => CAV_Acesso::META_PUBLICO, 'value' => $publicos, 'compare' => 'IN' ] ],
		] );

		if ( ! $posts ) {
			return '<p class="cav-vazio">Nada publicado por aqui ainda. Assim que sair conteúdo novo, ele aparece nesta página.</p>';
		}

		ob_start();
		echo '<div class="cav-grade">';
		foreach ( $posts as $p ) {
			$pub = CAV_Acesso::publico_do_post( $p->ID );
			?>
			<a class="cav-item" href="<?php echo esc_url( get_permalink( $p ) ); ?>">
				<?php if ( has_post_thumbnail( $p ) ) : ?>
					<span class="cav-item-foto"><?php echo get_the_post_thumbnail( $p, 'medium' ); ?></span>
				<?php endif; ?>
				<span class="cav-item-corpo">
					<span class="cav-item-tag"><?php echo esc_html( CAV_Acesso::rotulo_publico( $pub ) ); ?></span>
					<span class="cav-item-titulo"><?php echo esc_html( get_the_title( $p ) ); ?></span>
					<?php if ( $p->post_excerpt ) : ?>
						<span class="cav-item-resumo"><?php echo esc_html( wp_trim_words( $p->post_excerpt, 20 ) ); ?></span>
					<?php endif; ?>
				</span>
			</a>
			<?php
		}
		echo '</div>';
		return ob_get_clean();
	}

	/** Lista de sorteios com botão de participar. */
	public static function sorteios( $atts ) {
		$atts = shortcode_atts( [ 'publico' => '', 'qtd' => 12 ], $atts, 'cav_sorteios' );
		wp_enqueue_style( 'cav' );
		wp_enqueue_script( 'cav-sorteio' );

		$publicos = self::publicos_visiveis( $atts['publico'] );
		if ( ! $publicos ) {
			return CAV_Acesso::bloqueio( $atts['publico'] ?: 'membros' );
		}

		$posts = get_posts( [
			'post_type'      => 'cav_sorteio',
			'posts_per_page' => absint( $atts['qtd'] ),
			'post_status'    => 'publish',
			'meta_query'     => [ [ 'key' => CAV_Acesso::META_PUBLICO, 'value' => $publicos, 'compare' => 'IN' ] ],
		] );

		if ( ! $posts ) {
			return '<p class="cav-vazio">Nenhum sorteio aberto agora. Fique de olho, sempre tem coisa nova.</p>';
		}

		$meus = CAV_Sorteio::do_membro( get_current_user_id() );

		ob_start();
		echo '<div class="cav-grade">';
		foreach ( $posts as $p ) {
			$premio   = get_post_meta( $p->ID, CAV_Conteudo::META_PREMIO, true );
			$fim      = get_post_meta( $p->ID, CAV_Conteudo::META_FIM, true );
			$ganhador = (int) get_post_meta( $p->ID, CAV_Conteudo::META_GANHADOR, true );
			$aberto   = CAV_Conteudo::inscricoes_abertas( $p->ID );
			$dentro   = in_array( (string) $p->ID, array_map( 'strval', $meus ), true );
			?>
			<div class="cav-item is-sorteio">
				<?php if ( has_post_thumbnail( $p ) ) : ?>
					<span class="cav-item-foto"><?php echo get_the_post_thumbnail( $p, 'medium' ); ?></span>
				<?php endif; ?>
				<div class="cav-item-corpo">
					<span class="cav-item-titulo"><?php echo esc_html( get_the_title( $p ) ); ?></span>
					<?php if ( $premio ) : ?>
						<span class="cav-item-resumo"><?php echo esc_html( $premio ); ?></span>
					<?php endif; ?>
					<?php if ( $fim && $aberto ) : ?>
						<span class="cav-item-prazo">Participe até <?php echo esc_html( mysql2date( 'd/m/Y', $fim . ' 00:00:00' ) ); ?></span>
					<?php endif; ?>

					<?php if ( $ganhador ) :
						$g = get_userdata( $ganhador ); ?>
						<span class="cav-item-status">Ganhador: <?php echo esc_html( $g ? $g->display_name : 'apurado' ); ?></span>
					<?php elseif ( $dentro ) : ?>
						<span class="cav-item-status is-ok">Você está participando</span>
					<?php elseif ( ! $aberto ) : ?>
						<span class="cav-item-status">Inscrições encerradas</span>
					<?php elseif ( ! CAV_Acesso::e_membro_ativo() ) : ?>
						<span class="cav-item-status">Só para membros com matrícula ativa</span>
					<?php else : ?>
						<button type="button" class="cav-btn cav-participar" data-sorteio="<?php echo esc_attr( $p->ID ); ?>">Participar</button>
					<?php endif; ?>
				</div>
			</div>
			<?php
		}
		echo '</div>';
		return ob_get_clean();
	}

	/** Públicos que este visitante consegue enxergar, filtrados pelo atributo. */
	private static function publicos_visiveis( $pedido = '' ) {
		$lista = [];
		if ( CAV_Acesso::pode_ver( 'membros' ) ) {
			$lista[] = 'membros';
		}
		if ( CAV_Acesso::pode_ver( 'parceiros' ) ) {
			$lista[] = 'parceiros';
		}
		if ( $lista ) {
			$lista[] = 'ambos';
		}

		if ( $pedido && in_array( $pedido, [ 'membros', 'parceiros', 'ambos' ], true ) ) {
			$lista = array_intersect( $lista, [ $pedido, 'ambos' ] );
		}

		return array_values( array_unique( $lista ) );
	}

	/** Terminal do balcão: consulta CPF e registra o uso. */
	public static function terminal() {
		if ( ! is_user_logged_in() ) {
			return self::aviso( 'Faça login com a conta do estabelecimento para usar o terminal.' );
		}
		if ( ! current_user_can( 'cav_consultar' ) ) {
			return self::aviso( 'Esta conta não tem acesso ao terminal.' );
		}

		$parceiro_id = CAV_Parceiro::parceiro_do_usuario( get_current_user_id() );
		if ( ! $parceiro_id && ! current_user_can( 'cav_gerenciar' ) ) {
			return self::aviso( 'Sua conta ainda não está vinculada a um estabelecimento. Fale com a Alliance.' );
		}

		wp_enqueue_style( 'cav' );
		wp_enqueue_script( 'cav-terminal' );

		$nome_parceiro = $parceiro_id ? get_the_title( $parceiro_id ) : 'Modo administrador';

		ob_start();
		?>
		<div class="cav-terminal" data-parceiro="<?php echo esc_attr( $parceiro_id ); ?>">
			<p class="cav-eyebrow"><?php echo esc_html( $nome_parceiro ); ?></p>

			<div class="cav-step" data-step="cpf">
				<label class="cav-label" for="cav-cpf">CPF do membro</label>
				<input type="text" id="cav-cpf" class="cav-input" inputmode="numeric" autocomplete="off"
				       placeholder="000.000.000-00" maxlength="14">
				<button type="button" class="cav-btn" id="cav-consultar">Verificar</button>
				<p class="cav-erro" id="cav-erro" hidden></p>
			</div>

			<div class="cav-step" data-step="resultado" hidden>
				<div class="cav-resultado" id="cav-resultado"></div>
				<div class="cav-beneficios" id="cav-beneficios"></div>
				<button type="button" class="cav-btn cav-btn-ghost" id="cav-novo">Nova consulta</button>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/** Painel do parceiro: contadores e últimos registros. */
	public static function painel_parceiro() {
		if ( ! is_user_logged_in() || ! current_user_can( 'cav_ver_relatorio' ) ) {
			return self::aviso( 'Faça login com a conta do estabelecimento para ver o painel.' );
		}

		$parceiro_id = CAV_Parceiro::parceiro_do_usuario( get_current_user_id() );
		if ( ! $parceiro_id ) {
			return self::aviso( 'Sua conta ainda não está vinculada a um estabelecimento.' );
		}

		wp_enqueue_style( 'cav' );

		$r    = CAV_Usos::resumo_parceiro( $parceiro_id );
		$usos = CAV_Usos::do_parceiro( $parceiro_id, 30 );

		ob_start();
		?>
		<div class="cav-painel">
			<div class="cav-numeros">
				<div class="cav-numero"><strong><?php echo esc_html( $r['hoje'] ); ?></strong><span>hoje</span></div>
				<div class="cav-numero"><strong><?php echo esc_html( $r['mes'] ); ?></strong><span>no mês</span></div>
				<div class="cav-numero"><strong><?php echo esc_html( $r['total'] ); ?></strong><span>no total</span></div>
				<div class="cav-numero"><strong><?php echo esc_html( $r['membros'] ); ?></strong><span>membros</span></div>
			</div>

			<?php if ( $usos ) : ?>
				<table class="cav-tabela">
					<thead><tr><th>Quando</th><th>Benefício</th><th>Membro</th></tr></thead>
					<tbody>
					<?php foreach ( $usos as $u ) :
						$membro = get_userdata( $u->membro_id ); ?>
						<tr>
							<td><?php echo esc_html( mysql2date( 'd/m H:i', $u->criado_em ) ); ?></td>
							<td><?php echo esc_html( get_the_title( $u->beneficio_id ) ); ?></td>
							<td><?php echo esc_html( $membro ? CAV_Membro::primeiro_nome( $membro ) : '—' ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p class="cav-vazio">Nenhum benefício usado ainda. Assim que o primeiro membro chegar, ele aparece aqui.</p>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/** Carteirinha do membro. */
	public static function carteirinha() {
		if ( ! is_user_logged_in() ) {
			return self::aviso( 'Faça login para ver sua carteirinha.' );
		}

		wp_enqueue_style( 'cav' );

		$user  = wp_get_current_user();
		$cpf   = get_user_meta( $user->ID, CAV_Membro::META_CPF, true );
		$check = CAV_Membro::checar( $user );
		$val   = CAV_Membro::validade( $user->ID );

		ob_start();
		?>
		<div class="cav-carteirinha <?php echo $check['elegivel'] ? 'is-ativa' : 'is-inativa'; ?>">
			<p class="cav-eyebrow">Clube de Vantagens</p>
			<p class="cav-nome"><?php echo esc_html( $user->display_name ); ?></p>
			<p class="cav-cpf"><?php echo esc_html( $cpf ? CAV_CPF::formatar( $cpf ) : 'CPF não cadastrado' ); ?></p>
			<p class="cav-situacao">
				<?php echo esc_html( CAV_Membro::motivo_legivel( $check['motivo'] ) ); ?>
				<?php if ( $check['elegivel'] && $val ) : ?>
					<span>· válido até <?php echo esc_html( mysql2date( 'd/m/Y', $val . ' 00:00:00' ) ); ?></span>
				<?php endif; ?>
			</p>
			<p class="cav-instrucao">Informe seu CPF no estabelecimento parceiro para receber o benefício.</p>
		</div>
		<?php
		return ob_get_clean();
	}

	/** Histórico de benefícios do membro. */
	public static function meus_usos() {
		if ( ! is_user_logged_in() ) {
			return self::aviso( 'Faça login para ver seu histórico.' );
		}

		wp_enqueue_style( 'cav' );
		$usos = CAV_Usos::do_membro( get_current_user_id(), 50 );

		if ( ! $usos ) {
			return '<p class="cav-vazio">Você ainda não usou nenhum benefício. Veja os parceiros disponíveis e comece a economizar.</p>';
		}

		ob_start();
		?>
		<table class="cav-tabela">
			<thead><tr><th>Quando</th><th>Parceiro</th><th>Benefício</th></tr></thead>
			<tbody>
			<?php foreach ( $usos as $u ) : ?>
				<tr>
					<td><?php echo esc_html( mysql2date( 'd/m/Y', $u->criado_em ) ); ?></td>
					<td><?php echo esc_html( get_the_title( $u->parceiro_id ) ); ?></td>
					<td><?php echo esc_html( get_the_title( $u->beneficio_id ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		return ob_get_clean();
	}
}

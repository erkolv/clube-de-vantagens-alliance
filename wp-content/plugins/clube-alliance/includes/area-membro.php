<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Área do aluno: menu entre as páginas, painel inicial, ofertas da academia e agenda.
 * A carteirinha, o histórico, os sorteios e os conteúdos já existem; aqui eles
 * ganham um menu comum e uma página de entrada.
 */
class CAV_Area {

	/** Páginas da área, na ordem do menu: slug => rótulo. */
	const PAGINAS = [
		'area-do-membro'    => 'Painel',
		'minha-carteirinha' => 'Carteirinha',
		'meus-beneficios'   => 'Benefícios',
		'ofertas'           => 'Ofertas',
		'agenda'            => 'Agenda',
		'sorteios-do-clube' => 'Sorteios',
		'conteudos'         => 'Conteúdos',
	];

	public static function init() {
		add_shortcode( 'cav_menu_membro', [ __CLASS__, 'menu' ] );
		add_shortcode( 'cav_painel_membro', [ __CLASS__, 'painel' ] );
		add_shortcode( 'cav_ofertas', [ __CLASS__, 'ofertas' ] );
		add_shortcode( 'cav_agenda', [ __CLASS__, 'agenda' ] );
		add_shortcode( 'cav_proximos_eventos', [ __CLASS__, 'proximos_eventos' ] );
		add_shortcode( 'cav_clube_agora', [ __CLASS__, 'clube_agora' ] );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'assets' ] );
	}

	public static function assets() {
		wp_register_style( 'cav-membro', CAV_URL . 'assets/cav-membro.css', [], CAV_VERSION );
	}

	private static function estilos() {
		wp_enqueue_style( 'cav' );
		wp_enqueue_style( 'cav-membro' );
	}

	/** Endereço de uma página da área pelo slug, ou '' se ela não existe. */
	public static function url( $slug ) {
		$p = get_page_by_path( $slug );
		return $p ? get_permalink( $p ) : '';
	}

	private static function e_da_area( $user = null ) {
		$user = $user ?: wp_get_current_user();
		return $user && $user->ID && ( user_can( $user, 'cav_gerenciar' ) || in_array( 'cav_membro', (array) $user->roles, true ) );
	}

	private static function ver_tudo( $slug, $texto = 'Ver tudo' ) {
		$url = self::url( $slug );
		return $url ? '<a class="cav-secao__mais" href="' . esc_url( $url ) . '">' . esc_html( $texto ) . ' &rarr;</a>' : '';
	}

	/* -------------------------------- Menu -------------------------------- */

	public static function menu() {
		if ( ! is_user_logged_in() || ! self::e_da_area() ) {
			return '';
		}

		self::estilos();
		$atual = (int) get_queried_object_id();

		ob_start();
		echo '<div class="cav-membro-raiz"><nav class="cav-menu-membro" aria-label="Área do aluno"><ul>';
		foreach ( self::PAGINAS as $slug => $rotulo ) {
			$p = get_page_by_path( $slug );
			if ( ! $p ) {
				continue;
			}
			printf(
				'<li><a href="%s"%s>%s</a></li>',
				esc_url( get_permalink( $p ) ),
				$atual === (int) $p->ID ? ' aria-current="page"' : '',
				esc_html( $rotulo )
			);
		}
		echo '</ul></nav></div>';
		return ob_get_clean();
	}

	/* ------------------------------- Painel ------------------------------- */

	public static function painel() {
		if ( ! is_user_logged_in() || ! self::e_da_area() ) {
			return CAV_Acesso::bloqueio( 'membros' );
		}

		self::estilos();

		$user     = wp_get_current_user();
		$check    = CAV_Membro::checar( $user );
		$validade = CAV_Membro::validade( $user->ID );
		$liberado = CAV_Acesso::pode_ver( 'membros' );
		$admin    = user_can( $user, 'cav_gerenciar' ) && ! $check['elegivel'];

		$usos     = CAV_Usos::do_membro( $user->ID, 500 );
		$mes      = current_time( 'Y-m' );
		$usos_mes = count( array_filter( $usos, function ( $u ) use ( $mes ) {
			return 0 === strpos( $u->criado_em, $mes );
		} ) );
		$sorteios = count( CAV_Sorteio::do_membro( $user->ID ) );

		ob_start();
		?>
		<div class="cav-membro-raiz cav-painel-membro">

			<header class="cav-saudacao">
				<p class="cav-eyebrow">Área do aluno</p>
				<h2>Olá, <?php echo esc_html( CAV_Membro::primeiro_nome( $user ) ); ?></h2>
				<p class="cav-status <?php echo $check['elegivel'] ? 'is-ativa' : 'is-inativa'; ?>">
					<?php if ( $admin ) : ?>
						Visualização de administrador
					<?php else : ?>
						<?php echo esc_html( CAV_Membro::motivo_legivel( $check['motivo'] ) ); ?>
						<?php if ( $check['elegivel'] && $validade ) : ?>
							<span>· válido até <?php echo esc_html( mysql2date( 'd/m/Y', $validade . ' 00:00:00' ) ); ?></span>
						<?php endif; ?>
					<?php endif; ?>
				</p>
			</header>

			<?php if ( ! $liberado ) : ?>
				<?php echo CAV_Acesso::bloqueio( 'membros' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php else : ?>

				<?php foreach ( self::ganhos_recentes( $user->ID ) as $g ) :
					$premio = get_post_meta( $g->ID, CAV_Conteudo::META_PREMIO, true ) ?: get_the_title( $g );
					?>
					<div class="cav-ganhou" role="status">
						<strong>Parabéns, você ganhou!</strong>
						<?php echo esc_html( $premio ); ?> &middot; <?php echo esc_html( get_the_title( $g ) ); ?>.
						Fale com a recepção da academia para retirar o prêmio.
					</div>
				<?php endforeach; ?>

				<div class="cav-numeros-membro">
					<div><strong><?php echo esc_html( $usos_mes ); ?></strong><span>benefícios usados no mês</span></div>
					<div><strong><?php echo esc_html( count( $usos ) ); ?></strong><span>no total</span></div>
					<div><strong><?php echo esc_html( $sorteios ); ?></strong><span>sorteios que você participa</span></div>
				</div>

				<?php
				$eventos = CAV_Agenda::proximos( 3 );
				$ofertas = CAV_Ofertas::listar( '', 3 );
				$abertos = self::sorteios_abertos( 3 );
				$resultados = array_slice( array_values( array_filter( CAV_Sorteio::apurados( 6 ), function ( $p ) {
					return CAV_Acesso::pode_ver_post( $p->ID );
				} ) ), 0, 2 );
				?>

				<?php if ( $eventos ) : ?>
					<section class="cav-secao">
						<div class="cav-secao__topo">
							<h3>Próximos eventos</h3>
							<?php echo self::ver_tudo( 'agenda', 'Ver a agenda' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						</div>
						<div class="cav-eventos">
							<?php foreach ( $eventos as $e ) { echo CAV_Agenda::item( $e ); } // phpcs:ignore WordPress.Security.EscapeOutput ?>
						</div>
					</section>
				<?php endif; ?>

				<?php if ( $ofertas ) : ?>
					<section class="cav-secao">
						<div class="cav-secao__topo">
							<h3>Ofertas da academia</h3>
							<?php echo self::ver_tudo( 'ofertas', 'Ver todas' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						</div>
						<div class="cav-ofertas-grade">
							<?php foreach ( $ofertas as $o ) { echo CAV_Ofertas::cartao( $o ); } // phpcs:ignore WordPress.Security.EscapeOutput ?>
						</div>
					</section>
				<?php endif; ?>

				<?php if ( $abertos ) : ?>
					<section class="cav-secao">
						<div class="cav-secao__topo">
							<h3>Sorteios abertos</h3>
							<?php echo self::ver_tudo( 'sorteios-do-clube', 'Participar' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						</div>
						<ul class="cav-lista-simples">
							<?php foreach ( $abertos as $s ) :
								$premio = get_post_meta( $s->ID, CAV_Conteudo::META_PREMIO, true );
								$fim    = get_post_meta( $s->ID, CAV_Conteudo::META_FIM, true );
								?>
								<li>
									<strong><?php echo esc_html( get_the_title( $s ) ); ?></strong>
									<?php if ( $premio ) : ?><span><?php echo esc_html( $premio ); ?></span><?php endif; ?>
									<?php if ( $fim ) : ?><em>até <?php echo esc_html( mysql2date( 'd/m', $fim . ' 00:00:00' ) ); ?></em><?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>

				<?php if ( $resultados ) : ?>
					<section class="cav-secao">
						<div class="cav-secao__topo">
							<h3>Últimos resultados</h3>
							<?php echo self::ver_tudo( 'sorteios-do-clube', 'Ver todos' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						</div>
						<ul class="cav-lista-simples">
							<?php foreach ( $resultados as $s ) :
								$gid = (int) get_post_meta( $s->ID, CAV_Conteudo::META_GANHADOR, true );
								?>
								<li>
									<strong><?php echo esc_html( get_the_title( $s ) ); ?></strong>
									<span><?php echo $gid === (int) $user->ID ? 'Você ganhou' : 'Ganhador: ' . esc_html( CAV_Sorteio::nome_publico( $gid ) ); ?></span>
									<em><?php echo esc_html( mysql2date( 'd/m', get_post_meta( $s->ID, CAV_Conteudo::META_APURADO, true ) ) ); ?></em>
								</li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>

				<?php if ( $usos ) : ?>
					<section class="cav-secao">
						<div class="cav-secao__topo">
							<h3>Seus últimos benefícios</h3>
							<?php echo self::ver_tudo( 'meus-beneficios', 'Histórico' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						</div>
						<ul class="cav-lista-simples">
							<?php foreach ( array_slice( $usos, 0, 3 ) as $u ) : ?>
								<li>
									<strong><?php echo esc_html( get_the_title( $u->beneficio_id ) ); ?></strong>
									<span><?php echo esc_html( get_the_title( $u->parceiro_id ) ); ?></span>
									<em><?php echo esc_html( mysql2date( 'd/m', $u->criado_em ) ); ?></em>
								</li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>

				<?php if ( ! $eventos && ! $ofertas && ! $abertos && ! $resultados && ! $usos ) : ?>
					<p class="cav-vazio">Por enquanto não há novidades. Quando a academia publicar eventos, ofertas e sorteios, eles aparecem aqui.</p>
				<?php endif; ?>

			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/** Sorteios que o usuário ganhou nos últimos 60 dias (depois disso o aviso sai do painel). */
	private static function ganhos_recentes( $user_id ) {
		$limite = wp_date( 'Y-m-d H:i:s', strtotime( '-60 days' ) );

		return array_values( array_filter( CAV_Sorteio::ganhos_do_membro( $user_id, 3 ), function ( $p ) use ( $limite ) {
			return (string) get_post_meta( $p->ID, CAV_Conteudo::META_APURADO, true ) >= $limite;
		} ) );
	}

	/** Sorteios com inscrição aberta que o usuário pode ver. */
	private static function sorteios_abertos( $qtd ) {
		$posts = get_posts( [
			'post_type'      => 'cav_sorteio',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
		] );

		$posts = array_values( array_filter( $posts, function ( $p ) {
			return CAV_Conteudo::inscricoes_abertas( $p->ID ) && CAV_Acesso::pode_ver_post( $p->ID );
		} ) );

		return array_slice( $posts, 0, $qtd );
	}

	/* -------------------------------- Ofertas -------------------------------- */

	/** [cav_ofertas tipo="produto|desconto" qtd="24"] */
	public static function ofertas( $atts ) {
		$atts = shortcode_atts( [ 'tipo' => '', 'qtd' => 24 ], $atts, 'cav_ofertas' );

		if ( ! CAV_Acesso::pode_ver( 'membros' ) ) {
			return CAV_Acesso::bloqueio( 'membros' );
		}

		self::estilos();

		$tipos = $atts['tipo'] && isset( CAV_Ofertas::tipos()[ $atts['tipo'] ] )
			? [ $atts['tipo'] ]
			: [ 'desconto', 'produto' ];

		$titulos = [
			'desconto' => 'Descontos exclusivos na academia',
			'produto'  => 'Produtos com desconto',
		];

		ob_start();
		echo '<div class="cav-membro-raiz cav-ofertas">';

		$achou = false;
		foreach ( $tipos as $tipo ) {
			$lista = CAV_Ofertas::listar( $tipo, $atts['qtd'] );
			if ( ! $lista ) {
				continue;
			}
			$achou = true;
			echo '<section class="cav-secao"><div class="cav-secao__topo"><h3>' . esc_html( $titulos[ $tipo ] ) . '</h3></div>';
			echo '<div class="cav-ofertas-grade">';
			foreach ( $lista as $o ) {
				echo CAV_Ofertas::cartao( $o ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</div></section>';
		}

		if ( ! $achou ) {
			echo '<p class="cav-vazio">Nenhuma oferta no ar agora. Quando a academia publicar, ela aparece aqui.</p>';
		} else {
			echo '<p class="cav-nota">Preços e descontos valem só para quem tem a matrícula e o clube em dia. Para pegar, fale com a recepção.</p>';
		}

		echo '</div>';
		return ob_get_clean();
	}

	/* --------------------------------- Agenda --------------------------------- */

	/** [cav_agenda] calendário do mês e lista de eventos. */
	public static function agenda() {
		if ( ! CAV_Acesso::pode_ver( 'membros' ) ) {
			return CAV_Acesso::bloqueio( 'membros' );
		}

		self::estilos();

		$hoje = explode( '-', current_time( 'Y-m' ) );
		$ano  = (int) $hoje[0];
		$mes  = (int) $hoje[1];

		// phpcs:ignore WordPress.Security.NonceVerification
		$pedido = isset( $_GET['mes'] ) ? sanitize_text_field( wp_unslash( $_GET['mes'] ) ) : '';
		if ( preg_match( '/^(\d{4})-(\d{2})$/', $pedido, $m ) && (int) $m[2] >= 1 && (int) $m[2] <= 12 ) {
			$dif = ( (int) $m[1] - $ano ) * 12 + ( (int) $m[2] - $mes );
			if ( abs( $dif ) <= 24 ) {
				$ano = (int) $m[1];
				$mes = (int) $m[2];
			}
		}

		$do_mes = CAV_Agenda::do_mes( $ano, $mes );
		$base   = get_permalink( get_queried_object_id() ) ?: home_url( '/' );

		ob_start();
		?>
		<div class="cav-membro-raiz cav-agenda">
			<div class="cav-agenda__layout">
				<?php echo CAV_Agenda::calendario( $ano, $mes, $do_mes, $base ); // phpcs:ignore WordPress.Security.EscapeOutput ?>

				<div class="cav-agenda__lista">
					<?php if ( $do_mes ) : ?>
						<h3 class="cav-agenda__titulo">Em <?php echo esc_html( CAV_Agenda::MESES[ $mes ] ); ?></h3>
						<div class="cav-eventos">
							<?php foreach ( $do_mes as $e ) { echo CAV_Agenda::item( $e, 'cav-evento-' . $e->ID ); } // phpcs:ignore WordPress.Security.EscapeOutput ?>
						</div>
					<?php else : ?>
						<?php $proximos = CAV_Agenda::proximos( 5 ); ?>
						<p class="cav-vazio">Nada marcado para <?php echo esc_html( CAV_Agenda::MESES[ $mes ] ); ?>.</p>
						<?php if ( $proximos ) : ?>
							<h3 class="cav-agenda__titulo">Próximos eventos</h3>
							<div class="cav-eventos">
								<?php foreach ( $proximos as $e ) { echo CAV_Agenda::item( $e, 'cav-evento-' . $e->ID ); } // phpcs:ignore WordPress.Security.EscapeOutput ?>
							</div>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/** [cav_proximos_eventos qtd="3"] lista curta, para qualquer página. */
	public static function proximos_eventos( $atts ) {
		$atts = shortcode_atts( [ 'qtd' => 3 ], $atts, 'cav_proximos_eventos' );

		if ( ! CAV_Acesso::pode_ver( 'membros' ) ) {
			return CAV_Acesso::bloqueio( 'membros' );
		}

		self::estilos();
		$lista = CAV_Agenda::proximos( $atts['qtd'] );

		if ( ! $lista ) {
			return '<p class="cav-vazio">Nenhum evento marcado por enquanto.</p>';
		}

		ob_start();
		echo '<div class="cav-membro-raiz"><div class="cav-eventos">';
		foreach ( $lista as $e ) {
			echo CAV_Agenda::item( $e ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div></div>';
		return ob_get_clean();
	}

	/**
	 * Vitrine pública para a home: o que está acontecendo no clube agora (sorteio aberto,
	 * próximo seminário, descontos da academia). Só mostra títulos e datas, nada de área restrita.
	 * [cav_clube_agora]
	 */
	public static function clube_agora() {
		$itens = [];

		foreach ( get_posts( [ 'post_type' => 'cav_sorteio', 'post_status' => 'publish', 'posts_per_page' => 20 ] ) as $s ) {
			if ( CAV_Conteudo::inscricoes_abertas( $s->ID ) ) {
				$premio = get_post_meta( $s->ID, CAV_Conteudo::META_PREMIO, true ) ?: get_the_title( $s );
				$fim    = get_post_meta( $s->ID, CAV_Conteudo::META_FIM, true );
				$itens[] = [ 'Sorteio aberto', $premio, $fim ? 'até ' . mysql2date( 'd/m', $fim . ' 00:00:00' ) : '' ];
				break;
			}
		}

		foreach ( CAV_Agenda::proximos( 30 ) as $e ) {
			if ( 'seminario' === get_post_meta( $e->ID, CAV_Agenda::META_TIPO, true ) ) {
				$data    = get_post_meta( $e->ID, CAV_Agenda::META_DATA, true );
				$itens[] = [ 'Próximo seminário', get_the_title( $e ), $data ? mysql2date( 'd/m', $data . ' 00:00:00' ) : '' ];
				break;
			}
		}

		$descontos = count( CAV_Ofertas::listar( 'desconto', 50 ) );
		if ( $descontos ) {
			$itens[] = [ 'Descontos na academia', 1 === $descontos ? '1 condição exclusiva' : $descontos . ' condições exclusivas', 'no ar agora' ];
		}

		if ( ! $itens ) {
			return '';
		}

		wp_enqueue_style( 'cav-membro' );

		ob_start();
		echo '<div class="cav-membro-raiz cav-agora"><p class="cav-agora__titulo">Acontecendo agora no clube</p><ul class="cav-agora__lista">';
		foreach ( $itens as $i ) {
			printf(
				'<li><span class="cav-agora__rotulo">%s</span><strong>%s</strong>%s</li>',
				esc_html( $i[0] ),
				esc_html( $i[1] ),
				$i[2] ? '<em>' . esc_html( $i[2] ) . '</em>' : ''
			);
		}
		echo '</ul></div>';
		return ob_get_clean();
	}
}

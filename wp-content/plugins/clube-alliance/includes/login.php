<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Login com a cara do site. A tela de entrada do WordPress (wp-login.php) deixa de ser a porta:
 * /entrar/ recebe e-mail e senha (e o botão do Google, se configurado). O WordPress continua
 * cuidando de "esqueci a senha" e de definir a senha, mas essas telas ganham o visual do clube
 * (o visual fica no tema filho). Para abrir a tela original em caso de emergência: wp-login.php?cav_wp=1
 */
class CAV_Login {

	const PAGINA = 'entrar';

	public static function init() {
		add_shortcode( 'cav_login', [ __CLASS__, 'formulario' ] );

		add_action( 'admin_post_nopriv_cav_entrar', [ __CLASS__, 'receber' ] );
		add_action( 'admin_post_cav_entrar', [ __CLASS__, 'receber' ] );
		add_action( 'admin_post_nopriv_cav_google', [ __CLASS__, 'receber_google' ] );
		add_action( 'admin_post_cav_google', [ __CLASS__, 'receber_google' ] );

		add_action( 'login_init', [ __CLASS__, 'desviar' ] );
		add_filter( 'login_url', [ __CLASS__, 'filtrar_url' ], 10, 3 );
		add_filter( 'logout_redirect', [ __CLASS__, 'depois_de_sair' ], 10, 3 );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'assets' ] );
	}

	public static function assets() {
		wp_register_style( 'cav-login', CAV_URL . 'assets/cav-login.css', [ 'cav' ], CAV_VERSION );
		wp_register_script( 'cav-google-gsi', 'https://accounts.google.com/gsi/client', [], null, [ 'strategy' => 'async', 'in_footer' => true ] );
		wp_register_script( 'cav-login', CAV_URL . 'assets/cav-login.js', [ 'cav-google-gsi' ], CAV_VERSION, true );
	}

	/** Endereço da página de login, ou '' se ela ainda não existe (aí vale a tela do WordPress). */
	public static function url() {
		$p = get_page_by_path( self::PAGINA );
		return ( $p && 'publish' === $p->post_status ) ? get_permalink( $p ) : '';
	}

	/** Todo link "faça login" do site passa a apontar para a página nova. */
	public static function filtrar_url( $url, $redirect, $forcar_reauth ) {
		$pagina = self::url();
		if ( ! $pagina || $forcar_reauth ) {
			return $url;
		}
		return $redirect ? add_query_arg( 'redirect_to', rawurlencode( $redirect ), $pagina ) : $pagina;
	}

	/** wp-login.php aberto "no modo entrar" vira a página nova. Os outros modos (senha, etc.) ficam. */
	public static function desviar() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}
		if ( isset( $_GET['cav_wp'] ) || isset( $_GET['interim-login'] ) || isset( $_GET['reauth'] ) ) {
			return;
		}

		$acao = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : 'login';
		if ( 'login' !== $acao ) {
			return;
		}

		$pagina = self::url();
		if ( ! $pagina ) {
			return;
		}

		$args = [];

		if ( ! empty( $_GET['redirect_to'] ) ) {
			$args['redirect_to'] = rawurlencode( wp_unslash( $_GET['redirect_to'] ) );
		}
		if ( isset( $_GET['loggedout'] ) ) {
			$args['cav_msg'] = 'saiu';
		} elseif ( isset( $_GET['password'] ) && 'changed' === $_GET['password'] ) {
			$args['cav_msg'] = 'senha_ok';
		} elseif ( isset( $_GET['checkemail'] ) ) {
			$args['cav_msg'] = 'email_enviado';
		}
		// phpcs:enable

		wp_safe_redirect( add_query_arg( $args, $pagina ) );
		exit;
	}

	public static function depois_de_sair( $destino, $pedido, $user ) {
		$pagina = self::url();
		return $pagina ? add_query_arg( 'cav_msg', 'saiu', $pagina ) : $destino;
	}

	/* ------------------------------ Mensagens ------------------------------ */

	private static function mensagens() {
		return [
			'dados'              => [ 'erro', 'E-mail ou senha incorretos. Confira e tente de novo.' ],
			'falha'              => [ 'erro', 'Não consegui entrar agora. Tente de novo em instantes.' ],
			'campos'             => [ 'erro', 'Preencha o e-mail e a senha.' ],
			'google_sem_cadastro' => [ 'erro', 'Esse e-mail do Google não tem cadastro no clube. Entre com o e-mail e a senha, ou peça acesso na recepção.' ],
			'google_pendente'    => [ 'erro', 'Seu pedido ainda está em análise pela recepção. Você recebe um e-mail quando o acesso for liberado.' ],
			'google_admin'       => [ 'erro', 'Esta conta entra só com e-mail e senha.' ],
			'google_falha'       => [ 'erro', 'Não consegui confirmar sua conta Google. Tente de novo ou entre com e-mail e senha.' ],
			'saiu'               => [ 'ok', 'Você saiu da sua conta.' ],
			'senha_ok'           => [ 'ok', 'Senha atualizada. Agora é só entrar.' ],
			'email_enviado'      => [ 'ok', 'Se o e-mail estiver cadastrado, o link para criar uma nova senha chega em instantes.' ],
		];
	}

	private static function recado() {
		// phpcs:ignore WordPress.Security.NonceVerification
		$codigo = isset( $_GET['cav_msg'] ) ? sanitize_key( wp_unslash( $_GET['cav_msg'] ) ) : '';
		$todas  = self::mensagens();

		if ( ! $codigo || ! isset( $todas[ $codigo ] ) ) {
			return '';
		}

		return sprintf(
			'<p class="cav-recado cav-recado--%s" role="%s">%s</p>',
			esc_attr( $todas[ $codigo ][0] ),
			'ok' === $todas[ $codigo ][0] ? 'status' : 'alert',
			esc_html( $todas[ $codigo ][1] )
		);
	}

	/** Pedido de redirect vindo da URL ou do formulário, só se for do próprio site. */
	private static function pedido_de_redirect() {
		// phpcs:ignore WordPress.Security.NonceVerification
		$bruto = isset( $_REQUEST['redirect_to'] ) ? wp_unslash( $_REQUEST['redirect_to'] ) : '';
		return $bruto ? wp_validate_redirect( $bruto, '' ) : '';
	}

	private static function voltar( $codigo, $pedido = '' ) {
		$args = [ 'cav_msg' => $codigo ];
		if ( $pedido ) {
			$args['redirect_to'] = rawurlencode( $pedido );
		}
		wp_safe_redirect( add_query_arg( $args, self::url() ?: wp_login_url() ) );
		exit;
	}

	/* ------------------------------ Formulário ------------------------------ */

	public static function formulario() {
		wp_enqueue_style( 'cav' );
		wp_enqueue_style( 'cav-login' );

		$pedido = self::pedido_de_redirect();

		if ( is_user_logged_in() ) {
			$area = CAV_Entrada::area( wp_get_current_user() );
			ob_start();
			?>
			<div class="cav-login">
				<?php echo self::recado(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<div class="cav-card">
					<p><strong>Você já está dentro.</strong></p>
					<p class="cav-login__acoes">
						<?php if ( $area ) : ?>
							<a class="cav-btn cav-btn--link" href="<?php echo esc_url( $area['url'] ); ?>"><?php echo esc_html( $area['rotulo'] ); ?></a>
						<?php endif; ?>
						<a class="cav-login__link" href="<?php echo esc_url( wp_logout_url() ); ?>">Sair</a>
					</p>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}

		$google = CAV_Config::google_client_id();

		if ( $google ) {
			wp_enqueue_script( 'cav-login' );
			wp_localize_script( 'cav-login', 'cavLogin', [
				'clientId' => $google,
				'url'      => admin_url( 'admin-post.php' ),
				'nonce'    => wp_create_nonce( 'cav_google' ),
				'redirect' => $pedido,
			] );
		}

		ob_start();
		?>
		<div class="cav-login">
			<?php echo self::recado(); // phpcs:ignore WordPress.Security.EscapeOutput ?>

			<?php if ( $google ) : ?>
				<div class="cav-login__google">
					<div id="cav-google-botao" class="cav-login__google-botao"></div>
					<p class="cav-login__ou"><span>ou entre com e-mail e senha</span></p>
				</div>
			<?php endif; ?>

			<form class="cav-form cav-form--login" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="cav_entrar">
				<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $pedido ); ?>">
				<?php wp_nonce_field( 'cav_entrar', 'cav_nonce' ); ?>

				<label for="cav-login-email">E-mail</label>
				<input id="cav-login-email" name="login" type="text" autocomplete="username" autocapitalize="none" required>

				<label for="cav-login-senha">Senha</label>
				<input id="cav-login-senha" name="senha" type="password" autocomplete="current-password" required>

				<label class="cav-consent cav-login__lembrar">
					<input type="checkbox" name="lembrar" value="1">
					<span>Manter conectado neste aparelho</span>
				</label>

				<button type="submit" class="cav-btn">Entrar</button>
			</form>

			<p class="cav-login__links">
				<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>">Esqueci minha senha</a>
			</p>
			<p class="cav-login__links cav-login__links--ajuda">
				Ainda não é do clube? <a href="<?php echo esc_url( home_url( '/quero-fazer-parte/' ) ); ?>">Sou aluno e quero participar</a>
				· <a href="<?php echo esc_url( home_url( '/seja-parceiro/' ) ); ?>">Tenho um negócio e quero ser parceiro</a>
			</p>
		</div>
		<?php
		return ob_get_clean();
	}

	/* --------------------------- E-mail e senha --------------------------- */

	public static function receber() {
		check_admin_referer( 'cav_entrar', 'cav_nonce' );

		$pedido = self::pedido_de_redirect();

		if ( is_user_logged_in() ) {
			self::ir( wp_get_current_user(), $pedido );
		}

		$login = isset( $_POST['login'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['login'] ) ) ) : '';
		$senha = isset( $_POST['senha'] ) ? (string) wp_unslash( $_POST['senha'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		if ( '' === $login || '' === $senha ) {
			self::voltar( 'campos', $pedido );
		}

		$user = wp_signon( [
			'user_login'    => $login,
			'user_password' => $senha,
			'remember'      => ! empty( $_POST['lembrar'] ),
		], is_ssl() );

		if ( is_wp_error( $user ) ) {
			$cod = $user->get_error_code();
			// Mesma mensagem para usuário que não existe e senha errada: não revela quem tem cadastro.
			$erro = in_array( $cod, [ 'invalid_username', 'invalid_email', 'incorrect_password', 'empty_username', 'empty_password' ], true ) ? 'dados' : 'falha';
			self::voltar( $erro, $pedido );
		}

		self::ir( $user, $pedido );
	}

	/** Manda a pessoa para a área dela (ou para o que ela tinha pedido). */
	private static function ir( WP_User $user, $pedido ) {
		$destino = apply_filters( 'login_redirect', $pedido ?: admin_url(), $pedido, $user );
		wp_safe_redirect( $destino ?: home_url( '/' ) );
		exit;
	}

	/* ------------------------------- Google ------------------------------- */

	public static function receber_google() {
		check_admin_referer( 'cav_google', 'cav_nonce' );

		$pedido    = self::pedido_de_redirect();
		$client_id = CAV_Config::google_client_id();

		if ( ! $client_id ) {
			self::voltar( 'google_falha', $pedido );
		}

		$credencial = isset( $_POST['credential'] ) ? (string) wp_unslash( $_POST['credential'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$dados      = CAV_Google::verificar( $credencial, $client_id );

		if ( is_wp_error( $dados ) ) {
			self::voltar( 'google_falha', $pedido );
		}

		$user = get_user_by( 'email', $dados['email'] );

		if ( ! $user ) {
			self::voltar( 'google_sem_cadastro', $pedido );
		}

		// Só contas do clube entram com Google. Quem administra o site entra com senha.
		$papeis_do_clube = array_intersect( (array) $user->roles, [ 'cav_membro', 'cav_parceiro', 'cav_recepcao' ] );
		if ( ! $papeis_do_clube || user_can( $user, 'manage_options' ) || user_can( $user, 'cav_gerenciar' ) ) {
			self::voltar( 'google_admin', $pedido );
		}

		// Aluno que ainda não foi aprovado não entra por aqui.
		$status = get_user_meta( $user->ID, 'cav_status', true );
		if ( in_array( 'cav_membro', (array) $user->roles, true ) && in_array( $status, [ CAV_Solicitacoes::STATUS_PENDENTE, CAV_Solicitacoes::STATUS_RECUSADO ], true ) ) {
			self::voltar( 'google_pendente', $pedido );
		}

		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true, is_ssl() );
		do_action( 'wp_login', $user->user_login, $user );

		self::ir( $user, $pedido );
	}
}

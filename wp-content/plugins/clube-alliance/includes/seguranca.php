<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Proteções do clube, juntas num lugar só:
 *
 *  - limite de tentativas de login e de "esqueci a senha" (contra senha adivinhada por robô);
 *  - proteção dos formulários públicos (campo-armadilha, tempo mínimo, limite por endereço);
 *  - a lista de usuários do WordPress não vaza (REST, ?author=, sitemap, oEmbed);
 *  - cabeçalhos de segurança e "não guardar em cache" nas páginas com dados de pessoas;
 *  - senhas novas com pelo menos 10 caracteres;
 *  - limpeza diária de registros antigos (consultas de CPF e endereços de IP);
 *  - avisos no painel quando algo está inseguro (chave de dados, https, contas de teste).
 *
 * Atrás de um proxy (Cloudflare, balanceador), o endereço do visitante chega num cabeçalho.
 * Só confiamos nele se você avisar no wp-config.php:  define( 'CAV_PROXY_CONFIAVEL', true );
 */
class CAV_Seguranca {

	const JANELA_LOGIN = 900; // 15 minutos

	/** Páginas do clube com dados de pessoas ou formulários: nunca ficam em cache. */
	const PAGINAS_PRIVADAS = [
		'entrar', 'quero-fazer-parte', 'seja-parceiro', 'minha-carteirinha', 'meus-beneficios',
		'conteudos', 'sorteios-do-clube', 'terminal', 'painel-do-parceiro', 'conteudos-do-parceiro',
		'area-do-membro', 'meu-negocio', 'minhas-promocoes', 'agenda',
	];

	public static function init() {
		// Login
		add_filter( 'authenticate', [ __CLASS__, 'travar_login' ], 1, 3 );
		add_action( 'wp_login_failed', [ __CLASS__, 'falha_de_login' ], 10, 2 );
		add_action( 'wp_login', [ __CLASS__, 'login_ok' ], 10, 2 );
		add_filter( 'login_errors', [ __CLASS__, 'erro_generico' ] );

		// Senha
		add_action( 'login_form_lostpassword', [ __CLASS__, 'esqueci_a_senha' ], 1 );
		add_action( 'validate_password_reset', [ __CLASS__, 'senha_do_link' ], 10, 2 );
		add_action( 'user_profile_update_errors', [ __CLASS__, 'senha_do_perfil' ], 10, 3 );

		// A lista de quem tem conta não vaza
		add_filter( 'rest_endpoints', [ __CLASS__, 'esconder_usuarios_da_api' ] );
		add_action( 'init', [ __CLASS__, 'barrar_autor_na_url' ], 1 );
		add_action( 'template_redirect', [ __CLASS__, 'barrar_pagina_de_autor' ], 1 );
		add_filter( 'wp_sitemaps_add_provider', [ __CLASS__, 'sem_usuarios_no_sitemap' ], 10, 2 );
		add_filter( 'oembed_response_data', [ __CLASS__, 'oembed_sem_autor' ] );

		// Portas que o clube não usa
		add_filter( 'xmlrpc_enabled', '__return_false' );
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			status_header( 403 );
			exit;
		}
		add_filter( 'wp_is_application_passwords_available', '__return_false' );
		add_filter( 'the_generator', '__return_empty_string' );
		remove_action( 'wp_head', 'wp_generator' );
		add_filter( 'wp_headers', [ __CLASS__, 'tirar_pingback' ] );
		add_filter( 'map_meta_cap', [ __CLASS__, 'sem_editor_de_arquivos' ], 10, 2 );

		// Cabeçalhos e cache
		add_action( 'send_headers', [ __CLASS__, 'cabecalhos' ] );
		add_action( 'login_init', [ __CLASS__, 'cabecalhos' ] );
		add_action( 'template_redirect', [ __CLASS__, 'sem_cache' ], 0 );

		// Limpeza e avisos
		add_action( 'init', [ __CLASS__, 'agendar_limpeza' ] );
		add_action( 'cav_limpeza_diaria', [ __CLASS__, 'limpeza' ] );
		add_action( 'admin_notices', [ __CLASS__, 'avisos' ] );
	}

	/* ------------------------------ Utilidades ------------------------------ */

	/** Endereço do visitante. */
	public static function ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';

		if ( defined( 'CAV_PROXY_CONFIAVEL' ) && CAV_PROXY_CONFIAVEL ) {
			foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR' ] as $h ) {
				if ( ! empty( $_SERVER[ $h ] ) ) {
					$primeiro = trim( explode( ',', (string) wp_unslash( $_SERVER[ $h ] ) )[0] );
					if ( filter_var( $primeiro, FILTER_VALIDATE_IP ) ) {
						$ip = $primeiro;
						break;
					}
				}
			}
		}

		$ip = apply_filters( 'cav_ip', $ip );
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? substr( $ip, 0, 45 ) : '';
	}

	/** Texto de um campo do formulário, sem tags e com tamanho máximo. */
	public static function texto( $campo, $max ) {
		if ( ! isset( $_POST[ $campo ] ) || ! is_string( $_POST[ $campo ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return '';
		}
		$v = sanitize_text_field( wp_unslash( $_POST[ $campo ] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		return function_exists( 'mb_substr' ) ? mb_substr( $v, 0, $max ) : substr( $v, 0, $max );
	}

	/** Telefone: só dígitos e + ( ) - espaço, até 20 caracteres. */
	public static function telefone( $valor ) {
		return substr( trim( preg_replace( '/[^\d+()\-\s]/', '', (string) $valor ) ), 0, 20 );
	}

	/** true enquanto a pessoa/endereço ainda não passou do limite; conta mais uma tentativa. */
	public static function limitar( $nome, $max, $janela, $quem = null ) {
		$quem  = null === $quem ? self::ip() : $quem;
		$chave = 'cav_lim_' . substr( md5( $nome . '|' . $quem ), 0, 24 );
		$n     = (int) get_transient( $chave );

		if ( $n >= $max ) {
			return false;
		}

		set_transient( $chave, $n + 1, $janela );
		return true;
	}

	/** Já passou do limite? (só olha, não conta) */
	private static function estourou( $nome, $max, $quem ) {
		return (int) get_transient( 'cav_lim_' . substr( md5( $nome . '|' . $quem ), 0, 24 ) ) >= $max;
	}

	private static function somar( $nome, $quem, $janela ) {
		$chave = 'cav_lim_' . substr( md5( $nome . '|' . $quem ), 0, 24 );
		set_transient( $chave, (int) get_transient( $chave ) + 1, $janela );
	}

	/* --------------------------- Formulários públicos --------------------------- */

	/** Campo-armadilha (robô preenche, gente não vê) e carimbo de hora assinado. */
	public static function campos_antirrobo() {
		$t = time();
		$a = hash_hmac( 'sha256', 'cav-t|' . $t, wp_salt( 'nonce' ) );

		return '<div aria-hidden="true" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden">'
			. '<label>Não preencha este campo<input type="text" name="cav_site" tabindex="-1" autocomplete="off" value=""></label></div>'
			. '<input type="hidden" name="cav_t" value="' . esc_attr( $t . '.' . $a ) . '">';
	}

	/** false se parece gente; 'armadilha' se o campo escondido veio preenchido; 'tempo' se o carimbo é inválido ou rápido demais. */
	public static function robo() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( ! empty( $_POST['cav_site'] ) ) {
			return 'armadilha';
		}

		$bruto = isset( $_POST['cav_t'] ) && is_string( $_POST['cav_t'] ) ? wp_unslash( $_POST['cav_t'] ) : '';
		// phpcs:enable
		if ( ! preg_match( '/^(\d{9,11})\.([a-f0-9]{64})$/', $bruto, $m ) ) {
			return 'tempo';
		}

		$esperado = hash_hmac( 'sha256', 'cav-t|' . $m[1], wp_salt( 'nonce' ) );
		if ( ! hash_equals( $esperado, $m[2] ) ) {
			return 'tempo';
		}

		$idade = time() - (int) $m[1];
		if ( $idade < 2 || $idade > 14 * DAY_IN_SECONDS ) {
			return 'tempo';
		}

		return false;
	}

	/* ---------------------------------- Login ---------------------------------- */

	public static function travar_login( $user, $login, $senha ) {
		$login = strtolower( trim( (string) $login ) );
		if ( '' === $login ) {
			return $user;
		}

		$ip = self::ip();
		// Por conta + endereço: 5 erros e espera. Por endereço: 60 erros (folga para wi-fi compartilhado).
		if ( self::estourou( 'login_conta', 5, $ip . '|' . $login ) || self::estourou( 'login_ip', 60, $ip ) ) {
			// Sem checar a senha: tira os verificadores do núcleo para esta tentativa.
			remove_filter( 'authenticate', 'wp_authenticate_username_password', 20 );
			remove_filter( 'authenticate', 'wp_authenticate_email_password', 20 );
			remove_filter( 'authenticate', 'wp_authenticate_application_password', 20 );
			remove_filter( 'authenticate', 'wp_authenticate_cookie', 30 );

			return new WP_Error( 'cav_bloqueado', 'Muitas tentativas. Espere 15 minutos e tente de novo.' );
		}

		return $user;
	}

	public static function falha_de_login( $login, $erro = null ) {
		if ( $erro instanceof WP_Error && 'cav_bloqueado' === $erro->get_error_code() ) {
			return;
		}
		$login = strtolower( trim( (string) $login ) );
		if ( '' === $login ) {
			return;
		}
		$ip = self::ip();
		self::somar( 'login_conta', $ip . '|' . $login, self::JANELA_LOGIN );
		self::somar( 'login_ip', $ip, self::JANELA_LOGIN );
	}

	public static function login_ok( $login, $user ) {
		delete_transient( 'cav_lim_' . substr( md5( 'login_conta|' . self::ip() . '|' . strtolower( (string) $login ) ), 0, 24 ) );
	}

	/** Na tela original do WordPress, "usuário não existe" e "senha errada" viram a mesma frase. */
	public static function erro_generico( $texto ) {
		global $errors;
		if ( $errors instanceof WP_Error && array_intersect( $errors->get_error_codes(), [ 'invalid_username', 'invalid_email', 'incorrect_password' ] ) ) {
			return '<strong>Erro:</strong> e-mail ou senha incorretos.';
		}
		return $texto;
	}

	/* ---------------------------------- Senha ---------------------------------- */

	/**
	 * "Esqueci a senha": a resposta é sempre a mesma, exista a conta ou não, e há um limite por hora.
	 * Assim ninguém descobre quem faz parte do clube digitando e-mails, nem enche a caixa de alguém.
	 */
	public static function esqueci_a_senha() {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}

		$destino = site_url( 'wp-login.php?checkemail=confirm', 'login' );

		if ( ! self::limitar( 'senha', 5, HOUR_IN_SECONDS ) ) {
			wp_safe_redirect( $destino );
			exit;
		}

		// phpcs:ignore WordPress.Security.NonceVerification
		$informado = isset( $_POST['user_login'] ) && is_string( $_POST['user_login'] ) ? trim( wp_unslash( $_POST['user_login'] ) ) : '';
		$user      = '' === $informado ? false : ( false !== strpos( $informado, '@' ) ? get_user_by( 'email', $informado ) : get_user_by( 'login', $informado ) );

		if ( ! $user ) {
			wp_safe_redirect( $destino );
			exit;
		}
	}

	private static function senha_curta( $senha ) {
		return is_string( $senha ) && '' !== $senha && strlen( $senha ) < 10;
	}

	public static function senha_do_link( $errors, $user ) {
		// phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
		$nova = isset( $_POST['pass1'] ) ? (string) wp_unslash( $_POST['pass1'] ) : '';
		if ( self::senha_curta( $nova ) ) {
			$errors->add( 'cav_senha_curta', 'A senha precisa ter pelo menos 10 caracteres.' );
		}
	}

	public static function senha_do_perfil( $errors, $atualizando, $user ) {
		if ( isset( $user->user_pass ) && self::senha_curta( $user->user_pass ) ) {
			$errors->add( 'cav_senha_curta', 'A senha precisa ter pelo menos 10 caracteres.' );
		}
	}

	/* ------------------------- Lista de usuários não vaza ------------------------- */

	public static function esconder_usuarios_da_api( $rotas ) {
		if ( ! current_user_can( 'list_users' ) ) {
			foreach ( array_keys( $rotas ) as $r ) {
				// "/me" é a própria pessoa logada (o editor precisa dela); o resto é a lista de usuários.
				if ( 0 === strpos( $r, '/wp/v2/users' ) && ! ( '/wp/v2/users/me' === $r && is_user_logged_in() ) ) {
					unset( $rotas[ $r ] );
				}
			}
		}
		return $rotas;
	}

	/** site.com/?author=1 revela o nome de usuário no endereço para onde o WordPress redireciona. */
	public static function barrar_autor_na_url() {
		if ( ! is_admin() && ! is_user_logged_in() && isset( $_GET['author'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}

	public static function barrar_pagina_de_autor() {
		if ( is_author() && ! is_user_logged_in() ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}

	public static function sem_usuarios_no_sitemap( $provider, $nome ) {
		return 'users' === $nome ? false : $provider;
	}

	public static function oembed_sem_autor( $dados ) {
		unset( $dados['author_name'], $dados['author_url'] );
		return $dados;
	}

	public static function tirar_pingback( $cabecalhos ) {
		unset( $cabecalhos['X-Pingback'] );
		return $cabecalhos;
	}

	/** Mesmo com acesso de administrador, ninguém edita arquivos do site pelo painel. */
	public static function sem_editor_de_arquivos( $caps, $cap ) {
		if ( in_array( $cap, [ 'edit_themes', 'edit_plugins', 'edit_files' ], true ) ) {
			return [ 'do_not_allow' ];
		}
		return $caps;
	}

	/* --------------------------- Cabeçalhos e cache --------------------------- */

	public static function cabecalhos() {
		if ( headers_sent() ) {
			return;
		}
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()' );
		if ( is_ssl() ) {
			header( 'Strict-Transport-Security: max-age=31536000' );
		}
		header_remove( 'X-Powered-By' );
	}

	/** Página de quem está logado, ou com formulário do clube: o navegador e o cache da hospedagem não guardam. */
	public static function sem_cache() {
		$privada = is_user_logged_in() || is_page( (array) apply_filters( 'cav_paginas_privadas', self::PAGINAS_PRIVADAS ) );

		if ( $privada ) {
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
			nocache_headers();
		}
	}

	/* ------------------------------ Limpeza diária ------------------------------ */

	public static function agendar_limpeza() {
		if ( ! wp_next_scheduled( 'cav_limpeza_diaria' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'cav_limpeza_diaria' );
		}
	}

	/**
	 * Guarda só o que precisa:
	 *  - consultas de CPF no terminal: 90 dias;
	 *  - endereço de IP dos usos e das inscrições: 12 meses (o registro do uso em si fica).
	 */
	public static function limpeza() {
		global $wpdb;

		$wpdb->query( $wpdb->prepare(
			'DELETE FROM ' . CAV_Install::tabela_consultas() . ' WHERE criado_em < %s',
			wp_date( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS )
		) );

		$limite = wp_date( 'Y-m-d H:i:s', time() - 365 * DAY_IN_SECONDS );
		foreach ( [ CAV_Install::tabela_usos(), CAV_Install::tabela_inscricoes() ] as $tabela ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$tabela} SET ip = NULL WHERE ip IS NOT NULL AND criado_em < %s", $limite ) );
		}
	}

	/* --------------------------------- Avisos --------------------------------- */

	public static function avisos() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$local = 'local' === wp_get_environment_type();
		$msgs  = [];

		if ( ! CAV_Cripto::tem_chave() ) {
			// Dados protegidos com a chave forte, mas a constante sumiu: os CPFs não abrem.
			global $wpdb;
			$perdida = (bool) $wpdb->get_var( $wpdb->prepare(
				"SELECT 1 FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value LIKE %s LIMIT 1",
				CAV_Membro::META_CPF,
				$wpdb->esc_like( CAV_Cripto::FORTE ) . '%'
			) );
			if ( $perdida ) {
				$msgs[] = [ 'error', '<strong>Clube:</strong> a chave que protege os CPFs (<code>CAV_CHAVE_DADOS</code>) sumiu do wp-config.php. Sem ela os CPFs gravados não abrem. Coloque a chave de volta.' ];
			} elseif ( ! $local ) {
				$msgs[] = [ 'warning', '<strong>Clube:</strong> falta a chave que protege os CPFs no banco. Gere uma (<code>openssl rand -hex 32</code>), coloque no wp-config.php como <code>define( \'CAV_CHAVE_DADOS\', \'...\' );</code> e guarde uma cópia fora do backup do banco.' ];
			}
		}

		if ( ! $local ) {
			if ( ! is_ssl() ) {
				$msgs[] = [ 'error', '<strong>Clube:</strong> o site não está em https. Login e CPFs trafegam sem proteção. Ative o certificado SSL da hospedagem.' ];
			}

			$demo = array_filter( [ 'aluno.demo', 'aluno.vencido', 'parceiro.demo', 'recepcao.demo' ], 'username_exists' );
			if ( $demo ) {
				$msgs[] = [ 'error', '<strong>Clube:</strong> existem contas de teste neste site (' . esc_html( implode( ', ', $demo ) ) . '), com senha pública. Apague em Usuários antes de divulgar.' ];
			}

			if ( username_exists( 'admin' ) ) {
				$msgs[] = [ 'warning', '<strong>Clube:</strong> existe um usuário chamado "admin", o primeiro nome que robôs tentam. Crie outro administrador com nome diferente e apague este.' ];
			}
		}

		foreach ( $msgs as $m ) {
			printf( '<div class="notice notice-%s"><p>%s</p></div>', esc_attr( $m[0] ), wp_kses( $m[1], [ 'strong' => [], 'code' => [] ] ) );
		}
	}
}

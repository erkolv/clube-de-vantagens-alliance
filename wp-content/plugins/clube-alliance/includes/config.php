<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Configurações do clube, em Clube → Configurações.
 *
 * O valor mensal fica num lugar só. Os textos do site usam as marcas abaixo e o
 * WordPress troca pelo valor atual na hora de mostrar a página:
 *
 *   {{valor_clube}}      R$ 19,90
 *   {{valor_clube_ano}}  R$ 238,80   (o valor de 12 meses)
 *
 * Também funcionam os shortcodes [cav_valor] e [cav_valor_ano], para quem prefere.
 */
class CAV_Config {

	const OPT_VALOR  = 'cav_valor_mensal';
	const OPT_GOOGLE = 'cav_google_client_id';
	const PADRAO     = 19.90;

	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'menu' ], 20 );
		add_action( 'admin_post_cav_salvar_config', [ __CLASS__, 'salvar' ] );

		add_shortcode( 'cav_valor', [ __CLASS__, 'sc_valor' ] );
		add_shortcode( 'cav_valor_ano', [ __CLASS__, 'sc_valor_ano' ] );

		add_filter( 'the_content', [ __CLASS__, 'trocar_marcas' ], 99 );
		add_filter( 'widget_text', [ __CLASS__, 'trocar_marcas' ], 99 );
		add_filter( 'elementor/widget/render_content', [ __CLASS__, 'trocar_marcas' ], 99 );
	}

	/* ------------------------------- Valores ------------------------------- */

	/** Valor mensal do clube, em reais (float). */
	public static function valor() {
		$v = get_option( self::OPT_VALOR, '' );
		return ( '' !== $v && is_numeric( $v ) && (float) $v > 0 ) ? (float) $v : self::PADRAO;
	}

	public static function formatar( $valor ) {
		return 'R$ ' . number_format( (float) $valor, 2, ',', '.' );
	}

	public static function valor_formatado() {
		return self::formatar( self::valor() );
	}

	public static function valor_ano_formatado() {
		return self::formatar( round( self::valor() * 12, 2 ) );
	}

	public static function google_client_id() {
		return trim( (string) get_option( self::OPT_GOOGLE, '' ) );
	}

	/** Troca as marcas {{valor_clube}} e {{valor_clube_ano}} pelo valor atual. */
	public static function trocar_marcas( $texto ) {
		if ( ! is_string( $texto ) || false === strpos( $texto, '{{valor_clube' ) ) {
			return $texto;
		}

		return str_replace(
			[ '{{valor_clube_ano}}', '{{valor_clube}}' ],
			[ self::valor_ano_formatado(), self::valor_formatado() ],
			$texto
		);
	}

	public static function sc_valor() {
		return esc_html( self::valor_formatado() );
	}

	public static function sc_valor_ano() {
		return esc_html( self::valor_ano_formatado() );
	}

	/* -------------------------------- Admin -------------------------------- */

	public static function menu() {
		add_submenu_page(
			'cav-relatorio',
			'Configurações do clube',
			'Configurações',
			'cav_gerenciar',
			'cav-config',
			[ __CLASS__, 'tela' ]
		);
	}

	public static function tela() {
		if ( ! current_user_can( 'cav_gerenciar' ) ) {
			wp_die( 'Sem permissão.' );
		}

		$valor_campo = number_format( self::valor(), 2, ',', '' );
		$google      = self::google_client_id();
		$parsed      = wp_parse_url( home_url() );
		$origem      = ( $parsed['scheme'] ?? 'https' ) . '://' . ( $parsed['host'] ?? '' ) . ( isset( $parsed['port'] ) ? ':' . $parsed['port'] : '' );
		// phpcs:ignore WordPress.Security.NonceVerification
		$salvo = isset( $_GET['salvo'] );
		$erro_n = isset( $_GET['erro'] ) ? absint( $_GET['erro'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$erro   = (bool) $erro_n;
		?>
		<div class="wrap">
			<h1>Configurações do clube</h1>

			<?php if ( $salvo ) : ?>
				<div class="notice notice-success is-dismissible"><p>Configurações salvas. O novo valor já aparece em todo o site.</p></div>
			<?php endif; ?>
			<?php if ( $erro ) : ?>
				<div class="notice notice-error"><p><?php echo 2 === (int) $erro_n ? 'Esse ID do cliente Google não parece certo. Ele termina com .apps.googleusercontent.com. Nada foi salvo.' : 'O valor precisa ser um número maior que zero, por exemplo 19,90.'; ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="cav_salvar_config">
				<?php wp_nonce_field( 'cav_salvar_config', 'cav_nonce' ); ?>

				<h2>Valor do clube</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="cav_valor">Valor por mês</label></th>
						<td>
							R$ <input type="text" id="cav_valor" name="cav_valor" value="<?php echo esc_attr( $valor_campo ); ?>" class="small-text" inputmode="decimal" required>
							<p class="description">
								É o adicional que a Alliance cobra do aluno na mensalidade. Hoje o site mostra
								<strong><?php echo esc_html( self::valor_formatado() ); ?> por mês</strong> e
								<strong><?php echo esc_html( self::valor_ano_formatado() ); ?> por ano</strong>.
							</p>
							<p class="description">
								Ao salvar, o valor muda na página inicial, em "O que é o clube", em "Quero fazer parte" e no aceite
								que o aluno confirma ao pedir acesso. Quem já pediu antes continua com o aceite do valor da época
								registrado.
							</p>
						</td>
					</tr>
				</table>

				<h2>Entrar com Google</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="cav_google">ID do cliente Google</label></th>
						<td>
							<input type="text" id="cav_google" name="cav_google" value="<?php echo esc_attr( $google ); ?>" class="large-text code" placeholder="123456789-abc...apps.googleusercontent.com" autocomplete="off">
							<p class="description">
								Enquanto este campo estiver vazio, o botão "Entrar com Google" não aparece na página de login.
								A pessoa só entra com Google se o e-mail da conta Google já for o e-mail cadastrado no clube. O Google
								não cria cadastro novo.
							</p>
							<details style="margin-top:10px">
								<summary><strong>Como conseguir o ID do cliente</strong> (uma vez só, uns 10 minutos)</summary>
								<ol style="margin-top:8px">
									<li>Entre em <code>console.cloud.google.com</code> com a conta Google da Alliance e crie um projeto (por exemplo "Clube Alliance").</li>
									<li>Em <strong>APIs e serviços → Tela de permissão OAuth</strong>, escolha "Externo", preencha o nome do app e o e-mail de suporte, e publique o app.</li>
									<li>Em <strong>Credenciais → Criar credenciais → ID do cliente OAuth</strong>, tipo <strong>Aplicativo da Web</strong>.</li>
									<li>Em <strong>Origens JavaScript autorizadas</strong>, adicione: <code><?php echo esc_html( $origem ); ?></code>. Se o site tiver mais de um endereço (por exemplo com e sem www, ou o endereço do Codespaces), adicione cada um.</li>
									<li>Não precisa preencher "URIs de redirecionamento". Copie o ID do cliente que o Google mostrar e cole acima.</li>
								</ol>
							</details>
						</td>
					</tr>
				</table>

				<?php submit_button( 'Salvar configurações' ); ?>
			</form>
		</div>
		<?php
	}

	public static function salvar() {
		if ( ! current_user_can( 'cav_gerenciar' ) ) {
			wp_die( 'Sem permissão.' );
		}
		check_admin_referer( 'cav_salvar_config', 'cav_nonce' );

		$valor = CAV_Ofertas::ler_preco( isset( $_POST['cav_valor'] ) ? sanitize_text_field( wp_unslash( $_POST['cav_valor'] ) ) : '' );

		$volta = admin_url( 'admin.php?page=cav-config' );

		if ( null === $valor || $valor <= 0 || $valor > 9999 ) {
			wp_safe_redirect( add_query_arg( 'erro', 1, $volta ) );
			exit;
		}

		$google = isset( $_POST['cav_google'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['cav_google'] ) ) ) : '';

		// O ID do cliente tem sempre o formato "numeros-texto.apps.googleusercontent.com".
		if ( '' !== $google && ! preg_match( '/^[0-9A-Za-z._-]+\.apps\.googleusercontent\.com$/', $google ) ) {
			wp_safe_redirect( add_query_arg( 'erro', 2, $volta ) );
			exit;
		}

		update_option( self::OPT_VALOR, number_format( $valor, 2, '.', '' ) );
		update_option( self::OPT_GOOGLE, $google );

		wp_safe_redirect( add_query_arg( 'salvo', 1, $volta ) );
		exit;
	}
}

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clube → E-mails: o serviço que envia os e-mails do clube (SMTP).
 *
 * O WordPress sozinho manda e-mail pelo servidor da hospedagem, que costuma cair no spam
 * ou nem sair. Aqui a Alliance cola os dados de um serviço de envio (Brevo, Amazon SES,
 * Postmark, o e-mail do próprio domínio...) e todos os e-mails do site passam a sair por ele.
 *
 * A senha fica no banco. Quem preferir pode defini-la no wp-config.php, e então ela não
 * aparece nem é salva aqui:  define( 'CAV_SMTP_SENHA', '...' );
 */
class CAV_SMTP {

	const OPT = 'cav_smtp';

	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'menu' ], 25 );
		add_action( 'admin_post_cav_smtp_salvar', [ __CLASS__, 'salvar' ] );
		add_action( 'admin_post_cav_smtp_teste', [ __CLASS__, 'teste' ] );

		add_action( 'phpmailer_init', [ __CLASS__, 'configurar' ] );
		add_filter( 'wp_mail_from', [ __CLASS__, 'de_email' ], 99 );
		add_filter( 'wp_mail_from_name', [ __CLASS__, 'de_nome' ], 99 );
	}

	/** Valores salvos, com o que faltar preenchido. */
	public static function cfg() {
		$c = get_option( self::OPT, [] );
		$c = is_array( $c ) ? $c : [];

		$c = wp_parse_args( $c, [
			'host'       => '',
			'porta'      => 587,
			'seguranca'  => 'tls',
			'usuario'    => '',
			'senha'      => '',
			'de_email'   => '',
			'de_nome'    => '',
		] );

		if ( defined( 'CAV_SMTP_SENHA' ) && CAV_SMTP_SENHA ) {
			$c['senha'] = (string) CAV_SMTP_SENHA;
		}

		return $c;
	}

	public static function ativo() {
		return '' !== trim( self::cfg()['host'] );
	}

	public static function configurar( $mailer ) {
		$c = self::cfg();

		if ( '' === trim( $c['host'] ) ) {
			return;
		}

		$mailer->isSMTP();
		$mailer->Host       = trim( $c['host'] );
		$mailer->Port       = (int) $c['porta'];
		$mailer->SMTPAuth   = '' !== $c['usuario'];
		$mailer->Username   = $c['usuario'];
		$mailer->Password   = $c['senha'];
		$mailer->SMTPSecure = in_array( $c['seguranca'], [ 'tls', 'ssl' ], true ) ? $c['seguranca'] : '';
		$mailer->SMTPAutoTLS = 'tls' === $c['seguranca'];
		$mailer->Timeout    = 15;
	}

	public static function de_email( $email ) {
		$c = self::cfg();
		return ( self::ativo() && is_email( $c['de_email'] ) ) ? $c['de_email'] : $email;
	}

	public static function de_nome( $nome ) {
		$c = self::cfg();
		return ( self::ativo() && '' !== trim( $c['de_nome'] ) ) ? $c['de_nome'] : $nome;
	}

	/* ------------------------------ Admin ------------------------------ */

	public static function menu() {
		add_submenu_page( 'cav-relatorio', 'E-mails do clube', 'E-mails', 'cav_gerenciar', 'cav-emails', [ __CLASS__, 'tela' ] );
	}

	public static function tela() {
		if ( ! current_user_can( 'cav_gerenciar' ) ) {
			wp_die( 'Sem permissão.' );
		}

		$c      = self::cfg();
		$aviso  = isset( $_GET['aviso'] ) ? sanitize_key( wp_unslash( $_GET['aviso'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$msg    = get_transient( 'cav_smtp_msg_' . get_current_user_id() );
		$senha_no_arquivo = defined( 'CAV_SMTP_SENHA' ) && CAV_SMTP_SENHA;
		?>
		<div class="wrap">
			<h1>E-mails do clube</h1>

			<?php if ( 'salvo' === $aviso ) : ?>
				<div class="notice notice-success is-dismissible"><p>Configuração salva. Mande um e-mail de teste para conferir.</p></div>
			<?php elseif ( 'invalido' === $aviso ) : ?>
				<div class="notice notice-error"><p>Confira os campos: a porta precisa ser um número e o e-mail de envio precisa ser válido. Nada foi salvo.</p></div>
			<?php elseif ( 'teste_ok' === $aviso ) : ?>
				<div class="notice notice-success is-dismissible"><p>E-mail de teste enviado para <?php echo esc_html( wp_get_current_user()->user_email ); ?>. Se ele chegou na caixa de entrada (e não no spam), está tudo certo.</p></div>
			<?php elseif ( 'teste_erro' === $aviso ) : ?>
				<div class="notice notice-error"><p>O e-mail de teste não saiu.<?php echo $msg ? ' O servidor respondeu: <code>' . esc_html( $msg ) . '</code>' : ''; ?></p></div>
			<?php endif; ?>

			<p>
				Os e-mails do clube (acesso liberado, criar senha, avisos de sorteio) saem por aqui.
				<?php if ( self::ativo() ) : ?>
					<strong style="color:#1a7f37">Envio por SMTP ligado.</strong>
				<?php else : ?>
					<strong style="color:#b32d2e">Ainda sem serviço de envio.</strong> Enquanto o campo Servidor estiver vazio, o site usa o envio simples da hospedagem, que costuma cair no spam.
				<?php endif; ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="cav_smtp_salvar">
				<?php wp_nonce_field( 'cav_smtp_salvar' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="cav_smtp_host">Servidor (SMTP)</label></th>
						<td><input type="text" id="cav_smtp_host" name="host" value="<?php echo esc_attr( $c['host'] ); ?>" class="regular-text" placeholder="smtp-relay.brevo.com" autocomplete="off">
							<p class="description">O endereço que o serviço de envio informa. Deixe em branco para desligar.</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="cav_smtp_porta">Porta</label></th>
						<td><input type="number" id="cav_smtp_porta" name="porta" value="<?php echo esc_attr( (int) $c['porta'] ); ?>" class="small-text" min="1" max="65535">
							<select name="seguranca" aria-label="Segurança">
								<option value="tls" <?php selected( $c['seguranca'], 'tls' ); ?>>TLS (porta 587)</option>
								<option value="ssl" <?php selected( $c['seguranca'], 'ssl' ); ?>>SSL (porta 465)</option>
								<option value="" <?php selected( $c['seguranca'], '' ); ?>>Sem criptografia</option>
							</select></td>
					</tr>
					<tr>
						<th scope="row"><label for="cav_smtp_usuario">Usuário</label></th>
						<td><input type="text" id="cav_smtp_usuario" name="usuario" value="<?php echo esc_attr( $c['usuario'] ); ?>" class="regular-text" autocomplete="off"></td>
					</tr>
					<tr>
						<th scope="row"><label for="cav_smtp_senha">Senha</label></th>
						<td>
							<?php if ( $senha_no_arquivo ) : ?>
								<p class="description">A senha está definida no wp-config.php (<code>CAV_SMTP_SENHA</code>).</p>
							<?php else : ?>
								<input type="password" id="cav_smtp_senha" name="senha" value="" class="regular-text" autocomplete="new-password"
								       placeholder="<?php echo '' !== $c['senha'] ? 'Já salva. Deixe em branco para manter' : ''; ?>">
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cav_smtp_de_email">E-mail de envio</label></th>
						<td><input type="email" id="cav_smtp_de_email" name="de_email" value="<?php echo esc_attr( $c['de_email'] ); ?>" class="regular-text" placeholder="clube@alliancemogi.com.br">
							<p class="description">O remetente que a pessoa vê. Precisa ser um e-mail (ou domínio) que o serviço de envio já verificou, senão ele recusa.</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="cav_smtp_de_nome">Nome de envio</label></th>
						<td><input type="text" id="cav_smtp_de_nome" name="de_nome" value="<?php echo esc_attr( $c['de_nome'] ); ?>" class="regular-text" placeholder="Clube de Vantagens Alliance"></td>
					</tr>
				</table>

				<?php submit_button( 'Salvar' ); ?>
			</form>

			<h2>Testar</h2>
			<p>Manda um e-mail de teste para o seu e-mail de administrador (<?php echo esc_html( wp_get_current_user()->user_email ); ?>).</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="cav_smtp_teste">
				<?php wp_nonce_field( 'cav_smtp_teste' ); ?>
				<?php submit_button( 'Enviar e-mail de teste', 'secondary' ); ?>
			</form>
		</div>
		<?php
	}

	private static function volta( $aviso ) {
		wp_safe_redirect( add_query_arg( 'aviso', $aviso, admin_url( 'admin.php?page=cav-emails' ) ) );
		exit;
	}

	public static function salvar() {
		if ( ! current_user_can( 'cav_gerenciar' ) ) {
			wp_die( 'Sem permissão.' );
		}
		check_admin_referer( 'cav_smtp_salvar' );

		$antigo = self::cfg();

		$host = isset( $_POST['host'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['host'] ) ) ) : '';
		$host = preg_replace( '#^[a-z]+://#i', '', $host );
		$porta = isset( $_POST['porta'] ) ? (int) $_POST['porta'] : 0;
		$seg   = isset( $_POST['seguranca'] ) ? sanitize_key( wp_unslash( $_POST['seguranca'] ) ) : '';
		$seg   = in_array( $seg, [ 'tls', 'ssl' ], true ) ? $seg : '';
		$de    = isset( $_POST['de_email'] ) ? sanitize_email( wp_unslash( $_POST['de_email'] ) ) : '';

		if ( '' !== $host && ( $porta < 1 || $porta > 65535 || ! preg_match( '/^[A-Za-z0-9._-]+$/', $host ) ) ) {
			self::volta( 'invalido' );
		}
		if ( isset( $_POST['de_email'] ) && '' !== trim( (string) wp_unslash( $_POST['de_email'] ) ) && '' === $de ) {
			self::volta( 'invalido' );
		}

		$senha = isset( $_POST['senha'] ) ? (string) wp_unslash( $_POST['senha'] ) : '';
		if ( '' === $senha ) {
			$senha = get_option( self::OPT, [] )['senha'] ?? '';
		}

		update_option( self::OPT, [
			'host'      => $host,
			'porta'     => $porta ?: 587,
			'seguranca' => $seg,
			'usuario'   => isset( $_POST['usuario'] ) ? sanitize_text_field( wp_unslash( $_POST['usuario'] ) ) : '',
			'senha'     => $senha,
			'de_email'  => $de,
			'de_nome'   => isset( $_POST['de_nome'] ) ? sanitize_text_field( wp_unslash( $_POST['de_nome'] ) ) : '',
		], false );

		unset( $antigo );
		self::volta( 'salvo' );
	}

	public static function teste() {
		if ( ! current_user_can( 'cav_gerenciar' ) ) {
			wp_die( 'Sem permissão.' );
		}
		check_admin_referer( 'cav_smtp_teste' );

		$erro = '';
		add_action( 'wp_mail_failed', static function ( $e ) use ( &$erro ) {
			$erro = $e->get_error_message();
		} );

		$para = wp_get_current_user()->user_email;
		$ok   = wp_mail(
			$para,
			'Teste de e-mail do clube de vantagens',
			'<p>Este é um e-mail de teste do clube de vantagens da Alliance. Se você está lendo, o envio está funcionando.</p>',
			[ 'Content-Type: text/html; charset=UTF-8' ]
		);

		set_transient( 'cav_smtp_msg_' . get_current_user_id(), $erro, 5 * MINUTE_IN_SECONDS );
		self::volta( $ok ? 'teste_ok' : 'teste_erro' );
	}
}

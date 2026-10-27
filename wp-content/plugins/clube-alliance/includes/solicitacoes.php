<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CAV_Solicitacoes {

	const STATUS_PENDENTE = 'pendente';
	const STATUS_ATIVO    = 'ativo';
	const STATUS_RECUSADO = 'recusado';

	const META_WHATSAPP = 'cav_whatsapp';
	const META_CEP      = 'cav_cep';          // só os 8 números
	const META_TIPO     = 'cav_tipo';         // 'aluno' (padrão) ou 'nao_aluno'; muda só o valor
	const META_TURMA    = 'cav_turma';  // só em pedidos antigos
	const META_PLANO    = 'cav_plano';  // só em cadastros antigos (o clube não depende mais do plano)
	const META_PEDIDO   = 'cav_pedido_em';
	const META_NASC     = 'cav_nascimento';   // Y-m-d
	const META_RESP     = 'cav_responsavel';  // array: nome, cpf, em (só para menores de 18)
	const IDADE_MINIMA  = 12;
	const META_ACEITE   = 'cav_aceite_mensalidade'; // array: valor, texto, em, ip

	// Candidatura de estabelecimento
	const CAND_EMAIL    = '_cav_cand_email';
	const CAND_RESP     = '_cav_cand_responsavel';
	const CAND_ZAP      = '_cav_cand_whatsapp';
	const CAND_END      = '_cav_cand_endereco';
	const CAND_CAT      = '_cav_cand_categoria';
	const CAND_BEN      = '_cav_cand_beneficio';
	const CAND_STATUS   = '_cav_cand_status';

	/** Rótulos dos planos em cadastros antigos. */
	const PLANOS = [
		'recorrente'          => 'Recorrente',
		'recorrente_personal' => 'Recorrente + personal',
		'mensal'              => 'Mensal',
	];

	public static function rotulo_plano( $chave ) {
		if ( 'outro' === $chave ) {
			return 'Outro plano';
		}
		return self::PLANOS[ $chave ] ?? '';
	}

	/** Idade em anos completos numa data Y-m-d, ou null se a data não vale. */
	public static function idade( $nascimento ) {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $nascimento ) ) {
			return null;
		}
		try {
			$n    = new DateTimeImmutable( $nascimento );
			$hoje = new DateTimeImmutable( current_time( 'Y-m-d' ) );
		} catch ( Exception $e ) {
			return null;
		}
		if ( $n > $hoje || $n->format( 'Y-m-d' ) !== $nascimento ) {
			return null;
		}
		return (int) $n->diff( $hoje )->y;
	}

	public static function init() {
		add_action( 'init', [ __CLASS__, 'registrar_cpt' ] );

		add_action( 'admin_post_nopriv_cav_solicitar', [ __CLASS__, 'receber_solicitacao' ] );
		add_action( 'admin_post_cav_solicitar', [ __CLASS__, 'receber_solicitacao' ] );
		add_action( 'admin_post_nopriv_cav_candidatar', [ __CLASS__, 'receber_candidatura' ] );
		add_action( 'admin_post_cav_candidatar', [ __CLASS__, 'receber_candidatura' ] );

		add_shortcode( 'cav_solicitar', [ __CLASS__, 'form_solicitar' ] );
		add_shortcode( 'cav_candidatura', [ __CLASS__, 'form_candidatura' ] );
	}

	public static function registrar_cpt() {
		register_post_type( 'cav_candidatura', [
			'labels'      => [ 'name' => 'Candidaturas', 'singular_name' => 'Candidatura' ],
			'public'      => false,
			'show_ui'     => false,
			'supports'    => [ 'title' ],
		] );
	}

	/* ---------------------------------------------------------------
	 * Aluno
	 * --------------------------------------------------------------- */

	/** CEP só com números. O Excel come o zero da frente, então 7 dígitos viram 8. Devolve '' se não der 8. */
	public static function normalizar_cep( $texto ) {
		$d = preg_replace( '/\D+/', '', (string) $texto );
		if ( 7 === strlen( $d ) ) {
			$d = '0' . $d;
		}
		return 8 === strlen( $d ) ? $d : '';
	}

	/** 01234567 -> 01234-567 */
	public static function formatar_cep( $cep ) {
		$cep = self::normalizar_cep( $cep );
		return '' === $cep ? '' : substr( $cep, 0, 5 ) . '-' . substr( $cep, 5 );
	}

	public static function status( $user_id ) {
		$s = get_user_meta( $user_id, 'cav_status', true );
		return $s ?: self::STATUS_PENDENTE;
	}

	public static function form_solicitar() {
		wp_enqueue_style( 'cav' );

		$msg = isset( $_GET['cav'] ) ? sanitize_text_field( wp_unslash( $_GET['cav'] ) ) : '';

		if ( 'enviado' === $msg ) {
			$tipo = isset( $_GET['t'] ) && 'nao_aluno' === $_GET['t'] ? 'nao_aluno' : 'aluno';
			$link = ( isset( $_GET['t'] ) && in_array( $_GET['t'], [ 'aluno', 'nao_aluno' ], true ) ) ? CAV_Config::link_pagamento( $tipo ) : '';

			if ( $link ) {
				return '<div class="cav-card"><p><strong>Pedido enviado.</strong><br>Falta o pagamento: ' . esc_html( CAV_Config::formatar( CAV_Config::valor_para( $tipo ) ) )
					. ', em até 12x no cartão de crédito. Também mandamos o link para o seu e-mail. '
					. 'Assim que a recepção confirmar o pagamento, o acesso é liberado e você recebe um e-mail.</p>'
					. '<p><a class="cav-btn" href="' . esc_url( $link ) . '" rel="noopener">Pagar agora</a></p>'
					. '<p class="cav-ajuda">Se preferir, também dá para pagar na recepção da Alliance.</p></div>';
			}

			return '<div class="cav-card"><strong>Pedido enviado.</strong><br>Pague o clube na recepção da Alliance. Assim que o pagamento for conferido, '
				. 'o acesso é liberado e você recebe um e-mail.</div>';
		}

		ob_start();
		?>
		<form class="cav-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="cav_solicitar">
			<?php wp_nonce_field( 'cav_solicitar' ); ?>
			<?php echo CAV_Seguranca::campos_antirrobo(); // phpcs:ignore WordPress.Security.EscapeOutput ?>

			<?php if ( $msg && 'enviado' !== $msg ) : ?>
				<p class="cav-erro-form"><?php echo esc_html( self::mensagem_erro( $msg ) ); ?></p>
			<?php endif; ?>

			<fieldset class="cav-tipo">
				<legend>Você é aluno da Alliance?</legend>
				<label class="cav-consent">
					<input type="radio" name="tipo" value="aluno" checked>
					<span>Sou aluno. O clube custa <?php echo esc_html( CAV_Config::valor_formatado() ); ?>.</span>
				</label>
				<label class="cav-consent">
					<input type="radio" name="tipo" value="nao_aluno">
					<span>Ainda não sou aluno. O clube custa <?php echo esc_html( CAV_Config::valor_nao_aluno_formatado() ); ?>.</span>
				</label>
			</fieldset>

			<label for="cav-nome">Nome completo</label>
			<input id="cav-nome" name="nome" type="text" maxlength="120" required>

			<label for="cav-email">E-mail</label>
			<input id="cav-email" name="email" type="email" maxlength="100" required>

			<label for="cav-cpf">CPF</label>
			<input id="cav-cpf" name="cpf" type="text" inputmode="numeric" maxlength="14" required>

			<label for="cav-cep">CEP</label>
			<input id="cav-cep" name="cep" type="text" inputmode="numeric" autocomplete="postal-code" maxlength="9" placeholder="00000-000" required>

			<label for="cav-zap">WhatsApp</label>
			<input id="cav-zap" name="whatsapp" type="text" maxlength="20">

			<label for="cav-nasc">Data de nascimento</label>
			<input id="cav-nasc" name="nascimento" type="date" required>

			<fieldset class="cav-responsavel">
				<legend>Aluno com menos de 18 anos</legend>
				<p class="cav-ajuda">O clube é para alunos a partir de <?php echo (int) self::IDADE_MINIMA; ?> anos. Quem tem menos de 18 precisa da autorização de um responsável. Os alunos maiores de idade podem pular esta parte.</p>

				<label for="cav-resp-nome">Nome do responsável</label>
				<input id="cav-resp-nome" name="resp_nome" type="text" maxlength="120">

				<label for="cav-resp-cpf">CPF do responsável</label>
				<input id="cav-resp-cpf" name="resp_cpf" type="text" inputmode="numeric" maxlength="14">

				<label class="cav-consent">
					<input type="checkbox" name="resp_autoriza" value="1">
					<span>Sou o responsável pelo aluno e autorizo a participação dele no clube.</span>
				</label>
			</fieldset>

			<label class="cav-consent">
				<input type="checkbox" name="consentimento" value="1" required>
				<span>Autorizo que estabelecimentos parceiros consultem meu CPF para verificar se tenho direito ao benefício.</span>
			</label>

			<?php
			$v_aluno = CAV_Config::valor();
			$v_nao   = CAV_Config::valor_nao_aluno();
			?>
			<input type="hidden" name="aceite_valor_aluno" value="<?php echo esc_attr( number_format( $v_aluno, 2, '.', '' ) ); ?>">
			<input type="hidden" name="aceite_valor_nao_aluno" value="<?php echo esc_attr( number_format( $v_nao, 2, '.', '' ) ); ?>">
			<label class="cav-consent cav-consent--valor">
				<input type="checkbox" name="aceite_mensalidade" value="1" required>
				<span data-cav-aceite="aluno"><?php echo esc_html( self::texto_aceite( $v_aluno, 'aluno' ) ); ?></span>
				<span data-cav-aceite="nao_aluno"><?php echo esc_html( self::texto_aceite( $v_nao, 'nao_aluno' ) ); ?></span>
			</label>

			<button type="submit" class="cav-btn">Enviar pedido</button>
		</form>
		<script>
		(function () {
			var f = document.currentScript.previousElementSibling;
			if (!f) { return; }
			function atualizar() {
				var t = f.querySelector('input[name="tipo"]:checked');
				t = t ? t.value : 'aluno';
				f.querySelectorAll('[data-cav-aceite]').forEach(function (s) {
					s.hidden = s.getAttribute('data-cav-aceite') !== t;
				});
			}
			f.addEventListener('change', atualizar);
			atualizar();
		})();
		</script>
		<?php
		return ob_get_clean();
	}

	/** O texto que o aluno aceita. É guardado junto com o pedido, exatamente como foi mostrado. */
	public static function texto_aceite( $valor, $tipo = 'aluno' ) {
		$onde = CAV_Config::link_pagamento( $tipo )
			? 'pelo link de pagamento que vou receber por e-mail, ou na recepção da Alliance'
			: 'na recepção da Alliance';

		return sprintf(
			'Estou ciente de que o clube custa %1$s por %2$s%3$s, de que o pagamento é feito %4$s (no cartão de crédito, em até 12x) e de que o meu acesso só é liberado depois do pagamento.',
			CAV_Config::formatar( $valor ),
			CAV_Config::prazo_formatado(),
			'nao_aluno' === $tipo ? ' (valor para quem não é aluno), à parte da mensalidade' : ', à parte da mensalidade',
			$onde
		);
	}

	/** 'aluno' ou 'nao_aluno'. Pedidos antigos e importados são de aluno. */
	public static function tipo( $user_id ) {
		return 'nao_aluno' === get_user_meta( $user_id, self::META_TIPO, true ) ? 'nao_aluno' : 'aluno';
	}

	public static function rotulo_tipo( $tipo ) {
		return 'nao_aluno' === $tipo ? 'Não aluno' : 'Aluno';
	}

	private static function mensagem_erro( $codigo ) {
		$mapa = [
			'cpf'         => 'CPF inválido. Confira os números.',
			// Mesma frase para CPF e e-mail já cadastrados: o formulário não confirma quem faz parte do clube.
			'cpf_usado'   => 'Já existe um cadastro com esse e-mail ou CPF. Se for o seu, use "Entrar" e peça uma nova senha se precisar.',
			'email_usado' => 'Já existe um cadastro com esse e-mail ou CPF. Se for o seu, use "Entrar" e peça uma nova senha se precisar.',
			'muitos'      => 'Muitos pedidos vindos deste endereço em pouco tempo. Espere um pouco ou fale com a recepção.',
			'tempo'       => 'Não consegui validar o envio. Recarregue a página e envie de novo.',
			'campos'      => 'Preencha todos os campos obrigatórios.',
			'cep'         => 'Informe o CEP com 8 números.',
			'nascimento'  => 'Informe a data de nascimento do aluno.',
			'idade'       => 'O clube é para alunos a partir de ' . self::IDADE_MINIMA . ' anos.',
			'responsavel' => 'Para alunos com menos de 18 anos, informe o nome e o CPF do responsável e marque a autorização.',
			'consent'     => 'É preciso autorizar a consulta do CPF para continuar.',
			'aceite'      => 'Para entrar no clube é preciso concordar com o valor e com a forma de pagamento.',
			'valor_mudou' => 'O valor do clube mudou enquanto você preenchia. Leia o valor de novo e confirme.',
			'falha'       => 'Não foi possível registrar seu pedido. Tente de novo.',
		];
		return $mapa[ $codigo ] ?? 'Não foi possível concluir.';
	}

	private static function voltar( $codigo, $extra = [] ) {
		$url = wp_get_referer() ?: home_url();
		wp_safe_redirect( add_query_arg( array_merge( [ 'cav' => $codigo ], $extra ), remove_query_arg( [ 'cav', 't' ], $url ) ) );
		exit;
	}

	public static function receber_solicitacao() {
		check_admin_referer( 'cav_solicitar' );

		// Robô preenche o campo escondido: finge que deu certo e não grava nada.
		$robo = CAV_Seguranca::robo();
		if ( 'armadilha' === $robo ) {
			self::voltar( 'enviado' );
		}
		if ( $robo ) {
			self::voltar( 'tempo' );
		}
		// 15 por hora por endereço: dá para a recepção cadastrar vários alunos seguidos no mesmo wi-fi.
		if ( ! CAV_Seguranca::limitar( 'pedido', 15, HOUR_IN_SECONDS ) ) {
			self::voltar( 'muitos' );
		}

		$nome  = CAV_Seguranca::texto( 'nome', 120 );
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( strlen( $email ) > 100 ) {
			$email = '';
		}
		$cpf   = isset( $_POST['cpf'] ) ? CAV_CPF::normalizar( wp_unslash( $_POST['cpf'] ) ) : '';

		if ( ! $nome || ! is_email( $email ) || ! $cpf ) {
			self::voltar( 'campos' );
		}
		$cep = self::normalizar_cep( isset( $_POST['cep'] ) ? wp_unslash( $_POST['cep'] ) : '' );
		if ( '' === $cep ) {
			self::voltar( 'cep' );
		}
		$nasc  = isset( $_POST['nascimento'] ) ? sanitize_text_field( wp_unslash( $_POST['nascimento'] ) ) : '';
		$idade = self::idade( $nasc );
		if ( null === $idade ) {
			self::voltar( 'nascimento' );
		}
		if ( $idade < self::IDADE_MINIMA ) {
			self::voltar( 'idade' );
		}

		$resp = null;
		if ( $idade < 18 ) {
			$resp_nome = CAV_Seguranca::texto( 'resp_nome', 120 );
			$resp_cpf  = isset( $_POST['resp_cpf'] ) ? CAV_CPF::normalizar( wp_unslash( $_POST['resp_cpf'] ) ) : '';
			if ( '' === $resp_nome || ! $resp_cpf || ! CAV_CPF::valido( $resp_cpf ) || empty( $_POST['resp_autoriza'] ) ) {
				self::voltar( 'responsavel' );
			}
			$resp = [ 'nome' => $resp_nome, 'cpf' => CAV_Cripto::cifrar( $resp_cpf ), 'em' => current_time( 'mysql' ) ];
		}

		if ( empty( $_POST['consentimento'] ) ) {
			self::voltar( 'consent' );
		}
		if ( empty( $_POST['aceite_mensalidade'] ) ) {
			self::voltar( 'aceite' );
		}

		// O aceite vale para o valor que a pessoa viu. Se mudou no meio do caminho, mostra de novo.
		$tipo = isset( $_POST['tipo'] ) ? sanitize_key( wp_unslash( $_POST['tipo'] ) ) : '';
		if ( ! in_array( $tipo, [ 'aluno', 'nao_aluno' ], true ) ) {
			self::voltar( 'campos' );
		}
		$campo_valor = 'nao_aluno' === $tipo ? 'aceite_valor_nao_aluno' : 'aceite_valor_aluno';
		$valor_visto = isset( $_POST[ $campo_valor ] ) ? CAV_Ofertas::ler_preco( sanitize_text_field( wp_unslash( $_POST[ $campo_valor ] ) ) ) : null;
		if ( null === $valor_visto || abs( $valor_visto - CAV_Config::valor_para( $tipo ) ) > 0.001 ) {
			self::voltar( 'valor_mudou' );
		}

		if ( ! CAV_CPF::valido( $cpf ) ) {
			self::voltar( 'cpf' );
		}
		if ( CAV_CPF::buscar_usuario( $cpf ) ) {
			self::voltar( 'cpf_usado' );
		}
		if ( email_exists( $email ) ) {
			self::voltar( 'email_usado' );
		}

		$user_id = wp_insert_user( [
			'user_login'   => $email,
			'user_email'   => $email,
			'user_pass'    => wp_generate_password( 20 ),
			'display_name' => $nome,
			'first_name'   => strtok( $nome, ' ' ),
			'role'         => 'cav_membro',
		] );

		if ( is_wp_error( $user_id ) ) {
			self::voltar( 'falha' );
		}

		CAV_Membro::set_cpf( $user_id, $cpf );
		update_user_meta( $user_id, 'cav_status', self::STATUS_PENDENTE );
		update_user_meta( $user_id, CAV_Membro::META_CONSENTE, current_time( 'mysql' ) );
		update_user_meta( $user_id, self::META_PEDIDO, current_time( 'mysql' ) );
		update_user_meta( $user_id, self::META_NASC, $nasc );
		update_user_meta( $user_id, self::META_CEP, $cep );
		update_user_meta( $user_id, self::META_TIPO, $tipo );
		if ( $resp ) {
			update_user_meta( $user_id, self::META_RESP, $resp );
		}
		update_user_meta( $user_id, self::META_ACEITE, [
			'valor' => number_format( $valor_visto, 2, '.', '' ),
			'texto' => self::texto_aceite( $valor_visto, $tipo ),
			'tipo'  => $tipo,
			'em'    => current_time( 'mysql' ),
			'ip'    => CAV_Usos::ip(),
		] );

		if ( ! empty( $_POST['whatsapp'] ) ) {
			update_user_meta( $user_id, self::META_WHATSAPP, CAV_Seguranca::telefone( wp_unslash( $_POST['whatsapp'] ) ) );
		}

		do_action( 'cav_solicitacao_recebida', $user_id );

		// O aluno recebe o pedido por e-mail, com o link de pagamento quando ele estiver configurado.
		CAV_Emails::pedido_recebido( get_userdata( $user_id ) );

		self::voltar( 'enviado', [ 't' => $tipo ] );
	}

	/** Aprova o aluno com a validade digitada pela recepção. */
	public static function aprovar_membro( $user_id, $validade ) {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return new WP_Error( 'cav_sem_user', 'Cadastro não encontrado.' );
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $validade ) ) {
			return new WP_Error( 'cav_validade', 'Informe a data de validade.' );
		}

		update_user_meta( $user_id, 'cav_status', self::STATUS_ATIVO );
		CAV_Membro::set_validade( $user_id, $validade );
		delete_user_meta( $user_id, CAV_Membro::META_BLOQUEADO );

		CAV_Emails::membro_aprovado( $user, $validade );
		do_action( 'cav_membro_aprovado', $user_id, $validade );

		return true;
	}

	public static function recusar_membro( $user_id, $motivo = '' ) {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return new WP_Error( 'cav_sem_user', 'Cadastro não encontrado.' );
		}

		update_user_meta( $user_id, 'cav_status', self::STATUS_RECUSADO );
		CAV_Emails::membro_recusado( $user->user_email, CAV_Membro::primeiro_nome( $user ), $motivo );
		do_action( 'cav_membro_recusado', $user_id );

		return true;
	}

	public static function pendentes_membros() {
		return get_users( [
			'meta_key'   => 'cav_status',
			'meta_value' => self::STATUS_PENDENTE,
			'orderby'    => 'registered',
			'order'      => 'ASC',
			'number'     => 100,
		] );
	}

	/* ---------------------------------------------------------------
	 * Estabelecimento
	 * --------------------------------------------------------------- */

	public static function form_candidatura() {
		wp_enqueue_style( 'cav' );

		$msg = isset( $_GET['cav'] ) ? sanitize_text_field( wp_unslash( $_GET['cav'] ) ) : '';

		if ( 'candidatura_enviada' === $msg ) {
			return '<div class="cav-card"><strong>Candidatura enviada.</strong><br>A academia analisa e responde em até dois dias úteis. '
				. 'Se aprovada, você recebe um e-mail com os dados de acesso ao terminal.</div>';
		}

		ob_start();
		?>
		<form class="cav-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="cav_candidatar">
			<?php wp_nonce_field( 'cav_candidatar' ); ?>
			<?php echo CAV_Seguranca::campos_antirrobo(); // phpcs:ignore WordPress.Security.EscapeOutput ?>

			<?php if ( $msg && 0 === strpos( $msg, 'cand_' ) ) : ?>
				<p class="cav-erro-form"><?php echo esc_html( 'cand_email_usado' === $msg ? 'Já existe um cadastro com esse e-mail. Se for o seu, use "Entrar" e peça uma nova senha se precisar.' : self::mensagem_erro( str_replace( 'cand_', '', $msg ) ) ); ?></p>
			<?php endif; ?>

			<label for="cav-estab">Nome do estabelecimento</label>
			<input id="cav-estab" name="estabelecimento" type="text" required>

			<label for="cav-cat">Categoria</label>
			<input id="cav-cat" name="categoria" type="text" placeholder="Barbearia, restaurante, clínica…">

			<label for="cav-resp">Responsável</label>
			<input id="cav-resp" name="responsavel" type="text" required>

			<label for="cav-cemail">E-mail para o acesso</label>
			<input id="cav-cemail" name="email" type="email" required>

			<label for="cav-czap">WhatsApp</label>
			<input id="cav-czap" name="whatsapp" type="text">

			<label for="cav-cend">Endereço</label>
			<input id="cav-cend" name="endereco" type="text">

			<label for="cav-cben">Que benefício você quer oferecer</label>
			<input id="cav-cben" name="beneficio" type="text" required
			       placeholder="Ex.: 15% de desconto no corte, seg a qui">

			<button type="submit" class="cav-btn">Enviar candidatura</button>
		</form>
		<?php
		return ob_get_clean();
	}

	public static function receber_candidatura() {
		check_admin_referer( 'cav_candidatar' );

		$robo = CAV_Seguranca::robo();
		if ( 'armadilha' === $robo ) {
			self::voltar( 'candidatura_enviada' );
		}
		if ( $robo ) {
			self::voltar( 'cand_tempo' );
		}
		if ( ! CAV_Seguranca::limitar( 'candidatura', 5, HOUR_IN_SECONDS ) ) {
			self::voltar( 'cand_muitos' );
		}

		$estab = CAV_Seguranca::texto( 'estabelecimento', 150 );
		$resp  = CAV_Seguranca::texto( 'responsavel', 120 );
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$ben   = CAV_Seguranca::texto( 'beneficio', 300 );
		if ( strlen( $email ) > 100 ) {
			$email = '';
		}

		if ( ! $estab || ! $resp || ! is_email( $email ) || ! $ben ) {
			self::voltar( 'cand_campos' );
		}
		if ( email_exists( $email ) ) {
			self::voltar( 'cand_email_usado' );
		}

		$post_id = wp_insert_post( [
			'post_type'   => 'cav_candidatura',
			'post_status' => 'publish',
			'post_title'  => $estab,
		] );

		if ( ! $post_id || is_wp_error( $post_id ) ) {
			self::voltar( 'cand_falha' );
		}

		update_post_meta( $post_id, self::CAND_EMAIL, $email );
		update_post_meta( $post_id, self::CAND_RESP, $resp );
		update_post_meta( $post_id, self::CAND_BEN, $ben );
		update_post_meta( $post_id, self::CAND_STATUS, self::STATUS_PENDENTE );

		foreach ( [ 'categoria' => [ self::CAND_CAT, 80 ], 'whatsapp' => [ self::CAND_ZAP, 20 ], 'endereco' => [ self::CAND_END, 200 ] ] as $campo => $cfg ) {
			$valor = 'whatsapp' === $campo ? CAV_Seguranca::telefone( isset( $_POST[ $campo ] ) ? wp_unslash( $_POST[ $campo ] ) : '' ) : CAV_Seguranca::texto( $campo, $cfg[1] );
			if ( '' !== $valor ) {
				update_post_meta( $post_id, $cfg[0], $valor );
			}
		}

		do_action( 'cav_candidatura_recebida', $post_id );
		self::voltar( 'candidatura_enviada' );
	}

	/**
	 * Aprova o estabelecimento: cria o post do parceiro, o usuário operador,
	 * um benefício em rascunho com a oferta proposta, e envia o e-mail de acesso.
	 */
	public static function aprovar_candidatura( $post_id, $url_terminal = '' ) {
		if ( self::STATUS_ATIVO === get_post_meta( $post_id, self::CAND_STATUS, true ) ) {
			return new WP_Error( 'cav_ja_aprovada', 'Esta candidatura já foi aprovada.' );
		}

		$estab = get_the_title( $post_id );
		$email = get_post_meta( $post_id, self::CAND_EMAIL, true );

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'cav_email', 'A candidatura não tem e-mail válido.' );
		}
		if ( email_exists( $email ) ) {
			return new WP_Error( 'cav_email_usado', 'Já existe uma conta com este e-mail.' );
		}

		// 1. Post do parceiro no diretório, como rascunho para revisão.
		$parceiro_id = wp_insert_post( [
			'post_type'    => cav_parceiro_post_type(),
			'post_status'  => 'draft',
			'post_title'   => $estab,
			'post_content' => '',
		] );

		if ( ! $parceiro_id || is_wp_error( $parceiro_id ) ) {
			return new WP_Error( 'cav_parceiro', 'Não foi possível criar o estabelecimento.' );
		}

		// 2. Usuário operador.
		$user_id = wp_insert_user( [
			'user_login'   => $email,
			'user_email'   => $email,
			'user_pass'    => wp_generate_password( 24 ),
			'display_name' => get_post_meta( $post_id, self::CAND_RESP, true ) ?: $estab,
			'role'         => 'cav_parceiro',
		] );

		if ( is_wp_error( $user_id ) ) {
			wp_delete_post( $parceiro_id, true );
			return $user_id;
		}

		update_user_meta( $user_id, CAV_Parceiro::META_USER_PARCEIRO, $parceiro_id );

		// 3. Benefício proposto, em rascunho — ninguém entra no clube sem oferta.
		$ben = get_post_meta( $post_id, self::CAND_BEN, true );

		if ( $ben ) {
			$ben_id = wp_insert_post( [
				'post_type'   => 'cav_beneficio',
				'post_status' => 'draft',
				'post_title'  => $ben,
			] );

			if ( $ben_id && ! is_wp_error( $ben_id ) ) {
				update_post_meta( $ben_id, CAV_Parceiro::META_BEN_PARCEIRO, $parceiro_id );
				update_post_meta( $ben_id, CAV_Parceiro::META_BEN_REGRA, $ben );
				update_post_meta( $ben_id, CAV_Parceiro::META_BEN_LIMITE, 'nenhum' );
				update_post_meta( $ben_id, CAV_Parceiro::META_BEN_ATIVO, '1' );
			}
		}

		update_post_meta( $post_id, self::CAND_STATUS, self::STATUS_ATIVO );
		update_post_meta( $post_id, '_cav_parceiro_criado', $parceiro_id );

		CAV_Emails::parceiro_aprovado( get_userdata( $user_id ), $estab, $url_terminal );
		do_action( 'cav_candidatura_aprovada', $post_id, $parceiro_id, $user_id );

		return $parceiro_id;
	}

	public static function recusar_candidatura( $post_id, $motivo = '' ) {
		$email = get_post_meta( $post_id, self::CAND_EMAIL, true );

		update_post_meta( $post_id, self::CAND_STATUS, self::STATUS_RECUSADO );

		if ( is_email( $email ) ) {
			CAV_Emails::parceiro_recusado( $email, get_the_title( $post_id ), $motivo );
		}

		do_action( 'cav_candidatura_recusada', $post_id );
		return true;
	}

	public static function pendentes_candidaturas() {
		return get_posts( [
			'post_type'      => 'cav_candidatura',
			'posts_per_page' => 100,
			'orderby'        => 'date',
			'order'          => 'ASC',
			'meta_query'     => [ [ 'key' => self::CAND_STATUS, 'value' => self::STATUS_PENDENTE ] ],
		] );
	}
}

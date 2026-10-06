<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CAV_Solicitacoes {

	const STATUS_PENDENTE = 'pendente';
	const STATUS_ATIVO    = 'ativo';
	const STATUS_RECUSADO = 'recusado';

	const META_WHATSAPP = 'cav_whatsapp';
	const META_TURMA    = 'cav_turma';
	const META_PEDIDO   = 'cav_pedido_em';

	// Candidatura de estabelecimento
	const CAND_EMAIL    = '_cav_cand_email';
	const CAND_RESP     = '_cav_cand_responsavel';
	const CAND_ZAP      = '_cav_cand_whatsapp';
	const CAND_END      = '_cav_cand_endereco';
	const CAND_CAT      = '_cav_cand_categoria';
	const CAND_BEN      = '_cav_cand_beneficio';
	const CAND_STATUS   = '_cav_cand_status';

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

	public static function status( $user_id ) {
		$s = get_user_meta( $user_id, 'cav_status', true );
		return $s ?: self::STATUS_PENDENTE;
	}

	public static function form_solicitar() {
		wp_enqueue_style( 'cav' );

		$msg = isset( $_GET['cav'] ) ? sanitize_text_field( wp_unslash( $_GET['cav'] ) ) : '';

		if ( 'enviado' === $msg ) {
			return '<div class="cav-card"><strong>Pedido enviado.</strong><br>A recepção vai conferir sua matrícula e liberar o acesso. '
				. 'Você recebe um e-mail assim que estiver pronto.</div>';
		}

		ob_start();
		?>
		<form class="cav-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="cav_solicitar">
			<?php wp_nonce_field( 'cav_solicitar' ); ?>

			<?php if ( $msg && 'enviado' !== $msg ) : ?>
				<p class="cav-erro-form"><?php echo esc_html( self::mensagem_erro( $msg ) ); ?></p>
			<?php endif; ?>

			<label for="cav-nome">Nome completo</label>
			<input id="cav-nome" name="nome" type="text" required>

			<label for="cav-email">E-mail</label>
			<input id="cav-email" name="email" type="email" required>

			<label for="cav-cpf">CPF</label>
			<input id="cav-cpf" name="cpf" type="text" inputmode="numeric" maxlength="14" required>

			<label for="cav-zap">WhatsApp</label>
			<input id="cav-zap" name="whatsapp" type="text">

			<label for="cav-turma">Turma que você treina</label>
			<input id="cav-turma" name="turma" type="text">

			<label class="cav-consent">
				<input type="checkbox" name="consentimento" value="1" required>
				<span>Autorizo que estabelecimentos parceiros consultem meu CPF para verificar se tenho direito ao benefício.</span>
			</label>

			<button type="submit" class="cav-btn">Enviar pedido</button>
		</form>
		<?php
		return ob_get_clean();
	}

	private static function mensagem_erro( $codigo ) {
		$mapa = [
			'cpf'         => 'CPF inválido. Confira os números.',
			'cpf_usado'   => 'Este CPF já tem um pedido ou cadastro no clube.',
			'email_usado' => 'Este e-mail já está cadastrado. Tente entrar com ele.',
			'campos'      => 'Preencha todos os campos obrigatórios.',
			'consent'     => 'É preciso autorizar a consulta do CPF para continuar.',
			'falha'       => 'Não foi possível registrar seu pedido. Tente de novo.',
		];
		return $mapa[ $codigo ] ?? 'Não foi possível concluir.';
	}

	private static function voltar( $codigo ) {
		$url = wp_get_referer() ?: home_url();
		wp_safe_redirect( add_query_arg( 'cav', $codigo, remove_query_arg( 'cav', $url ) ) );
		exit;
	}

	public static function receber_solicitacao() {
		check_admin_referer( 'cav_solicitar' );

		$nome  = isset( $_POST['nome'] ) ? sanitize_text_field( wp_unslash( $_POST['nome'] ) ) : '';
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$cpf   = isset( $_POST['cpf'] ) ? CAV_CPF::normalizar( wp_unslash( $_POST['cpf'] ) ) : '';

		if ( ! $nome || ! is_email( $email ) || ! $cpf ) {
			self::voltar( 'campos' );
		}
		if ( empty( $_POST['consentimento'] ) ) {
			self::voltar( 'consent' );
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

		update_user_meta( $user_id, CAV_Membro::META_CPF, $cpf );
		update_user_meta( $user_id, 'cav_status', self::STATUS_PENDENTE );
		update_user_meta( $user_id, CAV_Membro::META_CONSENTE, current_time( 'mysql' ) );
		update_user_meta( $user_id, self::META_PEDIDO, current_time( 'mysql' ) );

		if ( ! empty( $_POST['whatsapp'] ) ) {
			update_user_meta( $user_id, self::META_WHATSAPP, sanitize_text_field( wp_unslash( $_POST['whatsapp'] ) ) );
		}
		if ( ! empty( $_POST['turma'] ) ) {
			update_user_meta( $user_id, self::META_TURMA, sanitize_text_field( wp_unslash( $_POST['turma'] ) ) );
		}

		do_action( 'cav_solicitacao_recebida', $user_id );
		self::voltar( 'enviado' );
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

			<?php if ( $msg && 0 === strpos( $msg, 'cand_' ) ) : ?>
				<p class="cav-erro-form"><?php echo esc_html( self::mensagem_erro( str_replace( 'cand_', '', $msg ) ) ); ?></p>
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

		$estab = isset( $_POST['estabelecimento'] ) ? sanitize_text_field( wp_unslash( $_POST['estabelecimento'] ) ) : '';
		$resp  = isset( $_POST['responsavel'] ) ? sanitize_text_field( wp_unslash( $_POST['responsavel'] ) ) : '';
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$ben   = isset( $_POST['beneficio'] ) ? sanitize_text_field( wp_unslash( $_POST['beneficio'] ) ) : '';

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

		foreach ( [ 'categoria' => self::CAND_CAT, 'whatsapp' => self::CAND_ZAP, 'endereco' => self::CAND_END ] as $campo => $meta ) {
			if ( ! empty( $_POST[ $campo ] ) ) {
				update_post_meta( $post_id, $meta, sanitize_text_field( wp_unslash( $_POST[ $campo ] ) ) );
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

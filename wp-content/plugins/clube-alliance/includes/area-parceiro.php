<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Área do parceiro: o estabelecimento mantém, pelo próprio site, os dados do negócio
 * (foto, logo, contato, horário) e as promoções que aparecem no terminal e na vitrine.
 * Nada disso exige entrar no painel do WordPress.
 */
class CAV_AreaParceiro {

	// Dados do negócio, guardados no post do parceiro.
	const META_LOGO      = '_cav_p_logo';       // ID do anexo
	const META_TELEFONE  = '_cav_p_telefone';
	const META_ENDERECO  = '_cav_p_endereco';
	const META_HORARIO   = '_cav_p_horario';
	const META_INSTAGRAM = '_cav_p_instagram';  // só o @usuario
	const META_SITE      = '_cav_p_site';

	const MAX_FOTO = 4194304; // 4 MB

	/** Páginas da área, na ordem do menu: slug => rótulo. */
	const PAGINAS = [
		'terminal'           => 'Terminal',
		'painel-do-parceiro' => 'Painel',
		'minhas-promocoes'   => 'Promoções',
		'meu-negocio'        => 'Meu negócio',
	];

	public static function init() {
		add_shortcode( 'cav_menu_parceiro', [ __CLASS__, 'menu' ] );
		add_shortcode( 'cav_meu_negocio', [ __CLASS__, 'pagina_negocio' ] );
		add_shortcode( 'cav_minhas_promocoes', [ __CLASS__, 'pagina_promocoes' ] );

		add_action( 'admin_post_cav_salvar_negocio', [ __CLASS__, 'salvar_negocio' ] );
		add_action( 'admin_post_cav_salvar_promocao', [ __CLASS__, 'salvar_promocao' ] );
		add_action( 'admin_post_cav_acao_promocao', [ __CLASS__, 'acao_promocao' ] );

		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'assets' ] );

		add_action( 'add_meta_boxes', [ __CLASS__, 'metabox' ] );
		add_action( 'save_post', [ __CLASS__, 'salvar_metabox' ] );
	}

	public static function assets() {
		wp_register_style( 'cav-parceiro', CAV_URL . 'assets/cav-parceiro.css', [ 'cav-membro' ], CAV_VERSION );
	}

	private static function estilos() {
		wp_enqueue_style( 'cav' );
		wp_enqueue_style( 'cav-membro' );
		wp_enqueue_style( 'cav-parceiro' );
	}

	/* ------------------------------ Utilidades ------------------------------ */

	/** Endereço de uma página da área pelo slug, ou '' se ela não existe. */
	public static function url( $slug ) {
		$p = get_page_by_path( $slug );
		return $p ? get_permalink( $p ) : '';
	}

	/** Parceiro que o usuário logado opera, ou 0. */
	private static function parceiro_atual() {
		if ( ! is_user_logged_in() ) {
			return 0;
		}
		$id = CAV_Parceiro::parceiro_do_usuario( get_current_user_id() );
		return ( $id && get_post( $id ) && cav_parceiro_post_type() === get_post_type( $id ) ) ? $id : 0;
	}

	private static function aviso( $texto ) {
		self::estilos();
		return '<div class="cav-membro-raiz"><div class="cav-card cav-aviso">' . esc_html( $texto ) . '</div></div>';
	}

	/** Bloqueia quem não é parceiro; devolve o aviso, ou '' se pode seguir. */
	private static function barreira() {
		if ( ! is_user_logged_in() ) {
			return self::aviso( 'Faça login com a conta do estabelecimento.' );
		}
		if ( ! current_user_can( 'cav_editar_negocio' ) ) {
			return self::aviso( 'Esta conta não tem acesso à área do parceiro.' );
		}
		if ( ! self::parceiro_atual() ) {
			return self::aviso( 'Sua conta ainda não está vinculada a um estabelecimento. Fale com a Alliance.' );
		}
		return '';
	}

	private static function voltar( $slug, $codigo, array $extra = [] ) {
		$url = self::url( $slug ) ?: home_url( '/' );
		wp_safe_redirect( add_query_arg( array_merge( [ 'cav_msg' => $codigo ], $extra ), $url ) );
		exit;
	}

	private static function mensagens() {
		return [
			'negocio_ok'      => [ 'ok', 'Dados do negócio salvos. Já aparecem na sua página no clube.' ],
			'promocao_ok'     => [ 'ok', 'Promoção salva.' ],
			'promocao_pausada' => [ 'ok', 'Promoção pausada. Ela saiu do terminal e da vitrine.' ],
			'promocao_ativada' => [ 'ok', 'Promoção no ar de novo.' ],
			'promocao_apagada' => [ 'ok', 'Promoção apagada. O histórico de usos continua guardado.' ],
			'campos'          => [ 'erro', 'Preencha o nome da promoção e a regra que o caixa vai ver.' ],
			'datas'           => [ 'erro', 'A data final não pode ser antes da data de início.' ],
			'foto_tipo'       => [ 'erro', 'A imagem precisa ser JPG, PNG ou WebP.' ],
			'foto_tamanho'    => [ 'erro', 'A imagem passa de 4 MB. Reduza o tamanho e envie de novo.' ],
			'foto_falha'      => [ 'erro', 'Não consegui enviar a imagem. Tente de novo; se repetir, fale com a Alliance.' ],
			'falha'           => [ 'erro', 'Não consegui salvar. Tente de novo; se repetir, fale com a Alliance.' ],
			'proibido'        => [ 'erro', 'Você não tem permissão para isso.' ],
		];
	}

	private static function recado() {
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

	/* ------------------------- Dados do negócio (leitura) ------------------------- */

	/** Telefone digitado vira link de WhatsApp (assume Brasil se vier sem o 55). */
	public static function link_whatsapp( $telefone ) {
		$d = preg_replace( '/\D+/', '', (string) $telefone );
		if ( strlen( $d ) < 10 ) {
			return '';
		}
		if ( strlen( $d ) <= 11 ) {
			$d = '55' . $d;
		}
		return 'https://wa.me/' . $d;
	}

	/** "@usuario", "usuario" ou o link inteiro viram só "usuario". */
	public static function limpar_instagram( $texto ) {
		$t = trim( (string) $texto );
		$t = preg_replace( '#^https?://(www\.)?instagram\.com/#i', '', $t );
		$t = ltrim( $t, '@' );
		$t = strtok( $t, '/?# ' );
		return preg_match( '/^[A-Za-z0-9._]{1,30}$/', (string) $t ) ? $t : '';
	}

	public static function limpar_site( $texto ) {
		$t = trim( (string) $texto );
		if ( '' === $t ) {
			return '';
		}
		if ( ! preg_match( '#^https?://#i', $t ) ) {
			$t = 'https://' . $t;
		}
		return esc_url_raw( $t );
	}

	/** Tudo que o parceiro preencheu, pronto para o tema mostrar. */
	public static function dados( $parceiro_id ) {
		$insta = get_post_meta( $parceiro_id, self::META_INSTAGRAM, true );
		$logo  = (int) get_post_meta( $parceiro_id, self::META_LOGO, true );

		return [
			'telefone'  => (string) get_post_meta( $parceiro_id, self::META_TELEFONE, true ),
			'whatsapp'  => self::link_whatsapp( get_post_meta( $parceiro_id, self::META_TELEFONE, true ) ),
			'endereco'  => (string) get_post_meta( $parceiro_id, self::META_ENDERECO, true ),
			'horario'   => (string) get_post_meta( $parceiro_id, self::META_HORARIO, true ),
			'instagram' => $insta,
			'insta_url' => $insta ? 'https://instagram.com/' . rawurlencode( $insta ) : '',
			'site'      => (string) get_post_meta( $parceiro_id, self::META_SITE, true ),
			'logo_id'   => ( $logo && wp_attachment_is_image( $logo ) ) ? $logo : 0,
		];
	}

	/* --------------------------------- Menu --------------------------------- */

	public static function menu() {
		$parceiro = self::parceiro_atual();
		if ( ! $parceiro || ! current_user_can( 'cav_consultar' ) ) {
			return '';
		}

		self::estilos();
		$atual = (int) get_queried_object_id();

		ob_start();
		echo '<div class="cav-membro-raiz"><nav class="cav-menu-membro" aria-label="Área do parceiro"><ul>';
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
		printf( '<li><a href="%s">Minha página no clube &rarr;</a></li>', esc_url( get_permalink( $parceiro ) ) );
		echo '</ul></nav></div>';
		return ob_get_clean();
	}

	/* ------------------------------ Meu negócio ------------------------------ */

	public static function pagina_negocio() {
		$barreira = self::barreira();
		if ( $barreira ) {
			return $barreira;
		}

		self::estilos();

		$id    = self::parceiro_atual();
		$post  = get_post( $id );
		$d     = self::dados( $id );
		$cats  = get_the_terms( $id, 'cav_categoria' );
		$cat   = ( $cats && ! is_wp_error( $cats ) ) ? $cats[0]->name : '';
		$capa  = has_post_thumbnail( $id ) ? get_the_post_thumbnail( $id, 'medium', [ 'loading' => 'lazy' ] ) : '';
		$logo  = $d['logo_id'] ? wp_get_attachment_image( $d['logo_id'], 'thumbnail', false, [ 'loading' => 'lazy' ] ) : '';

		ob_start();
		?>
		<div class="cav-membro-raiz cav-p-pagina">
			<?php echo self::recado(); // phpcs:ignore WordPress.Security.EscapeOutput ?>

			<header class="cav-p-cabeca">
				<h2>Meu negócio</h2>
				<p>Isto aparece na sua página no clube, que os alunos veem antes de ir até você.
					<a href="<?php echo esc_url( get_permalink( $id ) ); ?>">Ver como está &rarr;</a></p>
			</header>

			<form class="cav-p-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="cav_salvar_negocio">
				<?php wp_nonce_field( 'cav_salvar_negocio', 'cav_nonce' ); ?>

				<fieldset class="cav-p-grupo">
					<legend>Imagens</legend>

					<div class="cav-p-imagens">
						<div class="cav-p-imagem">
							<span class="cav-p-rotulo">Foto do negócio</span>
							<div class="cav-p-previa cav-p-previa--capa"><?php echo $capa ?: '<span>Sem foto ainda</span>'; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
							<input type="file" name="cav_foto" id="cav_foto" accept="image/jpeg,image/png,image/webp">
							<small>Fachada, balcão ou produto. De preferência na horizontal. JPG, PNG ou WebP, até 4 MB.</small>
						</div>
						<div class="cav-p-imagem">
							<span class="cav-p-rotulo">Logo</span>
							<div class="cav-p-previa cav-p-previa--logo"><?php echo $logo ?: '<span>Sem logo ainda</span>'; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
							<input type="file" name="cav_logo" id="cav_logo" accept="image/jpeg,image/png,image/webp">
							<small>Quadrada funciona melhor. Só envie se quiser trocar.</small>
						</div>
					</div>
				</fieldset>

				<fieldset class="cav-p-grupo">
					<legend>Sobre</legend>

					<p class="cav-p-fixo"><span class="cav-p-rotulo">Nome</span> <?php echo esc_html( $post->post_title ); ?><?php echo $cat ? ' · ' . esc_html( $cat ) : ''; ?>
						<small>Para mudar o nome ou a categoria, fale com a Alliance.</small></p>

					<label class="cav-p-campo">
						<span class="cav-p-rotulo">Resumo (uma frase)</span>
						<input type="text" name="cav_resumo" maxlength="160" value="<?php echo esc_attr( $post->post_excerpt ); ?>" placeholder="Ex.: Barbearia clássica no Centro, atendimento com hora marcada.">
					</label>

					<label class="cav-p-campo">
						<span class="cav-p-rotulo">Descrição</span>
						<textarea name="cav_descricao" rows="6" maxlength="2000" placeholder="Conte o que vocês fazem e o que o aluno encontra aí."><?php echo esc_textarea( wp_strip_all_tags( $post->post_content ) ); ?></textarea>
					</label>
				</fieldset>

				<fieldset class="cav-p-grupo">
					<legend>Como encontrar vocês</legend>

					<div class="cav-p-duas">
						<label class="cav-p-campo">
							<span class="cav-p-rotulo">WhatsApp ou telefone</span>
							<input type="tel" name="cav_telefone" maxlength="30" value="<?php echo esc_attr( $d['telefone'] ); ?>" placeholder="(11) 99999-0000">
						</label>
						<label class="cav-p-campo">
							<span class="cav-p-rotulo">Instagram</span>
							<input type="text" name="cav_instagram" maxlength="60" value="<?php echo esc_attr( $d['instagram'] ? '@' . $d['instagram'] : '' ); ?>" placeholder="@seunegocio">
						</label>
					</div>

					<label class="cav-p-campo">
						<span class="cav-p-rotulo">Endereço</span>
						<input type="text" name="cav_endereco" maxlength="200" value="<?php echo esc_attr( $d['endereco'] ); ?>" placeholder="Rua, número, bairro, Mogi das Cruzes">
					</label>

					<label class="cav-p-campo">
						<span class="cav-p-rotulo">Horário de funcionamento</span>
						<textarea name="cav_horario" rows="3" maxlength="300" placeholder="Seg a sex, 9h às 19h&#10;Sábado, 9h às 15h"><?php echo esc_textarea( $d['horario'] ); ?></textarea>
					</label>

					<label class="cav-p-campo">
						<span class="cav-p-rotulo">Site</span>
						<input type="text" name="cav_site" maxlength="200" value="<?php echo esc_attr( $d['site'] ); ?>" placeholder="www.seunegocio.com.br">
					</label>
				</fieldset>

				<p class="cav-p-acoes"><button type="submit" class="cav-p-botao">Salvar dados do negócio</button></p>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/** Envia uma imagem do formulário. 0 = nada enviado, int = ID do anexo, WP_Error = problema. */
	private static function enviar_imagem( $campo, $parceiro_id ) {
		if ( empty( $_FILES[ $campo ]['name'] ) ) {
			return 0;
		}

		$arq = $_FILES[ $campo ]; // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput

		if ( UPLOAD_ERR_INI_SIZE === (int) $arq['error'] || UPLOAD_ERR_FORM_SIZE === (int) $arq['error'] || (int) $arq['size'] > self::MAX_FOTO ) {
			return new WP_Error( 'foto_tamanho' );
		}
		if ( UPLOAD_ERR_OK !== (int) $arq['error'] ) {
			return new WP_Error( 'foto_falha' );
		}

		$mimes = [
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'webp'         => 'image/webp',
		];
		$tipo = wp_check_filetype_and_ext( $arq['tmp_name'], $arq['name'], $mimes );

		if ( empty( $tipo['type'] ) ) {
			return new WP_Error( 'foto_tipo' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$anexo = media_handle_upload( $campo, $parceiro_id, [], [ 'test_form' => false, 'mimes' => $mimes ] );

		return is_wp_error( $anexo ) ? new WP_Error( 'foto_falha' ) : (int) $anexo;
	}

	/** Apaga a imagem antiga só se foi o próprio parceiro que a enviou. */
	private static function descartar_anexo( $anexo_id ) {
		if ( $anexo_id && (int) get_post_field( 'post_author', $anexo_id ) === get_current_user_id() ) {
			wp_delete_attachment( $anexo_id, true );
		}
	}

	public static function salvar_negocio() {
		check_admin_referer( 'cav_salvar_negocio', 'cav_nonce' );

		$id = self::parceiro_atual();
		if ( ! $id || ! current_user_can( 'cav_editar_negocio' ) ) {
			self::voltar( 'meu-negocio', 'proibido' );
		}

		$foto = self::enviar_imagem( 'cav_foto', $id );
		$logo = self::enviar_imagem( 'cav_logo', $id );

		foreach ( [ $foto, $logo ] as $r ) {
			if ( is_wp_error( $r ) ) {
				// Se a outra imagem já subiu, não deixa órfã.
				foreach ( [ $foto, $logo ] as $ok ) {
					if ( is_int( $ok ) && $ok ) {
						wp_delete_attachment( $ok, true );
					}
				}
				self::voltar( 'meu-negocio', $r->get_error_code() );
			}
		}

		if ( $foto ) {
			$antiga = (int) get_post_thumbnail_id( $id );
			set_post_thumbnail( $id, $foto );
			if ( $antiga !== $foto ) {
				self::descartar_anexo( $antiga );
			}
		}
		if ( $logo ) {
			$antiga = (int) get_post_meta( $id, self::META_LOGO, true );
			update_post_meta( $id, self::META_LOGO, $logo );
			if ( $antiga !== $logo ) {
				self::descartar_anexo( $antiga );
			}
		}

		wp_update_post( [
			'ID'           => $id,
			'post_excerpt' => isset( $_POST['cav_resumo'] ) ? sanitize_text_field( wp_unslash( $_POST['cav_resumo'] ) ) : '',
			'post_content' => isset( $_POST['cav_descricao'] ) ? sanitize_textarea_field( wp_unslash( $_POST['cav_descricao'] ) ) : '',
		] );

		$telefone = isset( $_POST['cav_telefone'] ) ? sanitize_text_field( wp_unslash( $_POST['cav_telefone'] ) ) : '';
		update_post_meta( $id, self::META_TELEFONE, $telefone );
		update_post_meta( $id, self::META_ENDERECO, isset( $_POST['cav_endereco'] ) ? sanitize_text_field( wp_unslash( $_POST['cav_endereco'] ) ) : '' );
		update_post_meta( $id, self::META_HORARIO, isset( $_POST['cav_horario'] ) ? sanitize_textarea_field( wp_unslash( $_POST['cav_horario'] ) ) : '' );
		update_post_meta( $id, self::META_INSTAGRAM, self::limpar_instagram( isset( $_POST['cav_instagram'] ) ? wp_unslash( $_POST['cav_instagram'] ) : '' ) );
		update_post_meta( $id, self::META_SITE, self::limpar_site( isset( $_POST['cav_site'] ) ? wp_unslash( $_POST['cav_site'] ) : '' ) );

		do_action( 'cav_negocio_atualizado', $id, get_current_user_id() );
		self::voltar( 'meu-negocio', 'negocio_ok' );
	}

	/* -------------------------------- Promoções -------------------------------- */

	/** A promoção é do parceiro do usuário logado? */
	private static function e_minha( $beneficio_id ) {
		$parceiro = self::parceiro_atual();
		$post     = get_post( $beneficio_id );

		return $parceiro && $post && 'cav_beneficio' === $post->post_type
			&& (int) get_post_meta( $beneficio_id, CAV_Parceiro::META_BEN_PARCEIRO, true ) === $parceiro;
	}

	/** Situação para o parceiro: [classe, texto]. */
	public static function situacao( $beneficio_id ) {
		$post = get_post( $beneficio_id );

		if ( $post && 'publish' !== $post->post_status ) {
			return [ 'analise', 'Em análise pela Alliance' ];
		}
		if ( '1' !== (string) get_post_meta( $beneficio_id, CAV_Parceiro::META_BEN_ATIVO, true ) ) {
			return [ 'pausada', 'Pausada' ];
		}

		$hoje   = current_time( 'Y-m-d' );
		$inicio = get_post_meta( $beneficio_id, CAV_Parceiro::META_BEN_INICIO, true );
		$fim    = get_post_meta( $beneficio_id, CAV_Parceiro::META_BEN_FIM, true );

		if ( $fim && $hoje > $fim ) {
			return [ 'encerrada', 'Encerrada' ];
		}
		if ( $inicio && $hoje < $inicio ) {
			return [ 'agendada', 'Começa em ' . mysql2date( 'd/m', $inicio . ' 00:00:00' ) ];
		}
		return [ 'no-ar', 'No ar' ];
	}

	private static function promocoes_do_parceiro( $parceiro_id ) {
		return get_posts( [
			'post_type'      => 'cav_beneficio',
			'post_status'    => [ 'publish', 'draft', 'pending' ],
			'posts_per_page' => 100,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => [ [ 'key' => CAV_Parceiro::META_BEN_PARCEIRO, 'value' => (int) $parceiro_id ] ],
		] );
	}

	public static function pagina_promocoes() {
		$barreira = self::barreira();
		if ( $barreira ) {
			return $barreira;
		}

		self::estilos();

		$parceiro = self::parceiro_atual();
		$base     = self::url( 'minhas-promocoes' ) ?: get_permalink();

		$editar_id = isset( $_GET['editar'] ) ? absint( $_GET['editar'] ) : 0;
		$nova      = ! empty( $_GET['nova'] );

		if ( $editar_id && ! self::e_minha( $editar_id ) ) {
			$editar_id = 0;
		}

		ob_start();
		echo '<div class="cav-membro-raiz cav-p-pagina">';
		echo self::recado(); // phpcs:ignore WordPress.Security.EscapeOutput

		if ( $editar_id || $nova ) {
			echo self::formulario_promocao( $editar_id, $base ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '</div>';
			return ob_get_clean();
		}

		$lista = self::promocoes_do_parceiro( $parceiro );
		?>
		<header class="cav-p-cabeca cav-p-cabeca--linha">
			<div>
				<h2>Minhas promoções</h2>
				<p>O que você oferece aos alunos. Quem está no ar aparece no terminal e na sua página.</p>
			</div>
			<a class="cav-p-botao" href="<?php echo esc_url( add_query_arg( 'nova', 1, $base ) ); ?>">+ Nova promoção</a>
		</header>

		<?php if ( ! $lista ) : ?>
			<p class="cav-vazio">Você ainda não cadastrou nenhuma promoção. Comece pela primeira: é o que o aluno vê ao procurar seu negócio.</p>
		<?php else : ?>
			<ul class="cav-p-lista">
			<?php foreach ( $lista as $b ) :
				list( $classe, $texto ) = self::situacao( $b->ID );
				$regra  = get_post_meta( $b->ID, CAV_Parceiro::META_BEN_REGRA, true );
				$limite = get_post_meta( $b->ID, CAV_Parceiro::META_BEN_LIMITE, true ) ?: 'nenhum';
				$publicada = 'publish' === $b->post_status;
				$ativa     = '1' === (string) get_post_meta( $b->ID, CAV_Parceiro::META_BEN_ATIVO, true );
				?>
				<li class="cav-p-item cav-p-item--<?php echo esc_attr( $classe ); ?>">
					<div class="cav-p-item__corpo">
						<span class="cav-p-situacao cav-p-situacao--<?php echo esc_attr( $classe ); ?>"><?php echo esc_html( $texto ); ?></span>
						<h3><?php echo esc_html( get_the_title( $b ) ); ?></h3>
						<?php if ( $regra ) : ?><p class="cav-p-regra"><?php echo esc_html( $regra ); ?></p><?php endif; ?>
						<p class="cav-p-meta">
							<?php echo esc_html( CAV_Parceiro::rotulo_vigencia( $b->ID ) ); ?>
							· <?php echo esc_html( self::rotulo_limite( $limite ) ); ?>
						</p>
					</div>
					<div class="cav-p-item__acoes">
						<a class="cav-link-botao" href="<?php echo esc_url( add_query_arg( 'editar', $b->ID, $base ) ); ?>">Editar</a>
						<?php if ( $publicada ) : ?>
							<?php echo self::botao_acao( $b->ID, $ativa ? 'pausar' : 'ativar', $ativa ? 'Pausar' : 'Reativar' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<?php endif; ?>
						<?php echo self::botao_acao( $b->ID, 'apagar', 'Apagar', 'Apagar esta promoção? Os usos já registrados continuam no histórico.' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</div>
				</li>
			<?php endforeach; ?>
			</ul>
		<?php endif;

		echo '</div>';
		return ob_get_clean();
	}

	private static function rotulo_limite( $limite ) {
		$m = [
			'nenhum' => 'sem limite de uso',
			'dia'    => '1 uso por dia',
			'semana' => '1 uso por semana',
			'mes'    => '1 uso por mês',
		];
		return $m[ $limite ] ?? $m['nenhum'];
	}

	private static function botao_acao( $id, $acao, $rotulo, $confirmar = '' ) {
		ob_start();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cav-p-mini"
			<?php if ( $confirmar ) : ?>onsubmit="return confirm(<?php echo esc_attr( wp_json_encode( $confirmar ) ); ?>);"<?php endif; ?>>
			<input type="hidden" name="action" value="cav_acao_promocao">
			<input type="hidden" name="promocao" value="<?php echo esc_attr( $id ); ?>">
			<input type="hidden" name="acao" value="<?php echo esc_attr( $acao ); ?>">
			<?php wp_nonce_field( 'cav_acao_promocao_' . $id, 'cav_nonce' ); ?>
			<button type="submit" class="cav-link-botao<?php echo 'apagar' === $acao ? ' cav-link-botao--perigo' : ''; ?>"><?php echo esc_html( $rotulo ); ?></button>
		</form>
		<?php
		return ob_get_clean();
	}

	private static function formulario_promocao( $id, $base ) {
		$p      = $id ? get_post( $id ) : null;
		$regra  = $id ? get_post_meta( $id, CAV_Parceiro::META_BEN_REGRA, true ) : '';
		$limite = $id ? ( get_post_meta( $id, CAV_Parceiro::META_BEN_LIMITE, true ) ?: 'nenhum' ) : 'nenhum';
		$inicio = $id ? get_post_meta( $id, CAV_Parceiro::META_BEN_INICIO, true ) : '';
		$fim    = $id ? get_post_meta( $id, CAV_Parceiro::META_BEN_FIM, true ) : '';
		$ativo  = $id ? ( '1' === (string) get_post_meta( $id, CAV_Parceiro::META_BEN_ATIVO, true ) ) : true;
		$rascunho = $p && 'publish' !== $p->post_status;

		ob_start();
		?>
		<header class="cav-p-cabeca">
			<p class="cav-p-voltar"><a href="<?php echo esc_url( $base ); ?>">&larr; Minhas promoções</a></p>
			<h2><?php echo $id ? 'Editar promoção' : 'Nova promoção'; ?></h2>
		</header>

		<form class="cav-p-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="cav_salvar_promocao">
			<input type="hidden" name="promocao" value="<?php echo esc_attr( $id ); ?>">
			<?php wp_nonce_field( 'cav_salvar_promocao', 'cav_nonce' ); ?>

			<fieldset class="cav-p-grupo">
				<legend>O que você oferece</legend>

				<label class="cav-p-campo">
					<span class="cav-p-rotulo">Nome da promoção</span>
					<input type="text" name="cav_titulo" required maxlength="80" value="<?php echo esc_attr( $p ? $p->post_title : '' ); ?>" placeholder="Ex.: Corte de cabelo, 30% off">
					<small>É o título que o aluno vê na sua página e que aparece no terminal.</small>
				</label>

				<label class="cav-p-campo">
					<span class="cav-p-rotulo">Regra para o caixa</span>
					<input type="text" name="cav_regra" required maxlength="140" value="<?php echo esc_attr( $regra ); ?>" placeholder="Ex.: Segunda a quinta, uma vez por semana">
					<small>O que o seu atendente lê no terminal antes de dar o desconto: dias, limites, o que está incluído.</small>
				</label>

				<label class="cav-p-campo">
					<span class="cav-p-rotulo">Detalhes (opcional)</span>
					<textarea name="cav_detalhes" rows="4" maxlength="600"><?php echo esc_textarea( $p ? wp_strip_all_tags( $p->post_content ) : '' ); ?></textarea>
				</label>
			</fieldset>

			<fieldset class="cav-p-grupo">
				<legend>Quando vale</legend>

				<label class="cav-p-campo">
					<span class="cav-p-rotulo">Quantas vezes cada aluno pode usar</span>
					<select name="cav_limite">
						<?php foreach ( [ 'nenhum', 'dia', 'semana', 'mes' ] as $l ) : ?>
							<option value="<?php echo esc_attr( $l ); ?>" <?php selected( $limite, $l ); ?>><?php echo esc_html( ucfirst( self::rotulo_limite( $l ) ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>

				<div class="cav-p-duas">
					<label class="cav-p-campo">
						<span class="cav-p-rotulo">Começa em</span>
						<input type="date" name="cav_inicio" value="<?php echo esc_attr( $inicio ); ?>">
					</label>
					<label class="cav-p-campo">
						<span class="cav-p-rotulo">Termina em</span>
						<input type="date" name="cav_fim" value="<?php echo esc_attr( $fim ); ?>">
					</label>
				</div>
				<small class="cav-p-dica">Deixe as duas datas em branco para uma promoção permanente. Com data final, ela some sozinha depois do último dia.</small>

				<?php if ( ! $rascunho ) : ?>
					<label class="cav-p-check">
						<input type="checkbox" name="cav_ativo" value="1" <?php checked( $ativo ); ?>>
						<span>Promoção no ar</span>
					</label>
				<?php else : ?>
					<p class="cav-p-dica">Esta promoção está em análise pela Alliance e vai ao ar quando for liberada.</p>
				<?php endif; ?>
			</fieldset>

			<p class="cav-p-acoes">
				<button type="submit" class="cav-p-botao">Salvar promoção</button>
				<a class="cav-link-botao" href="<?php echo esc_url( $base ); ?>">Cancelar</a>
			</p>
		</form>
		<?php
		return ob_get_clean();
	}

	public static function salvar_promocao() {
		check_admin_referer( 'cav_salvar_promocao', 'cav_nonce' );

		$parceiro = self::parceiro_atual();
		if ( ! $parceiro || ! current_user_can( 'cav_editar_negocio' ) ) {
			self::voltar( 'minhas-promocoes', 'proibido' );
		}

		$id = isset( $_POST['promocao'] ) ? absint( $_POST['promocao'] ) : 0;
		if ( $id && ! self::e_minha( $id ) ) {
			self::voltar( 'minhas-promocoes', 'proibido' );
		}

		$titulo  = isset( $_POST['cav_titulo'] ) ? sanitize_text_field( wp_unslash( $_POST['cav_titulo'] ) ) : '';
		$regra   = isset( $_POST['cav_regra'] ) ? sanitize_text_field( wp_unslash( $_POST['cav_regra'] ) ) : '';
		$detalhe = isset( $_POST['cav_detalhes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['cav_detalhes'] ) ) : '';

		$data = static function ( $campo ) {
			$v = isset( $_POST[ $campo ] ) ? sanitize_text_field( wp_unslash( $_POST[ $campo ] ) ) : '';
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : '';
		};
		$inicio = $data( 'cav_inicio' );
		$fim    = $data( 'cav_fim' );

		$volta = $id ? [ 'editar' => $id ] : [ 'nova' => 1 ];

		if ( '' === $titulo || '' === $regra ) {
			self::voltar( 'minhas-promocoes', 'campos', $volta );
		}
		if ( $inicio && $fim && $fim < $inicio ) {
			self::voltar( 'minhas-promocoes', 'datas', $volta );
		}

		$limite = isset( $_POST['cav_limite'] ) ? sanitize_key( wp_unslash( $_POST['cav_limite'] ) ) : 'nenhum';
		if ( ! in_array( $limite, [ 'nenhum', 'dia', 'semana', 'mes' ], true ) ) {
			$limite = 'nenhum';
		}

		if ( $id ) {
			wp_update_post( [ 'ID' => $id, 'post_title' => $titulo, 'post_content' => $detalhe ] );
		} else {
			$id = wp_insert_post( [
				'post_type'    => 'cav_beneficio',
				'post_status'  => 'publish',
				'post_title'   => $titulo,
				'post_content' => $detalhe,
				'post_author'  => get_current_user_id(),
			] );

			if ( ! $id || is_wp_error( $id ) ) {
				self::voltar( 'minhas-promocoes', 'falha' );
			}
			update_post_meta( $id, CAV_Parceiro::META_BEN_PARCEIRO, $parceiro );
		}

		update_post_meta( $id, CAV_Parceiro::META_BEN_REGRA, $regra );
		update_post_meta( $id, CAV_Parceiro::META_BEN_LIMITE, $limite );
		update_post_meta( $id, CAV_Parceiro::META_BEN_INICIO, $inicio );
		update_post_meta( $id, CAV_Parceiro::META_BEN_FIM, $fim );

		// O campo "no ar" só existe no formulário de promoção já publicada; em análise, a Alliance decide.
		if ( 'publish' === get_post_status( $id ) ) {
			update_post_meta( $id, CAV_Parceiro::META_BEN_ATIVO, isset( $_POST['cav_ativo'] ) ? '1' : '' );
		}

		do_action( 'cav_promocao_salva_pelo_parceiro', $id, $parceiro );
		self::voltar( 'minhas-promocoes', 'promocao_ok' );
	}

	public static function acao_promocao() {
		$id = isset( $_POST['promocao'] ) ? absint( $_POST['promocao'] ) : 0;
		check_admin_referer( 'cav_acao_promocao_' . $id, 'cav_nonce' );

		if ( ! $id || ! current_user_can( 'cav_editar_negocio' ) || ! self::e_minha( $id ) ) {
			self::voltar( 'minhas-promocoes', 'proibido' );
		}

		$acao = isset( $_POST['acao'] ) ? sanitize_key( wp_unslash( $_POST['acao'] ) ) : '';

		if ( 'pausar' === $acao ) {
			update_post_meta( $id, CAV_Parceiro::META_BEN_ATIVO, '' );
			self::voltar( 'minhas-promocoes', 'promocao_pausada' );
		}
		if ( 'ativar' === $acao && 'publish' === get_post_status( $id ) ) {
			update_post_meta( $id, CAV_Parceiro::META_BEN_ATIVO, '1' );
			self::voltar( 'minhas-promocoes', 'promocao_ativada' );
		}
		if ( 'apagar' === $acao ) {
			// Vai para a lixeira: o histórico de usos aponta para esta promoção e continua legível.
			wp_trash_post( $id );
			self::voltar( 'minhas-promocoes', 'promocao_apagada' );
		}

		self::voltar( 'minhas-promocoes', 'proibido' );
	}

	/* ------------------- Campos do negócio no painel do WordPress ------------------- */

	public static function metabox() {
		add_meta_box( 'cav_negocio_dados', 'Dados do negócio (o parceiro também edita pelo site)', [ __CLASS__, 'render_metabox' ], cav_parceiro_post_type(), 'normal', 'default' );
	}

	public static function render_metabox( $post ) {
		$d = self::dados( $post->ID );
		wp_nonce_field( 'cav_salvar_negocio_admin', 'cav_negocio_admin_nonce' );
		?>
		<p><label><strong>WhatsApp ou telefone</strong><br><input type="text" name="cav_a_telefone" value="<?php echo esc_attr( $d['telefone'] ); ?>" style="width:100%;max-width:320px"></label></p>
		<p><label><strong>Endereço</strong><br><input type="text" name="cav_a_endereco" value="<?php echo esc_attr( $d['endereco'] ); ?>" style="width:100%;max-width:600px"></label></p>
		<p><label><strong>Horário</strong><br><textarea name="cav_a_horario" rows="3" style="width:100%;max-width:600px"><?php echo esc_textarea( $d['horario'] ); ?></textarea></label></p>
		<p><label><strong>Instagram</strong><br><input type="text" name="cav_a_instagram" value="<?php echo esc_attr( $d['instagram'] ? '@' . $d['instagram'] : '' ); ?>" style="width:100%;max-width:320px"></label></p>
		<p><label><strong>Site</strong><br><input type="text" name="cav_a_site" value="<?php echo esc_attr( $d['site'] ); ?>" style="width:100%;max-width:420px"></label></p>
		<p class="description">A foto do negócio é a Imagem destacada. O logo é enviado pelo parceiro na página "Meu negócio".</p>
		<?php
	}

	public static function salvar_metabox( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['cav_negocio_admin_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cav_negocio_admin_nonce'] ) ), 'cav_salvar_negocio_admin' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		update_post_meta( $post_id, self::META_TELEFONE, isset( $_POST['cav_a_telefone'] ) ? sanitize_text_field( wp_unslash( $_POST['cav_a_telefone'] ) ) : '' );
		update_post_meta( $post_id, self::META_ENDERECO, isset( $_POST['cav_a_endereco'] ) ? sanitize_text_field( wp_unslash( $_POST['cav_a_endereco'] ) ) : '' );
		update_post_meta( $post_id, self::META_HORARIO, isset( $_POST['cav_a_horario'] ) ? sanitize_textarea_field( wp_unslash( $_POST['cav_a_horario'] ) ) : '' );
		update_post_meta( $post_id, self::META_INSTAGRAM, self::limpar_instagram( isset( $_POST['cav_a_instagram'] ) ? wp_unslash( $_POST['cav_a_instagram'] ) : '' ) );
		update_post_meta( $post_id, self::META_SITE, self::limpar_site( isset( $_POST['cav_a_site'] ) ? wp_unslash( $_POST['cav_a_site'] ) : '' ) );
	}
}

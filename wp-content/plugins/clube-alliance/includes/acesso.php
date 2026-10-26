<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CAV_Acesso {

	const META_PUBLICO = '_cav_publico'; // membros | parceiros | ambos

	/** Post types que passam pela restrição. */
	public static function tipos_restritos() {
		return apply_filters( 'cav_tipos_restritos', [ 'cav_conteudo', 'cav_sorteio' ] );
	}

	/** O usuário atual é membro com matrícula em dia? */
	public static function e_membro_ativo( $user_id = null ) {
		$user = $user_id ? get_userdata( $user_id ) : wp_get_current_user();
		if ( ! $user || ! $user->ID ) {
			return false;
		}
		$check = CAV_Membro::checar( $user );
		return $check['elegivel'];
	}

	/** O usuário atual opera um estabelecimento parceiro? */
	public static function e_parceiro( $user_id = null ) {
		$user_id = $user_id ?: get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}
		return (bool) CAV_Parceiro::parceiro_do_usuario( $user_id );
	}

	/**
	 * O usuário pode ver conteúdo destinado a este público?
	 *
	 * @param string $publico membros | parceiros | ambos
	 */
	public static function pode_ver( $publico, $user_id = null ) {
		$user_id = $user_id ?: get_current_user_id();

		if ( ! $user_id ) {
			return false;
		}

		if ( user_can( $user_id, 'cav_gerenciar' ) ) {
			return true;
		}

		$membro   = self::e_membro_ativo( $user_id );
		$parceiro = self::e_parceiro( $user_id );

		switch ( $publico ) {
			case 'parceiros':
				return $parceiro;
			case 'ambos':
				return $membro || $parceiro;
			case 'membros':
			default:
				return $membro;
		}
	}

	public static function publico_do_post( $post_id ) {
		$p = get_post_meta( $post_id, self::META_PUBLICO, true );
		return in_array( $p, [ 'membros', 'parceiros', 'ambos' ], true ) ? $p : 'membros';
	}

	public static function pode_ver_post( $post_id, $user_id = null ) {
		return self::pode_ver( self::publico_do_post( $post_id ), $user_id );
	}

	public static function rotulo_publico( $publico ) {
		$mapa = [
			'membros'   => 'Membros',
			'parceiros' => 'Parceiros',
			'ambos'     => 'Membros e parceiros',
		];
		return $mapa[ $publico ] ?? 'Membros';
	}

	/** Mensagem exibida a quem não tem acesso. */
	public static function bloqueio( $publico ) {
		if ( ! is_user_logged_in() ) {
			$texto = 'Entre com sua conta para ver este conteúdo.';
		} elseif ( 'parceiros' === $publico ) {
			$texto = 'Área exclusiva dos estabelecimentos parceiros.';
		} elseif ( is_user_logged_in() && ! self::e_membro_ativo() ) {
			$texto = 'Sua matrícula não está ativa. Fale com a recepção para liberar seu acesso.';
		} else {
			$texto = 'Conteúdo exclusivo para membros do clube.';
		}

		return '<div class="cav-card cav-aviso"><i class="cav-cadeado" aria-hidden="true"></i>' . esc_html( $texto ) . '</div>';
	}

	public static function init() {
		add_filter( 'the_content', [ __CLASS__, 'filtrar_conteudo' ], 20 );
		add_action( 'pre_get_posts', [ __CLASS__, 'esconder_das_listagens' ] );
		add_shortcode( 'cav_restrito', [ __CLASS__, 'sc_restrito' ] );

		// Conteúdo de membro não vaza por outros caminhos: mapa do site, oEmbed e a versão "embed" da página.
		add_filter( 'wp_sitemaps_post_types', [ __CLASS__, 'tirar_do_mapa_do_site' ] );
		add_filter( 'oembed_request_post_id', [ __CLASS__, 'oembed_so_publico' ] );
		add_action( 'template_redirect', [ __CLASS__, 'embed_so_publico' ], 1 );
	}

	public static function tirar_do_mapa_do_site( $tipos ) {
		foreach ( self::tipos_restritos() as $t ) {
			unset( $tipos[ $t ] );
		}
		return $tipos;
	}

	public static function oembed_so_publico( $post_id ) {
		return ( $post_id && in_array( get_post_type( $post_id ), self::tipos_restritos(), true ) ) ? 0 : $post_id;
	}

	public static function embed_so_publico() {
		if ( is_embed() && is_singular( self::tipos_restritos() ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			include get_query_template( '404' );
			exit;
		}
	}

	/** Bloqueia o corpo do post quando o visitante não tem acesso. */
	public static function filtrar_conteudo( $content ) {
		if ( ! is_singular( self::tipos_restritos() ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post_id = get_the_ID();
		if ( self::pode_ver_post( $post_id ) ) {
			return $content;
		}

		wp_enqueue_style( 'cav' );
		return self::bloqueio( self::publico_do_post( $post_id ) );
	}

	/** Tira das listagens públicas o que o visitante não pode abrir. */
	public static function esconder_das_listagens( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$tipos = (array) $query->get( 'post_type' );
		if ( ! array_intersect( $tipos, self::tipos_restritos() ) ) {
			return;
		}

		if ( current_user_can( 'cav_gerenciar' ) ) {
			return;
		}

		$permitidos = [];
		if ( self::e_membro_ativo() ) {
			$permitidos[] = 'membros';
			$permitidos[] = 'ambos';
		}
		if ( self::e_parceiro() ) {
			$permitidos[] = 'parceiros';
			$permitidos[] = 'ambos';
		}

		if ( ! $permitidos ) {
			$query->set( 'post__in', [ 0 ] );
			return;
		}

		$query->set( 'meta_query', [
			'relation' => 'OR',
			[ 'key' => self::META_PUBLICO, 'value' => array_unique( $permitidos ), 'compare' => 'IN' ],
			[ 'key' => self::META_PUBLICO, 'compare' => 'NOT EXISTS' ],
		] );
	}

	/**
	 * Envolve qualquer trecho no Elementor ou num template.
	 * [cav_restrito publico="parceiros"]conteúdo[/cav_restrito]
	 */
	public static function sc_restrito( $atts, $conteudo = '' ) {
		$atts = shortcode_atts( [ 'publico' => 'membros' ], $atts, 'cav_restrito' );

		if ( ! self::pode_ver( $atts['publico'] ) ) {
			wp_enqueue_style( 'cav' );
			return self::bloqueio( $atts['publico'] );
		}

		return do_shortcode( $conteudo );
	}
}

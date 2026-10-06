<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Para onde cada pessoa vai depois de entrar.
 * Sem isso o WordPress leva todo mundo ao painel de administração.
 */
class CAV_Entrada {

	public static function init() {
		add_filter( 'login_redirect', [ __CLASS__, 'depois_do_login' ], 10, 3 );
		add_filter( 'show_admin_bar', [ __CLASS__, 'esconder_barra' ] );
		add_action( 'admin_init', [ __CLASS__, 'tirar_membro_do_admin' ] );
	}

	/** Endereço de uma página pelo slug; cai na home se ela não existir. */
	private static function pagina( $slug ) {
		$p = get_page_by_path( $slug );
		return $p ? get_permalink( $p ) : home_url( '/' );
	}

	/**
	 * Para onde a pessoa vai e como o link se chama ("Meu terminal", "Minha carteirinha"...).
	 * Devolve null para quem não tem uma área própria do clube.
	 */
	public static function area( $user ) {
		if ( ! ( $user instanceof WP_User ) || ! $user->ID ) {
			return null;
		}
		if ( user_can( $user, 'cav_gerenciar' ) ) {
			return [ 'url' => admin_url( 'admin.php?page=cav-relatorio' ), 'rotulo' => 'Painel do clube' ];
		}
		if ( in_array( 'cav_recepcao', (array) $user->roles, true ) ) {
			return [ 'url' => admin_url( 'admin.php?page=cav-aprovacoes' ), 'rotulo' => 'Aprovações' ];
		}
		if ( in_array( 'cav_parceiro', (array) $user->roles, true ) ) {
			return [ 'url' => self::pagina( 'terminal' ), 'rotulo' => 'Meu terminal' ];
		}
		if ( in_array( 'cav_membro', (array) $user->roles, true ) ) {
			return [ 'url' => self::pagina( 'minha-carteirinha' ), 'rotulo' => 'Minha carteirinha' ];
		}
		return null;
	}

	private static function so_papel_do_clube( $user ) {
		if ( ! ( $user instanceof WP_User ) ) {
			return false;
		}
		return ! user_can( $user, 'edit_posts' ) && ! user_can( $user, 'cav_gerenciar' );
	}

	public static function depois_do_login( $destino, $pedido, $user ) {
		if ( ! ( $user instanceof WP_User ) ) {
			return $destino;
		}

		// Se a pessoa pediu uma página específica fora do admin, respeita.
		if ( $pedido && false === strpos( $pedido, 'wp-admin' ) ) {
			return $destino;
		}

		if ( user_can( $user, 'cav_gerenciar' ) || user_can( $user, 'edit_posts' ) ) {
			return $destino;
		}

		$area = self::area( $user );
		if ( $area ) {
			return $area['url'];
		}

		return $destino;
	}

	/** Membro e parceiro navegam no site, sem a barra preta do WordPress. */
	public static function esconder_barra( $mostrar ) {
		if ( is_user_logged_in() && self::so_papel_do_clube( wp_get_current_user() ) ) {
			return false;
		}
		return $mostrar;
	}

	/** Membro não tem o que fazer no painel: volta para a carteirinha. */
	public static function tirar_membro_do_admin() {
		if ( wp_doing_ajax() || ! is_user_logged_in() ) {
			return;
		}

		$user = wp_get_current_user();
		if ( ! in_array( 'cav_membro', (array) $user->roles, true ) || ! self::so_papel_do_clube( $user ) ) {
			return;
		}

		$tela = isset( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : '';
		if ( in_array( $tela, [ 'profile.php', 'admin-post.php', 'admin-ajax.php' ], true ) ) {
			return;
		}

		wp_safe_redirect( self::pagina( 'minha-carteirinha' ) );
		exit;
	}
}

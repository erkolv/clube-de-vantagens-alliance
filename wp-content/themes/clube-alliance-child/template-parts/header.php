<?php
/**
 * Cabeçalho do clube. Substitui o do Hello Elementor (sem precisar do Elementor Pro).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$usuario = wp_get_current_user();
$area    = class_exists( 'CAV_Entrada' ) && is_user_logged_in() ? CAV_Entrada::area( $usuario ) : null;
?>
<header class="clube-topo" id="site-header">
	<div class="clube-topo__in">

		<a class="clube-marca" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Clube de Vantagens Alliance Mogi, página inicial">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<strong>Alliance</strong>
				<span>Clube de Vantagens</span>
			<?php endif; ?>
		</a>

		<button class="clube-menu-btn" type="button" aria-expanded="false" aria-controls="clube-nav">
			<span class="clube-menu-btn__barras" aria-hidden="true"></span>
			<span class="screen-reader-text">Menu</span>
		</button>

		<nav class="clube-nav" id="clube-nav" aria-label="Principal">
			<?php
			wp_nav_menu( [
				'theme_location' => 'menu-1',
				'container'      => false,
				'menu_class'     => 'clube-menu',
				'fallback_cb'    => false,
				'depth'          => 1,
			] );
			?>

			<div class="clube-acoes">
				<?php if ( is_user_logged_in() ) : ?>
					<?php if ( $area ) : ?>
						<a class="clube-btn" href="<?php echo esc_url( $area['url'] ); ?>"><?php echo esc_html( $area['rotulo'] ); ?></a>
					<?php endif; ?>
					<a class="clube-btn clube-btn--linha" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">Sair</a>
				<?php else : ?>
					<a class="clube-btn clube-btn--linha" href="<?php echo esc_url( home_url( '/entrar/' ) ); ?>">Entrar</a>
					<a class="clube-btn" href="<?php echo esc_url( home_url( '/quero-fazer-parte/' ) ); ?>">Quero fazer parte</a>
				<?php endif; ?>
			</div>
		</nav>

	</div>
</header>

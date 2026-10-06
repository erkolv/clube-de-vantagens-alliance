<?php
/**
 * Tema filho do Hello Elementor para o clube de vantagens.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CLUBE_CHILD_VERSION', '0.2.0' );

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style(
		'clube-poppins',
		'https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400&display=swap',
		[],
		null
	);

	wp_enqueue_style(
		'clube-alliance-child',
		get_stylesheet_uri(),
		[ 'clube-poppins' ],
		CLUBE_CHILD_VERSION
	);

	$site = get_stylesheet_directory() . '/assets/site.css';
	wp_enqueue_style(
		'clube-site',
		get_stylesheet_directory_uri() . '/assets/site.css',
		[ 'clube-alliance-child' ],
		is_readable( $site ) ? filemtime( $site ) : CLUBE_CHILD_VERSION
	);

	// O plugin registra o handle "cav" (prioridade 10). Aqui, mais tarde, anexamos a
	// versão escura: o CSS só sai nas páginas em que um shortcode do plugin roda.
	$arquivo = get_stylesheet_directory() . '/assets/cav-escuro.css';

	if ( wp_style_is( 'cav', 'registered' ) && is_readable( $arquivo ) ) {
		wp_add_inline_style( 'cav', file_get_contents( $arquivo ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
}, 20 );


/**
 * Páginas montadas no Elementor trazem o próprio título. As demais (terminal,
 * carteirinha...) mantêm o título do tema.
 */
add_filter( 'hello_elementor_page_title', function ( $mostrar ) {
	if ( is_page() && get_post_meta( get_queried_object_id(), '_elementor_edit_mode', true ) === 'builder' ) {
		return false;
	}
	return $mostrar;
} );

/** Busca por nome na lista de parceiros: /parceiros/?busca=barbearia */
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ! $q->is_main_query() ) {
		return;
	}
	if ( ! $q->is_post_type_archive( 'cav_parceiro' ) && ! $q->is_tax( 'cav_categoria' ) ) {
		return;
	}
	$q->set( 'posts_per_page', 12 );
	if ( ! empty( $_GET['busca'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$q->set( 's', sanitize_text_field( wp_unslash( $_GET['busca'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
	}
} );

/** Cartão de um parceiro, usado na lista e no shortcode [clube_parceiros]. */
function clube_cartao_parceiro( $id ) {
	$beneficios = class_exists( 'CAV_Parceiro' ) ? CAV_Parceiro::beneficios( $id ) : [];
	$categorias = get_the_terms( $id, 'cav_categoria' );
	$titulo     = get_the_title( $id );
	?>
	<a class="clube-cartao" href="<?php echo esc_url( get_permalink( $id ) ); ?>">
		<span class="clube-cartao__foto">
			<?php if ( has_post_thumbnail( $id ) ) : ?>
				<?php echo get_the_post_thumbnail( $id, 'medium_large', [ 'loading' => 'lazy' ] ); ?>
			<?php else : ?>
				<span class="clube-cartao__inicial" aria-hidden="true"><?php echo esc_html( mb_strtoupper( mb_substr( $titulo, 0, 1 ) ) ); ?></span>
			<?php endif; ?>
		</span>
		<span class="clube-cartao__corpo">
			<?php if ( $categorias && ! is_wp_error( $categorias ) ) : ?>
				<span class="clube-tag"><?php echo esc_html( $categorias[0]->name ); ?></span>
			<?php endif; ?>
			<span class="clube-cartao__titulo"><?php echo esc_html( $titulo ); ?></span>
			<?php if ( $beneficios ) : ?>
				<span class="clube-cartao__meta">
					<?php echo esc_html( get_the_title( $beneficios[0] ) ); ?>
					<?php if ( count( $beneficios ) > 1 ) : ?>
						+ <?php echo (int) ( count( $beneficios ) - 1 ); ?>
					<?php endif; ?>
				</span>
			<?php endif; ?>
		</span>
	</a>
	<?php
}

/** [clube_parceiros limite="6"] mostra os últimos parceiros publicados. */
add_shortcode( 'clube_parceiros', function ( $atts ) {
	$atts = shortcode_atts( [ 'limite' => 6 ], $atts, 'clube_parceiros' );

	$consulta = new WP_Query( [
		'post_type'           => 'cav_parceiro',
		'post_status'         => 'publish',
		'posts_per_page'      => max( 1, (int) $atts['limite'] ),
		'orderby'             => 'date',
		'order'               => 'DESC',
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	] );

	ob_start();
	if ( $consulta->have_posts() ) {
		echo '<div class="clube-cartoes">';
		while ( $consulta->have_posts() ) {
			$consulta->the_post();
			clube_cartao_parceiro( get_the_ID() );
		}
		echo '</div>';
		wp_reset_postdata();
	} else {
		echo '<p class="clube-vazio">Os primeiros parceiros entram em breve.</p>';
	}
	return ob_get_clean();
} );

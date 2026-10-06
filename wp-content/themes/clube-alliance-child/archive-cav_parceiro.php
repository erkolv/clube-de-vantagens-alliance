<?php
/**
 * Lista de parceiros: /parceiros/ e as categorias.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$categorias = get_terms( [ 'taxonomy' => 'cav_categoria', 'hide_empty' => true ] );
$atual      = is_tax( 'cav_categoria' ) ? get_queried_object_id() : 0;
$busca      = isset( $_GET['busca'] ) ? sanitize_text_field( wp_unslash( $_GET['busca'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$base       = get_post_type_archive_link( 'cav_parceiro' );
?>
<main id="content" class="clube-pagina">

	<section class="clube-topo-pagina">
		<div class="clube-env">
			<p class="clube-olho">Clube de vantagens</p>
			<h1>Parceiros</h1>
			<p class="clube-lead">Comércios de Mogi das Cruzes que dão desconto para quem treina na Alliance.</p>
		</div>
	</section>

	<section class="clube-bloco">
		<div class="clube-env">

			<form class="clube-busca" method="get" action="<?php echo esc_url( $base ); ?>" role="search">
				<label class="screen-reader-text" for="clube-busca">Buscar parceiro</label>
				<input id="clube-busca" type="search" name="busca" value="<?php echo esc_attr( $busca ); ?>" placeholder="Buscar por nome ou tipo de negócio">
				<button class="clube-btn" type="submit">Buscar</button>
			</form>

			<?php if ( ! is_wp_error( $categorias ) && $categorias ) : ?>
				<ul class="clube-chips">
					<li><a class="<?php echo $atual ? '' : 'on'; ?>" href="<?php echo esc_url( $base ); ?>">Todos</a></li>
					<?php foreach ( $categorias as $c ) : ?>
						<li>
							<a class="<?php echo (int) $atual === (int) $c->term_id ? 'on' : ''; ?>" href="<?php echo esc_url( get_term_link( $c ) ); ?>">
								<?php echo esc_html( $c->name ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( have_posts() ) : ?>
				<div class="clube-cartoes">
					<?php
					while ( have_posts() ) {
						the_post();
						clube_cartao_parceiro( get_the_ID() );
					}
					?>
				</div>
				<?php the_posts_pagination( [ 'mid_size' => 1, 'prev_text' => 'Anterior', 'next_text' => 'Próxima' ] ); ?>
			<?php else : ?>
				<p class="clube-vazio">
					<?php echo $busca ? 'Nenhum parceiro encontrado para essa busca.' : 'Ainda não há parceiros publicados.'; ?>
				</p>
			<?php endif; ?>

		</div>
	</section>

</main>
<?php
get_footer();

<?php
/**
 * Só no ambiente local: usa o endereço gravado no WordPress como "Host" da requisição.
 *
 * No GitHub Codespaces o proxy entrega o pedido ao container como localhost:8080.
 * O WordPress compara esse nome com o do site e, em páginas internas, manda o
 * navegador para https://localhost/... (o que não existe no seu computador).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_ENVIRONMENT_TYPE' ) || 'local' !== WP_ENVIRONMENT_TYPE ) {
	return;
}

( function () {
	if ( empty( $_SERVER['HTTP_HOST'] ) ) {
		return;
	}

	$home = parse_url( (string) get_option( 'home' ) );
	if ( empty( $home['host'] ) ) {
		return;
	}

	$host = $home['host'] . ( ! empty( $home['port'] ) ? ':' . $home['port'] : '' );

	if ( 0 === strpos( $_SERVER['HTTP_HOST'], 'localhost' ) && $_SERVER['HTTP_HOST'] !== $host ) {
		$_SERVER['HTTP_HOST'] = $host;
	}
} )();

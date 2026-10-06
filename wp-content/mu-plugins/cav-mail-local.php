<?php
/**
 * Plugin Name: Clube Alliance — e-mail local
 * Description: Manda todo e-mail do WordPress para o Mailpit quando o ambiente é o do Docker.
 *              Em produção este arquivo não existe, e o envio passa a depender de um SMTP real.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'phpmailer_init', function ( $phpmailer ) {
	$host = getenv( 'CAV_SMTP_HOST' );

	if ( ! $host ) {
		return;
	}

	$phpmailer->isSMTP();
	$phpmailer->Host        = $host;
	$phpmailer->Port        = (int) ( getenv( 'CAV_SMTP_PORT' ) ?: 1025 );
	$phpmailer->SMTPAuth    = false;
	$phpmailer->SMTPAutoTLS = false;
} );

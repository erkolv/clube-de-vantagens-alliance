<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Confere o "ID token" que o Google entrega depois que a pessoa escolhe a conta.
 * A conferência é feita aqui no servidor, sem confiar no navegador: assinatura RS256
 * com as chaves públicas do Google, emissor, destinatário (o ID do cliente do clube),
 * validade e e-mail verificado. Não precisa de senha nem de "client secret".
 */
class CAV_Google {

	const URL_CHAVES = 'https://www.googleapis.com/oauth2/v3/certs';
	const CACHE      = 'cav_google_chaves';

	/**
	 * @return array|WP_Error  ['email' => ..., 'nome' => ..., 'sub' => ...]
	 */
	public static function verificar( $jwt, $client_id ) {
		$erro = new WP_Error( 'cav_google', 'Não consegui confirmar sua conta Google.' );

		if ( ! $client_id || ! is_string( $jwt ) || substr_count( $jwt, '.' ) !== 2 || strlen( $jwt ) > 4096 ) {
			return $erro;
		}

		list( $cab64, $corpo64, $ass64 ) = explode( '.', $jwt );

		$cab   = json_decode( (string) self::b64u( $cab64 ), true );
		$corpo = json_decode( (string) self::b64u( $corpo64 ), true );
		$ass   = self::b64u( $ass64 );

		if ( ! is_array( $cab ) || ! is_array( $corpo ) || false === $ass || '' === $ass ) {
			return $erro;
		}
		if ( 'RS256' !== ( $cab['alg'] ?? '' ) || empty( $cab['kid'] ) ) {
			return $erro;
		}

		$chave = self::chave( (string) $cab['kid'] );
		if ( ! $chave ) {
			// O Google troca as chaves de tempos em tempos: tenta uma vez com a lista nova.
			$chave = self::chave( (string) $cab['kid'], true );
		}
		if ( ! $chave || empty( $chave['n'] ) || empty( $chave['e'] ) ) {
			return $erro;
		}

		$pem = self::jwk_para_pem( $chave['n'], $chave['e'] );
		if ( ! $pem || 1 !== openssl_verify( $cab64 . '.' . $corpo64, $ass, $pem, OPENSSL_ALGO_SHA256 ) ) {
			return $erro;
		}

		$agora = time();

		if ( ! in_array( $corpo['iss'] ?? '', [ 'accounts.google.com', 'https://accounts.google.com' ], true ) ) {
			return $erro;
		}
		if ( ( $corpo['aud'] ?? '' ) !== $client_id ) {
			return $erro;
		}
		if ( (int) ( $corpo['exp'] ?? 0 ) < $agora - 30 ) {
			return $erro;
		}
		if ( (int) ( $corpo['iat'] ?? 0 ) > $agora + 300 ) {
			return $erro;
		}

		$verificado = $corpo['email_verified'] ?? false;
		if ( true !== $verificado && 'true' !== $verificado ) {
			return $erro;
		}

		$email = isset( $corpo['email'] ) ? strtolower( sanitize_email( $corpo['email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			return $erro;
		}

		return [
			'email' => $email,
			'nome'  => isset( $corpo['name'] ) ? sanitize_text_field( $corpo['name'] ) : '',
			'sub'   => isset( $corpo['sub'] ) ? sanitize_text_field( $corpo['sub'] ) : '',
		];
	}

	/** Chave pública do Google pelo "kid", ou null. */
	private static function chave( $kid, $atualizar = false ) {
		$lista = $atualizar ? false : get_transient( self::CACHE );

		if ( ! is_array( $lista ) ) {
			$lista = self::baixar_chaves();
			if ( $lista ) {
				set_transient( self::CACHE, $lista, 6 * HOUR_IN_SECONDS );
			}
		}

		return ( is_array( $lista ) && isset( $lista[ $kid ] ) ) ? $lista[ $kid ] : null;
	}

	private static function baixar_chaves() {
		$resp = wp_remote_get( self::URL_CHAVES, [ 'timeout' => 8 ] );

		if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
			return [];
		}

		$json = json_decode( wp_remote_retrieve_body( $resp ), true );
		$out  = [];

		foreach ( (array) ( $json['keys'] ?? [] ) as $k ) {
			if ( is_array( $k ) && ! empty( $k['kid'] ) && 'RSA' === ( $k['kty'] ?? '' ) ) {
				$out[ $k['kid'] ] = $k;
			}
		}

		return $out;
	}

	/** base64url -> binário (ou false). */
	private static function b64u( $texto ) {
		$texto = strtr( (string) $texto, '-_', '+/' );
		$resto = strlen( $texto ) % 4;
		if ( $resto ) {
			$texto .= str_repeat( '=', 4 - $resto );
		}
		return base64_decode( $texto, true );
	}

	/** Monta a chave pública PEM a partir do módulo (n) e do expoente (e) do JWK. */
	public static function jwk_para_pem( $n64, $e64 ) {
		$n = self::b64u( $n64 );
		$e = self::b64u( $e64 );

		if ( false === $n || false === $e || '' === $n || '' === $e ) {
			return '';
		}

		$inteiro = static function ( $bin ) {
			$bin = ltrim( $bin, "\0" );
			if ( '' === $bin || ( ord( $bin[0] ) & 0x80 ) ) {
				$bin = "\0" . $bin;
			}
			return "\x02" . self::der_tamanho( strlen( $bin ) ) . $bin;
		};

		$rsa      = $inteiro( $n ) . $inteiro( $e );
		$rsa      = "\x30" . self::der_tamanho( strlen( $rsa ) ) . $rsa;
		$bits     = "\0" . $rsa;
		$bits     = "\x03" . self::der_tamanho( strlen( $bits ) ) . $bits;
		$algoritmo = hex2bin( '300d06092a864886f70d0101010500' ); // rsaEncryption, parâmetros nulos
		$spki     = $algoritmo . $bits;
		$spki     = "\x30" . self::der_tamanho( strlen( $spki ) ) . $spki;

		return "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( $spki ), 64, "\n" ) . "-----END PUBLIC KEY-----\n";
	}

	private static function der_tamanho( $n ) {
		if ( $n < 128 ) {
			return chr( $n );
		}
		$bytes = ltrim( pack( 'N', $n ), "\0" );
		return chr( 0x80 | strlen( $bytes ) ) . $bytes;
	}
}

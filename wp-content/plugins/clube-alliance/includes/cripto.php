<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dados sensíveis (CPF do aluno, CPF do responsável, senha do e-mail) não ficam legíveis no banco.
 * Quem copiar o banco (um backup perdido, um acesso indevido ao banco) vê só texto embaralhado.
 *
 * A chave NÃO fica no banco. Ela vem do wp-config.php:
 *
 *     define( 'CAV_CHAVE_DADOS', '64 caracteres aleatórios' );
 *
 * O scripts/setup.sh cria essa linha sozinho no ambiente de desenvolvimento. Em produção, crie uma
 * (openssl rand -hex 32), coloque no wp-config.php e GUARDE UMA CÓPIA em lugar separado do backup do
 * banco: sem a chave, os CPFs gravados não voltam.
 *
 * Sem a constante, o plugin usa uma chave derivada das chaves secretas do WordPress (também só no
 * wp-config.php). Funciona, mas troca de chave se as chaves do WordPress forem trocadas, por isso o
 * painel avisa. Quando a constante aparece, tudo é regravado com a chave nova.
 */
class CAV_Cripto {

	const FORTE  = 'c1:'; // chave da constante CAV_CHAVE_DADOS
	const FRACA  = 'c0:'; // chave derivada das chaves do WordPress
	const OPCAO  = 'cav_chave_id';

	public static function tem_chave() {
		return defined( 'CAV_CHAVE_DADOS' ) && is_string( CAV_CHAVE_DADOS ) && strlen( CAV_CHAVE_DADOS ) >= 32;
	}

	/** 'c1' quando a constante existe, 'c0' quando não. */
	public static function id_atual() {
		return self::tem_chave() ? 'c1' : 'c0';
	}

	private static function base( $prefixo ) {
		if ( self::FORTE === $prefixo ) {
			return self::tem_chave() ? (string) CAV_CHAVE_DADOS : '';
		}
		return wp_salt( 'secure_auth' ) . '|' . wp_salt( 'auth' );
	}

	private static function chave( $prefixo ) {
		$base = self::base( $prefixo );
		if ( '' === $base ) {
			return '';
		}
		return sodium_crypto_generichash( 'clube-alliance|dados|' . $base, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}

	public static function eh_cifrado( $valor ) {
		return is_string( $valor ) && ( 0 === strpos( $valor, self::FORTE ) || 0 === strpos( $valor, self::FRACA ) );
	}

	/** Embaralha um texto. Texto vazio continua vazio. */
	public static function cifrar( $texto ) {
		$texto = (string) $texto;
		if ( '' === $texto ) {
			return '';
		}

		$prefixo = self::tem_chave() ? self::FORTE : self::FRACA;
		$nonce   = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

		return $prefixo . base64_encode( $nonce . sodium_crypto_secretbox( $texto, $nonce, self::chave( $prefixo ) ) );
	}

	/**
	 * Devolve o texto original. Valor antigo, ainda em texto puro, volta como está.
	 * Se não der para abrir (chave ausente ou trocada), devolve ''.
	 */
	public static function decifrar( $valor ) {
		$valor = (string) $valor;
		if ( '' === $valor || ! self::eh_cifrado( $valor ) ) {
			return $valor;
		}

		$prefixo = substr( $valor, 0, 3 );
		$bin     = base64_decode( substr( $valor, 3 ), true );
		$minimo  = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES;

		if ( false === $bin || strlen( $bin ) < $minimo ) {
			return $valor; // não é um valor nosso, só começa parecido
		}

		$chave = self::chave( $prefixo );
		if ( '' === $chave ) {
			return '';
		}

		$nonce = substr( $bin, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$txt   = sodium_crypto_secretbox_open( substr( $bin, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $nonce, $chave );

		return false === $txt ? '' : $txt;
	}

	/**
	 * "Impressão digital" para achar um registro pelo CPF sem guardar o CPF legível.
	 * Só quem tem a chave consegue calcular; no banco não dá para descobrir o CPF a partir dela.
	 */
	public static function indice( $texto, $contexto = 'cpf' ) {
		$prefixo = self::tem_chave() ? self::FORTE : self::FRACA;
		return hash_hmac( 'sha256', $contexto . '|' . $texto, self::chave( $prefixo ) );
	}

	/** Os valores do banco foram gravados com a chave de hoje? Se não, regrava. */
	public static function manter() {
		if ( get_option( self::OPCAO ) !== self::id_atual() ) {
			self::migrar();
		}
	}

	/** Regrava com a chave atual tudo o que é sensível (e protege o que ainda estava em texto puro). */
	public static function migrar() {
		global $wpdb;

		// CPF dos alunos.
		$linhas = $wpdb->get_results( $wpdb->prepare(
			"SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s",
			CAV_Membro::META_CPF
		) );
		foreach ( (array) $linhas as $l ) {
			$cpf = CAV_CPF::normalizar( self::decifrar( $l->meta_value ) );
			if ( 11 !== strlen( $cpf ) ) {
				continue; // não abriu: deixa como está, em vez de apagar
			}
			update_user_meta( (int) $l->user_id, CAV_Membro::META_CPF, self::cifrar( $cpf ) );
			update_user_meta( (int) $l->user_id, CAV_Membro::META_CPF_IDX, self::indice( $cpf ) );
		}

		// CPF do responsável (alunos menores de idade).
		$linhas = $wpdb->get_results( $wpdb->prepare(
			"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s",
			CAV_Solicitacoes::META_RESP
		) );
		foreach ( (array) $linhas as $l ) {
			$resp = get_user_meta( (int) $l->user_id, CAV_Solicitacoes::META_RESP, true );
			if ( ! is_array( $resp ) || empty( $resp['cpf'] ) ) {
				continue;
			}
			$cpf = CAV_CPF::normalizar( self::decifrar( $resp['cpf'] ) );
			if ( 11 !== strlen( $cpf ) ) {
				continue;
			}
			$resp['cpf'] = self::cifrar( $cpf );
			update_user_meta( (int) $l->user_id, CAV_Solicitacoes::META_RESP, $resp );
		}

		// Senha do e-mail (tela Clube → E-mails).
		$smtp = get_option( CAV_SMTP::OPT, [] );
		if ( is_array( $smtp ) && ! empty( $smtp['senha'] ) ) {
			$claro = self::decifrar( $smtp['senha'] );
			if ( '' !== $claro ) {
				$smtp['senha'] = self::cifrar( $claro );
				update_option( CAV_SMTP::OPT, $smtp, false );
			}
		}

		update_option( self::OPCAO, self::id_atual() );
	}
}

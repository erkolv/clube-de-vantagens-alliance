<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CAV_CPF {

	/** Remove tudo que não for dígito. */
	public static function normalizar( $cpf ) {
		return preg_replace( '/\D/', '', (string) $cpf );
	}

	/** Valida os dígitos verificadores. */
	public static function valido( $cpf ) {
		$cpf = self::normalizar( $cpf );

		if ( strlen( $cpf ) !== 11 ) {
			return false;
		}

		// Rejeita sequências iguais (000.000.000-00, 111..., etc).
		if ( preg_match( '/^(\d)\1{10}$/', $cpf ) ) {
			return false;
		}

		for ( $t = 9; $t < 11; $t++ ) {
			$soma = 0;
			for ( $i = 0; $i < $t; $i++ ) {
				$soma += (int) $cpf[ $i ] * ( ( $t + 1 ) - $i );
			}
			$digito = ( ( $soma * 10 ) % 11 ) % 10;
			if ( (int) $cpf[ $t ] !== $digito ) {
				return false;
			}
		}

		return true;
	}

	/** 12345678901 -> 123.456.789-01 */
	public static function formatar( $cpf ) {
		$cpf = self::normalizar( $cpf );
		if ( strlen( $cpf ) !== 11 ) {
			return $cpf;
		}
		return substr( $cpf, 0, 3 ) . '.' . substr( $cpf, 3, 3 ) . '.' . substr( $cpf, 6, 3 ) . '-' . substr( $cpf, 9, 2 );
	}

	/** 12345678901 -> ***.456.789-** (para exibir sem expor o documento inteiro) */
	public static function mascarar( $cpf ) {
		$cpf = self::normalizar( $cpf );
		if ( strlen( $cpf ) !== 11 ) {
			return '';
		}
		return '***.' . substr( $cpf, 3, 3 ) . '.' . substr( $cpf, 6, 3 ) . '-**';
	}

	/** Hash para o log de consultas — o CPF cru nunca vai para a tabela de log. */
	public static function hash( $cpf ) {
		return hash_hmac( 'sha256', self::normalizar( $cpf ), wp_salt( 'auth' ) );
	}

	/** Retorna o WP_User dono do CPF, ou null. */
	public static function buscar_usuario( $cpf ) {
		$cpf = self::normalizar( $cpf );
		if ( ! self::valido( $cpf ) ) {
			return null;
		}

		$users = get_users( [
			'meta_key'    => CAV_Membro::META_CPF,
			'meta_value'  => $cpf,
			'number'      => 1,
			'count_total' => false,
		] );

		return $users ? $users[0] : null;
	}
}

<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Helpers {
    private const TAX_CIPHER = 'aes-256-cbc';

    public static function ip_hash(): string {
        $salt = (string) get_option( 'luxvv_privacy_ip_hash_salt', 'luxvv' );
        $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
        return hash( 'sha256', $salt . '|' . $ip );
    }

    public static function user_agent(): string {
        return substr( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 500 );
    }

    public static function referrer(): string {
        return substr( (string) ( $_SERVER['HTTP_REFERER'] ?? '' ), 0, 500 );
    }

    public static function session_id(): string {
        if ( isset( $_COOKIE['luxvv_sid'] ) && is_string( $_COOKIE['luxvv_sid'] ) ) {
            $sid = preg_replace( '/[^a-zA-Z0-9_\-]/', '', $_COOKIE['luxvv_sid'] );
            if ( $sid ) {
                return substr( $sid, 0, 64 );
            }
        }
        $sid = wp_generate_password( 24, false, false );
        if ( ! headers_sent() ) {
            setcookie( 'luxvv_sid', $sid, time() + ( 60 * 60 * 24 * 30 ), COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true );
        }
        return substr( $sid, 0, 64 );
    }

    public static function normalize_tax_id( string $tax_id ): string {
        return preg_replace( '/\D+/', '', $tax_id );
    }

    public static function format_tax_id( string $tax_id, string $type ): string {
        $tax_id = self::normalize_tax_id( $tax_id );
        if ( strlen( $tax_id ) !== 9 ) {
            return $tax_id;
        }

        if ( 'ssn' === $type ) {
            return substr( $tax_id, 0, 3 ) . '-' . substr( $tax_id, 3, 2 ) . '-' . substr( $tax_id, 5 );
        }

        return substr( $tax_id, 0, 2 ) . '-' . substr( $tax_id, 2 );
    }

    public static function mask_tax_id( string $tax_id, string $type ): string {
        $tax_id = self::normalize_tax_id( $tax_id );
        if ( strlen( $tax_id ) !== 9 ) {
            return $tax_id;
        }

        $last4 = substr( $tax_id, -4 );
        $masked = str_repeat( '*', 5 ) . $last4;

        if ( 'ssn' === $type ) {
            return '***-**-' . $last4;
        }

        return '**-***' . $last4;
    }

    public static function encrypt_tax_id( string $tax_id ): string {
        $tax_id = self::normalize_tax_id( $tax_id );
        if ( '' === $tax_id || ! function_exists( 'openssl_encrypt' ) ) {
            return '';
        }

        $key = hash( 'sha256', wp_salt( 'auth' ), true );
        $iv_length = openssl_cipher_iv_length( self::TAX_CIPHER );
        $iv = random_bytes( $iv_length );
        $ciphertext = openssl_encrypt( $tax_id, self::TAX_CIPHER, $key, OPENSSL_RAW_DATA, $iv );

        if ( false === $ciphertext ) {
            return '';
        }

        return base64_encode( $iv . $ciphertext );
    }

    public static function decrypt_tax_id( string $encrypted ): string {
        if ( '' === $encrypted || ! function_exists( 'openssl_decrypt' ) ) {
            return '';
        }

        $decoded = base64_decode( $encrypted, true );
        if ( false === $decoded ) {
            return '';
        }

        $key = hash( 'sha256', wp_salt( 'auth' ), true );
        $iv_length = openssl_cipher_iv_length( self::TAX_CIPHER );
        $iv = substr( $decoded, 0, $iv_length );
        $ciphertext = substr( $decoded, $iv_length );

        $plain = openssl_decrypt( $ciphertext, self::TAX_CIPHER, $key, OPENSSL_RAW_DATA, $iv );
        return false === $plain ? '' : (string) $plain;
    }
}

function luxvv_is_verified( int $user_id = 0 ): bool {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}
	return (int) get_user_meta( $user_id, Verification::VERIFIED_META, true ) === 1;
}

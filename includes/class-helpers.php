<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Helpers {
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

    public static function normalize_tax_id( string $value ): string {
        return preg_replace( '/\D+/', '', $value );
    }

    public static function mask_tax_id( string $value ): string {
        $digits = self::normalize_tax_id( $value );
        if ( strlen( $digits ) < 4 ) {
            return '';
        }
        return str_repeat( '*', max( 0, strlen( $digits ) - 4 ) ) . substr( $digits, -4 );
    }

    public static function format_tax_id( string $value, string $type ): string {
        $digits = self::normalize_tax_id( $value );
        if ( $type === 'ssn' && strlen( $digits ) === 9 ) {
            return substr( $digits, 0, 3 ) . '-' . substr( $digits, 3, 2 ) . '-' . substr( $digits, 5 );
        }
        if ( $type === 'ein' && strlen( $digits ) === 9 ) {
            return substr( $digits, 0, 2 ) . '-' . substr( $digits, 2 );
        }
        return $digits;
    }

    public static function encrypt_sensitive( string $value ): string {
        if ( $value === '' ) {
            return '';
        }
        if ( ! function_exists( 'openssl_encrypt' ) ) {
            return '';
        }
        $key = hash( 'sha256', wp_salt( 'auth' ), true );
        $iv_length = openssl_cipher_iv_length( 'aes-256-cbc' );
        $iv = random_bytes( $iv_length );
        $ciphertext = openssl_encrypt( $value, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
        if ( $ciphertext === false ) {
            return '';
        }
        return base64_encode( $iv . $ciphertext );
    }

    public static function decrypt_sensitive( string $value ): string {
        if ( $value === '' ) {
            return '';
        }
        if ( ! function_exists( 'openssl_decrypt' ) ) {
            return '';
        }
        $decoded = base64_decode( $value, true );
        if ( $decoded === false ) {
            return '';
        }
        $key = hash( 'sha256', wp_salt( 'auth' ), true );
        $iv_length = openssl_cipher_iv_length( 'aes-256-cbc' );
        if ( strlen( $decoded ) <= $iv_length ) {
            return '';
        }
        $iv = substr( $decoded, 0, $iv_length );
        $ciphertext = substr( $decoded, $iv_length );
        $plaintext = openssl_decrypt( $ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
        return $plaintext !== false ? $plaintext : '';
    }
}

function luxvv_is_verified( int $user_id = 0 ): bool {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}
	return (int) get_user_meta( $user_id, LUXVV_VERIFIED_META, true ) === 1;
}

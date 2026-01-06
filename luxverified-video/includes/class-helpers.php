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
}

function luxvv_is_verified( int $user_id = 0 ): bool {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}
	return (int) get_user_meta( $user_id, LUXVV_VERIFIED_META, true ) === 1;
}

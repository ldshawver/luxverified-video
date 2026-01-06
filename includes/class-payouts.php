<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Payouts {

    private static $instance = null;

    public static function instance() : self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function calculate_user_period( int $user_id, string $period ) : array {
        return [
            'user_id' => $user_id,
            'period'  => $period,
            'views'   => 0,
            'revenue' => 0.00,
        ];
    }
}

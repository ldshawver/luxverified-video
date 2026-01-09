<?php
namespace LuxVerified\Rest;
use WP_REST_Controller;

class Forminator_Endpoint extends WP_REST_Controller {

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        register_rest_route('luxvv/v1', '/verification/forminator', [
            'methods'  => 'POST',
            'callback' => [$this, 'handle_form_submission'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handle_form_submission($request) {
        $user_id = absint($request['user_id']);

        if (!$user_id) {
            return new \WP_Error('no_user', 'User ID missing', ['status' => 400]);
        }

        update_user_meta( $user_id, \LuxVerified\Verification::STEP2, 1 );

        return [
            'success' => true,
            'message' => 'Step 2 completed'
        ];
    }
}

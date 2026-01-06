<?php
namespace LuxVerified;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Sync RegistrationMagic registrations to SliceWP affiliates.
 */
class RM_SliceWP_Sync {

    // If you only want to trigger on ONE RM form, set its ID here.
    // Set to 0 to allow all forms.
    protected $allowed_form_id = 0; // e.g. 123;

    public function __construct() {
        /**
         * NOTE: if your RegistrationMagic hook is different,
         * change 'rm_user_registered' below to the correct one.
         */
        add_action( 'rm_user_registered', array( $this, 'maybe_create_affiliate' ), 10, 3 );
    }

    /**
     * Callback fired after RM creates a user.
     *
     * @param int   $user_id
     * @param int   $form_id
     * @param array $data
     */
    public function maybe_create_affiliate( $user_id, $form_id, $data ) {

        // 1. Make sure SliceWP is active
        if ( ! function_exists( 'slicewp_insert_affiliate' ) ) {
            return;
        }

        // 2. Limit to a specific RM form if you want
        if ( $this->allowed_form_id && (int) $form_id !== (int) $this->allowed_form_id ) {
            return;
        }

        // 3. Avoid duplicates
        if ( function_exists( 'slicewp_get_affiliate_id_by_user_id' ) ) {
            $existing = slicewp_get_affiliate_id_by_user_id( $user_id );
            if ( ! empty( $existing ) ) {
                return;
            }
        }

        // 4. Create the affiliate in SliceWP
        $affiliate_id = slicewp_insert_affiliate( array(
            'user_id'      => $user_id,
            'status'       => 'active',
            'date_created' => current_time( 'mysql' ),
        ) );

        if ( empty( $affiliate_id ) ) {
            return;
        }

        // 5. Get the affiliate referral URL
        $referral_url = '';
        if ( function_exists( 'slicewp_get_affiliate_url' ) ) {
            $referral_url = slicewp_get_affiliate_url( array(
                'affiliate_id' => $affiliate_id,
            ) );
        }

        // 6. Store values on the user so LUX / Profile pages can show them
        update_user_meta( $user_id, 'slicewp_affiliate_id', $affiliate_id );
        update_user_meta( $user_id, 'slicewp_affiliate_referral_url', $referral_url );
        update_user_meta( $user_id, 'slicewp_affiliate_status', 'active' );

        // 7. OPTIONAL: capture RM fields and map to LUX/meta
        if ( isset( $data['stage_name'] ) ) {
            update_user_meta( $user_id, 'lux_stage_name', sanitize_text_field( $data['stage_name'] ) );
        }
    }
}

// Boot it.
new RM_SliceWP_Sync();

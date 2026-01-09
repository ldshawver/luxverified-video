<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Videos {

    private const UPLOAD_NONCE_ACTION = 'luxvv_upload_form';

    private static $instance = null;
    private $upload_form_id = 0;

    public static function instance() : self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode( 'luxvv_upload_form', [ $this, 'render_upload_form' ] );
        add_shortcode( 'luxvv_upload', [ $this, 'render_upload_shell' ] );
        add_action( 'admin_post_luxvv_do_upload', [ $this, 'handle_upload' ] );
        add_action( 'admin_post_nopriv_luxvv_do_upload', [ $this, 'handle_upload' ] );

        add_filter( 'forminator_render_form_html', [ $this, 'inject_upload_nonce' ], 10, 3 );
        add_filter( 'forminator_custom_form_submit_errors', [ $this, 'validate_upload_submission' ], 10, 3 );
        add_action( 'forminator_custom_form_after_save_entry', [ $this, 'handle_forminator_upload' ], 10, 4 );
    }

    public function render_upload_shell(): string {
        if ( ! is_user_logged_in() ) {
            return '<p>You must be logged in to upload.</p>';
        }

        $user_id = get_current_user_id();
        if ( ! Verification::user_is_verified( $user_id ) ) {
            return '<p>Your account is not yet verified. Please complete the verification steps.</p>';
        }

        $form_id = (int) Settings::get( 'upload_form_id' );
        if ( ! $form_id ) {
            return '<p>Upload form is not configured.</p>';
        }

        $this->upload_form_id = $form_id;

        return do_shortcode( '[forminator_form id="' . $form_id . '"]' );
    }

    public function inject_upload_nonce( string $html, int $form_id, $settings ) : string {
        if ( ! $this->is_upload_form( $form_id ) ) {
            return $html;
        }

        if ( strpos( $html, 'luxvv_upload_nonce' ) !== false ) {
            return $html;
        }

        $nonce = wp_create_nonce( self::UPLOAD_NONCE_ACTION );
        $field = '<input type="hidden" name="luxvv_upload_nonce" value="' . esc_attr( $nonce ) . '">';

        if ( strpos( $html, '</form>' ) !== false ) {
            return str_replace( '</form>', $field . '</form>', $html );
        }

        return $html . $field;
    }

    public function validate_upload_submission( $errors, $form_id, $field_data ) {
        if ( ! $this->is_upload_form( (int) $form_id ) ) {
            return $errors;
        }

        if ( ! is_user_logged_in() ) {
            $errors[] = __( 'You must be logged in to upload.', 'lux-verified-video' );
            return $errors;
        }

        $user_id = get_current_user_id();
        if ( ! Verification::user_is_verified( $user_id ) ) {
            $errors[] = __( 'Your account is not yet verified.', 'lux-verified-video' );
            return $errors;
        }

        $nonce = isset( $_POST['luxvv_upload_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['luxvv_upload_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, self::UPLOAD_NONCE_ACTION ) ) {
            $errors[] = __( 'Upload form nonce failed.', 'lux-verified-video' );
        }

        return $errors;
    }

    public function handle_forminator_upload( $entry_id, $form_id, $form_data, $form_fields = [] ): void {
        if ( ! $this->is_upload_form( (int) $form_id ) ) {
            return;
        }

        if ( ! is_user_logged_in() ) {
            return;
        }

        $user_id = get_current_user_id();
        if ( ! Verification::user_is_verified( $user_id ) ) {
            return;
        }

        $nonce = isset( $_POST['luxvv_upload_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['luxvv_upload_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, self::UPLOAD_NONCE_ACTION ) ) {
            return;
        }

        $title = $this->extract_value_by_key_match( $form_data, [ 'title', 'video_title' ] );
        $description = $this->extract_value_by_key_match( $form_data, [ 'description', 'video_description' ] );
        $actors = $this->extract_value_by_key_match( $form_data, [ 'actors', 'actor' ] );
        $duration = (int) $this->extract_value_by_key_match( $form_data, [ 'duration', 'length' ] );
        $file_path = $this->extract_uploaded_file_path( $form_data );

        if ( ! $file_path ) {
            $this->log_upload_event( 0, $user_id, 'failed' );
            return;
        }

        $bunny = Bunny::instance()->create_video_slot( $title ?: 'Untitled' );
        if ( is_wp_error( $bunny ) ) {
            $this->log_upload_event( 0, $user_id, 'failed' );
            return;
        }

        $guid = $bunny['guid'] ?? '';
        if ( ! $guid ) {
            $this->log_upload_event( 0, $user_id, 'failed' );
            return;
        }

        $post_id = wp_insert_post( [
            'post_title'   => $title ?: 'Untitled',
            'post_content' => $description ?: '',
            'post_status'  => 'pending',
            'post_author'  => $user_id,
            'post_type'    => 'post',
        ] );

        if ( $post_id && $guid ) {
            update_post_meta( $post_id, '_luxvv_bunny_guid', $guid );
            update_post_meta( $post_id, '_luxvv_duration', $duration );
        }

        global $wpdb;
        $tbl = $wpdb->prefix . 'lux_videos';
        $wpdb->insert( $tbl, [
            'user_id'    => $user_id,
            'post_id'    => $post_id,
            'bunny_guid' => $guid,
            'title'      => $title ?: 'Untitled',
            'duration'   => $duration,
            'status'     => 'uploading',
        ] );

        $video_id = (int) $wpdb->insert_id;
        $this->log_upload_event( $video_id, $user_id, 'uploading' );

        $upload = Bunny::instance()->upload_video_file( $guid, $file_path );
        if ( is_wp_error( $upload ) ) {
            $wpdb->update( $tbl, [ 'status' => 'failed' ], [ 'id' => $video_id ] );
            $this->log_upload_event( $video_id, $user_id, 'failed' );
            return;
        }

        $wpdb->update( $tbl, [ 'status' => 'processing' ], [ 'id' => $video_id ] );
        $this->log_upload_event( $video_id, $user_id, 'processing' );

        if ( $actors ) {
            $actors_arr = array_filter( array_map( 'trim', explode( ',', $actors ) ) );
            foreach ( $actors_arr as $actor_username ) {
                $actor_user = get_user_by( 'login', $actor_username );
                if ( $actor_user ) {
                    $wpdb->insert( $wpdb->prefix . 'lux_video_actors', [
                        'video_id'      => $video_id,
                        'actor_user_id' => $actor_user->ID,
                    ] );
                }
            }
        }
    }

    public function render_upload_form() : string {
        if ( ! is_user_logged_in() ) {
            return '<p>You must be logged in to upload.</p>';
        }
        $user_id = get_current_user_id();
        if ( ! Verification::user_is_verified( $user_id ) ) {
            return '<p>Your account is not yet verified. Please complete the verification steps.</p>';
        }

        $action = esc_url( admin_url( 'admin-post.php' ) );
        ob_start();
        ?>
        <form method="post" action="<?php echo $action; ?>">
            <h3>Upload Video (Bunny)</h3>
            <p>
                <label>Title<br>
                    <input type="text" name="luxvv_title" required class="regular-text">
                </label>
            </p>
            <p>
                <label>Description<br>
                    <textarea name="luxvv_description" rows="4" class="large-text"></textarea>
                </label>
            </p>
            <p>
                <label>Actors (comma-separated BP usernames)<br>
                    <input type="text" name="luxvv_actors" class="regular-text">
                </label>
            </p>
            <p>
                <label>Duration (seconds)<br>
                    <input type="number" name="luxvv_duration" min="0" step="1">
                </label>
            </p>
            <?php wp_nonce_field( 'luxvv_upload', 'luxvv_upload_nonce' ); ?>
            <input type="hidden" name="action" value="luxvv_do_upload">
            <p><button class="button button-primary">Create Video Slot</button></p>
        </form>
        <?php
        return ob_get_clean();
    }

    public function handle_upload() {
        if ( ! is_user_logged_in() ) {
            wp_die( 'Not allowed.' );
        }
        if ( ! isset( $_POST['luxvv_upload_nonce'] ) || ! wp_verify_nonce( $_POST['luxvv_upload_nonce'], 'luxvv_upload' ) ) {
            wp_die( 'Bad nonce.' );
        }

        $user_id    = get_current_user_id();
        if ( ! Verification::user_is_verified( $user_id ) ) {
            wp_die( 'Not verified.' );
        }

        $title       = isset( $_POST['luxvv_title'] ) ? sanitize_text_field( $_POST['luxvv_title'] ) : 'Untitled';
        $description = isset( $_POST['luxvv_description'] ) ? wp_kses_post( $_POST['luxvv_description'] ) : '';
        $actors      = isset( $_POST['luxvv_actors'] ) ? sanitize_text_field( $_POST['luxvv_actors'] ) : '';
        $duration    = isset( $_POST['luxvv_duration'] ) ? absint( $_POST['luxvv_duration'] ) : 0;

        $bunny = Bunny::instance()->create_video_slot( $title );
        if ( is_wp_error( $bunny ) ) {
            wp_die( 'Bunny error: ' . $bunny->get_error_message() );
        }

        $guid = $bunny['guid'] ?? '';
        if ( ! $guid ) {
            wp_die( 'No GUID returned from Bunny.' );
        }

        $post_id = wp_insert_post( [
            'post_title'   => $title,
            'post_content' => $description,
            'post_status'  => 'pending',
            'post_author'  => $user_id,
            'post_type'    => 'post',
        ] );

        if ( $post_id && $guid ) {
            update_post_meta( $post_id, '_luxvv_bunny_guid', $guid );
            update_post_meta( $post_id, '_luxvv_duration', $duration );
        }

        global $wpdb;
        $tbl = $wpdb->prefix . 'lux_videos';
        $wpdb->insert( $tbl, [
            'user_id'   => $user_id,
            'post_id'   => $post_id,
            'bunny_guid'=> $guid,
            'title'     => $title,
            'duration'  => $duration,
            'status'    => 'pending',
        ] );

        $video_id = $wpdb->insert_id;

        if ( $actors ) {
            $actors_arr = array_filter( array_map( 'trim', explode( ',', $actors ) ) );
            foreach ( $actors_arr as $actor_username ) {
                $actor_user = get_user_by( 'login', $actor_username );
                if ( $actor_user ) {
                    $wpdb->insert( $wpdb->prefix . 'lux_video_actors', [
                        'video_id'      => $video_id,
                        'actor_user_id' => $actor_user->ID,
                    ] );
                }
            }
        }

        wp_redirect( add_query_arg( 'luxvv', 'uploaded', wp_get_referer() ) );
        exit;
    }

    private function is_upload_form( int $form_id ): bool {
        if ( ! $this->upload_form_id ) {
            $this->upload_form_id = (int) Settings::get( 'upload_form_id' );
        }

        return $this->upload_form_id && $this->upload_form_id === $form_id;
    }

    private function extract_uploaded_file_path( $form_data ): string {
        if ( is_array( $form_data ) ) {
            foreach ( $form_data as $value ) {
                if ( is_array( $value ) ) {
                    if ( isset( $value['file_path'] ) && is_string( $value['file_path'] ) ) {
                        return $value['file_path'];
                    }
                    if ( isset( $value['file'] ) && is_string( $value['file'] ) ) {
                        return $value['file'];
                    }
                }
            }
        }

        if ( ! empty( $_FILES ) ) {
            $file = reset( $_FILES );
            if ( isset( $file['tmp_name'] ) && is_uploaded_file( $file['tmp_name'] ) ) {
                $handled = wp_handle_upload( $file, [ 'test_form' => false ] );
                if ( ! empty( $handled['file'] ) ) {
                    return $handled['file'];
                }
            }
        }

        return '';
    }

    private function extract_value_by_key_match( $form_data, array $matchers ): string {
        if ( ! is_array( $form_data ) ) {
            return '';
        }

        foreach ( $form_data as $key => $value ) {
            $key = strtolower( (string) $key );
            foreach ( $matchers as $matcher ) {
                if ( false !== strpos( $key, $matcher ) ) {
                    if ( is_array( $value ) && isset( $value['value'] ) ) {
                        return (string) $value['value'];
                    }
                    return is_scalar( $value ) ? (string) $value : '';
                }
            }
        }

        return '';
    }

    private function log_upload_event( int $video_id, int $user_id, string $status ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'lux_video_events';
        $wpdb->insert( $table, [
            'video_id' => $video_id,
            'user_id' => $user_id,
            'event_type' => $status,
            'watch_seconds' => 0,
            'ip_hash' => Helpers::ip_hash(),
            'ua_hash' => hash( 'sha256', Helpers::user_agent() ),
            'created_at' => current_time( 'mysql' ),
        ] );
    }
}

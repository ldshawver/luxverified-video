<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Videos {

    private static $instance = null;

    public static function instance() : self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode( 'luxvv_upload_form', [ $this, 'render_upload_form' ] );
        add_action( 'admin_post_luxvv_do_upload', [ $this, 'handle_upload' ] );
        add_action( 'admin_post_nopriv_luxvv_do_upload', [ $this, 'handle_upload' ] );
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
}

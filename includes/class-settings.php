<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TM_Settings {

    public static function add_menu() {
        add_options_page(
            __( 'Token Membership', 'token-membership' ),
            __( 'Token Membership', 'token-membership' ),
            'manage_options',
            'token-membership',
            [ self::class, 'render_page' ]
        );
    }

    public static function register_settings() {
        register_setting( 'token_membership_group', TM_OPTION_KEY, [
            'sanitize_callback' => [ self::class, 'sanitize' ],
        ] );

        add_settings_section(
            'tm_main',
            __( 'API Connection', 'token-membership' ),
            null,
            'token-membership'
        );

        add_settings_field( 'api_url', __( 'API Base URL', 'token-membership' ),
            [ self::class, 'field_api_url' ], 'token-membership', 'tm_main' );

        add_settings_field( 'api_key', __( 'API Key', 'token-membership' ),
            [ self::class, 'field_api_key' ], 'token-membership', 'tm_main' );

        add_settings_field( 'default_project_id', __( 'Default Project ID', 'token-membership' ),
            [ self::class, 'field_default_project' ], 'token-membership', 'tm_main' );
    }

    public static function sanitize( $input ) {
        return [
            'api_url'            => esc_url_raw( $input['api_url'] ?? '' ),
            'api_key'            => sanitize_text_field( $input['api_key'] ?? '' ),
            'default_project_id' => sanitize_text_field( $input['default_project_id'] ?? '' ),
        ];
    }

    public static function field_api_url() {
        $opts = get_option( TM_OPTION_KEY, [] );
        printf(
            '<input type="url" name="%s[api_url]" value="%s" class="regular-text" placeholder="https://nft-saas-production.up.railway.app" />',
            TM_OPTION_KEY,
            esc_attr( $opts['api_url'] ?? '' )
        );
        echo '<p class="description">' . esc_html__( 'The SPNTN backend URL.', 'token-membership' ) . '</p>';
    }

    public static function field_api_key() {
        $opts = get_option( TM_OPTION_KEY, [] );
        printf(
            '<input type="password" name="%s[api_key]" value="%s" class="regular-text" />',
            TM_OPTION_KEY,
            esc_attr( $opts['api_key'] ?? '' )
        );
        echo '<p class="description">' . esc_html__( 'Your SPNTN API key. Get one at spntn.com.', 'token-membership' ) . '</p>';
    }

    public static function field_default_project() {
        $opts = get_option( TM_OPTION_KEY, [] );
        printf(
            '<input type="text" name="%s[default_project_id]" value="%s" class="regular-text" placeholder="6615f3a2..." />',
            TM_OPTION_KEY,
            esc_attr( $opts['default_project_id'] ?? '' )
        );
        echo '<p class="description">' . esc_html__( 'Used when project_id is omitted from the shortcode.', 'token-membership' ) . '</p>';
    }

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Token Membership Settings', 'token-membership' ); ?></h1>

            <?php settings_errors(); ?>

            <form method="post" action="options.php">
                <?php
                settings_fields( 'token_membership_group' );
                do_settings_sections( 'token-membership' );
                submit_button();
                ?>
            </form>

            <hr>
            <h2><?php esc_html_e( 'Shortcode Usage', 'token-membership' ); ?></h2>
            <p><?php esc_html_e( 'Wrap any content with the shortcode below:', 'token-membership' ); ?></p>
            <code>[token_membership project_id="YOUR_PROJECT_ID"]Protected content here.[/token_membership]</code>
            <p><?php esc_html_e( 'Omit project_id to use the Default Project ID set above.', 'token-membership' ); ?></p>
        </div>
        <?php
    }
}

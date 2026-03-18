<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TM_Settings {

    public static function tm_add_menu() {
        add_options_page(
            __( 'Token Membership', 'token-membership' ),
            __( 'Token Membership', 'token-membership' ),
            'manage_options',
            'token-membership',
            [ self::class, 'render_page' ]
        );
    }

    public static function tm_register_settings() {
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

    public static function tm_sanitize( $input ) {
        return [
            'api_url'            => esc_url_raw( $input['api_url'] ?? '' ),
            'api_key'            => sanitize_text_field( $input['api_key'] ?? '' ),
            'default_project_id' => sanitize_text_field( $input['default_project_id'] ?? '' ),
        ];
    }

    public static function tm_field_api_url() {
        $opts = get_option( TM_OPTION_KEY, [] );
        printf(
            '<input type="url" name="%s[api_url]" value="%s" class="regular-text" placeholder="https://nft-saas-production.up.railway.app" />',
            esc_attr( TM_OPTION_KEY ),
            esc_attr( $opts['api_url'] ?? '' )
        );
        echo '<p class="description">' . esc_html__( 'The SPNTN backend URL.', 'token-membership' ) . '</p>';
    }

    public static function tm_field_api_key() {
        $opts = get_option( TM_OPTION_KEY, [] );
        printf(
            '<input type="password" name="%s[api_key]" value="%s" class="regular-text" />',
            esc_attr( TM_OPTION_KEY ),
            esc_attr( $opts['api_key'] ?? '' )
        );
        echo '<p class="description">' . esc_html__( 'Your SPNTN API key. Get one at spntn.com.', 'token-membership' ) . '</p>';
    }

    public static function tm_field_default_project() {
        $opts = get_option( TM_OPTION_KEY, [] );
        printf(
            '<input type="text" name="%s[default_project_id]" value="%s" class="regular-text" placeholder="6615f3a2..." />',
            esc_attr( TM_OPTION_KEY ),
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

            <!-- ── Quick Setup ──────────────────────────────────────────── -->
            <div style="background:#f5f3ff;border:1px solid #c4b5fd;border-radius:8px;padding:18px 20px;margin-bottom:24px;max-width:640px">
                <h2 style="margin:0 0 6px;font-size:1rem;color:#5b21b6"><?php esc_html_e( '⚡ Quick Setup', 'token-membership' ); ?></h2>
                <p style="margin:0 0 12px;color:#6d28d9;font-size:0.875rem">
                    <?php esc_html_e( 'Generate a Setup Code on your Token Membership dashboard (project page → WordPress Setup) and paste it here. All fields are filled automatically.', 'token-membership' ); ?>
                </p>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                    <input
                        type="text"
                        id="tm-setup-code"
                        placeholder="e.g. A3X7K2B9"
                        maxlength="8"
                        autocomplete="off"
                        style="font-family:monospace;font-size:1.15rem;letter-spacing:3px;padding:6px 12px;border:2px solid #7c3aed;border-radius:6px;width:148px;text-transform:uppercase;color:#5b21b6"
                    />
                    <button type="button" id="tm-apply-code" class="button button-primary" style="background:#7c3aed;border-color:#6d28d9">
                        <?php esc_html_e( 'Connect', 'token-membership' ); ?>
                    </button>
                    <span id="tm-setup-status" style="font-size:0.875rem"></span>
                </div>
            </div>

            <!-- ── Manual Configuration ────────────────────────────────── -->
            <form method="post" action="options.php">
                <?php
                settings_fields( 'token_membership_group' );
                do_settings_sections( 'token-membership' );
                submit_button();
                ?>
            </form>

            <!-- ── Test Connection ─────────────────────────────────────── -->
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px 20px;margin-top:8px;max-width:640px">
                <h2 style="margin:0 0 8px;font-size:1rem;color:#1e293b"><?php esc_html_e( 'Test Connection', 'token-membership' ); ?></h2>
                <p style="margin:0 0 10px;color:#64748b;font-size:0.875rem">
                    <?php esc_html_e( 'Verify that the saved API URL and Key are working correctly.', 'token-membership' ); ?>
                </p>
                <button type="button" id="tm-test-connection" class="button">
                    <?php esc_html_e( 'Test Connection', 'token-membership' ); ?>
                </button>
                <span id="tm-test-status" style="margin-left:10px;font-size:0.875rem"></span>
            </div>

            <hr style="margin:28px 0 20px">
            <h2><?php esc_html_e( 'Shortcode Usage', 'token-membership' ); ?></h2>
            <p><?php esc_html_e( 'Wrap any content with the shortcode below:', 'token-membership' ); ?></p>
            <code>[token_membership project_id="YOUR_PROJECT_ID"]Protected content here.[/token_membership]</code>
            <p><?php esc_html_e( 'Omit project_id to use the Default Project ID set above.', 'token-membership' ); ?></p>
        </div>

        <script>
        (function($) {
            var nonce   = (typeof tmAdmin !== 'undefined') ? tmAdmin.nonce   : '';
            var ajaxUrl = (typeof tmAdmin !== 'undefined') ? tmAdmin.ajaxUrl : ajaxurl;

            // ── Quick Setup ───────────────────────────────────────────────
            $('#tm-apply-code').on('click', function () {
                var code = $('#tm-setup-code').val().trim().toUpperCase();
                if (!code) {
                    $('#tm-setup-status').html('<span style="color:#dc2626"><?php echo esc_js( __( 'Please enter a code.', 'token-membership' ) ); ?></span>');
                    return;
                }
                var btn = $(this).prop('disabled', true).text('<?php echo esc_js( __( 'Connecting\u2026', 'token-membership' ) ); ?>');
                $('#tm-setup-status').text('');

                $.post(ajaxUrl, {
                    action: 'tm_exchange_setup_code',
                    nonce:  nonce,
                    code:   code,
                }, function (res) {
                    if (res.success) {
                        $('input[name="token_membership_settings[api_url]"]').val(res.data.apiUrl);
                        $('input[name="token_membership_settings[api_key]"]').val(res.data.apiKey);
                        if (res.data.defaultProjectId) {
                            $('input[name="token_membership_settings[default_project_id]"]').val(res.data.defaultProjectId);
                        }
                        $('#tm-setup-status').html('<span style="color:#16a34a;font-weight:600">&#10003; <?php echo esc_js( __( 'Connected! Click Save Changes to apply.', 'token-membership' ) ); ?></span>');
                    } else {
                        $('#tm-setup-status').html('<span style="color:#dc2626">' + (res.data && res.data.message ? res.data.message : '<?php echo esc_js( __( 'Invalid or expired code.', 'token-membership' ) ); ?>') + '</span>');
                    }
                }).fail(function () {
                    $('#tm-setup-status').html('<span style="color:#dc2626"><?php echo esc_js( __( 'Request failed. Please try again.', 'token-membership' ) ); ?></span>');
                }).always(function () {
                    btn.prop('disabled', false).text('<?php echo esc_js( __( 'Connect', 'token-membership' ) ); ?>');
                });
            });

            // Uppercase input as user types
            $('#tm-setup-code').on('input', function () {
                var pos = this.selectionStart;
                this.value = this.value.toUpperCase();
                this.setSelectionRange(pos, pos);
            });

            // ── Test Connection ───────────────────────────────────────────
            $('#tm-test-connection').on('click', function () {
                var btn = $(this).prop('disabled', true).text('<?php echo esc_js( __( 'Testing\u2026', 'token-membership' ) ); ?>');
                $('#tm-test-status').text('');

                $.post(ajaxUrl, {
                    action: 'tm_test_connection',
                    nonce:  nonce,
                }, function (res) {
                    if (res.success) {
                        var info = res.data.email ? ' (' + res.data.email + ' · ' + res.data.tier + ')' : '';
                        $('#tm-test-status').html('<span style="color:#16a34a;font-weight:600">&#10003; <?php echo esc_js( __( 'Connected', 'token-membership' ) ); ?>' + info + '</span>');
                    } else {
                        var msg = res.data && res.data.message ? res.data.message : '<?php echo esc_js( __( 'Connection failed.', 'token-membership' ) ); ?>';
                        $('#tm-test-status').html('<span style="color:#dc2626">&#10007; ' + msg + '</span>');
                    }
                }).fail(function () {
                    $('#tm-test-status').html('<span style="color:#dc2626"><?php echo esc_js( __( 'Request failed.', 'token-membership' ) ); ?></span>');
                }).always(function () {
                    btn.prop('disabled', false).text('<?php echo esc_js( __( 'Test Connection', 'token-membership' ) ); ?>');
                });
            });
        })(jQuery);
        </script>
        <?php
    }
}

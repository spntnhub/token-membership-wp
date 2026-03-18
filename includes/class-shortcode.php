<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TM_Shortcode {

    public static function tm_register() {
        add_shortcode( 'token_membership', [ self::class, 'render' ] );
        // Spec alias: [token_gate project="123"] works identically
        add_shortcode( 'token_gate', [ self::class, 'render_gate_alias' ] );
    }

    /**
     * [token_gate project="123"] — alias that maps 'project' to 'project_id'
     */
    public static function tm_render_gate_alias( $atts, $content = '' ) {
        if ( isset( $atts['project'] ) && ! isset( $atts['project_id'] ) ) {
            $atts['project_id'] = $atts['project'];
        }
        return self::render( $atts, $content );
    }

    /**
     * Gutenberg render_callback.
     * Receives $attributes (block attrs) and $content (rendered InnerBlocks HTML).
     */
    public static function tm_render_block( array $attributes, string $content ): string {
        return self::render(
            [
                'project_id'  => $attributes['projectId']   ?? '',
                'title'       => $attributes['title']       ?? 'Members Only',
                'description' => $attributes['description'] ?? 'This content is for members only.',
            ],
            $content
        );
    }

    /**
     * [token_membership project_id="abc123"]
     *   Protected content here.
     * [/token_membership]
     */
    public static function tm_render( $atts, $content = '' ) {
        $opts = get_option( TM_OPTION_KEY, [] );

        $atts = shortcode_atts(
            [
                'project_id'  => $opts['default_project_id'] ?? '',
                'title'       => __( 'Members Only', 'token-membership' ),
                'description' => __( 'This content is for members only.', 'token-membership' ),
            ],
            $atts,
            'token_membership'
        );

        $project_id  = sanitize_text_field( $atts['project_id'] );
        $title       = sanitize_text_field( $atts['title'] );
        $description = sanitize_text_field( $atts['description'] );

        if ( ! $project_id ) {
            if ( current_user_can( 'manage_options' ) ) {
                return '<p class="tm-error">' .
                    esc_html__( '[token_membership] Error: no project_id set. Configure it in Settings → Token Membership.', 'token-membership' ) .
                    '</p>';
            }
            return '';
        }

        $unique_id = 'tm-gate-' . esc_attr( $project_id ) . '-' . wp_rand( 1000, 9999 );

        ob_start();
        ?>
        <div class="tm-gate" id="<?php echo esc_attr( $unique_id ); ?>"
             data-project-id="<?php echo esc_attr( $project_id ); ?>">

            <?php /* Loading state */ ?>
            <div class="tm-state tm-state--loading">
                <p><?php esc_html_e( 'Checking access…', 'token-membership' ); ?></p>
            </div>

            <?php /* Not connected */ ?>
            <div class="tm-state tm-state--disconnected" style="display:none">
                <strong class="tm-gate-title"><?php echo esc_html( $title ); ?></strong>
                <p><?php echo esc_html( $description ); ?></p>
                <button class="tm-btn tm-btn--connect" type="button">
                    <?php esc_html_e( 'Connect Wallet', 'token-membership' ); ?>
                </button>
            </div>

            <?php /* No access — show price and buy button */ ?>
            <div class="tm-state tm-state--no-access" style="display:none">
                <strong class="tm-gate-title"><?php echo esc_html( $title ); ?></strong>
                <p><?php echo esc_html( $description ); ?></p>
                <p class="tm-price-display" style="display:none"></p>
                <button class="tm-btn tm-btn--buy" type="button"
                        data-project-id="<?php echo esc_attr( $project_id ); ?>">
                    <?php esc_html_e( 'Get Membership', 'token-membership' ); ?>
                </button>
            </div>

            <?php /* Expired — token exists but validity period has passed */ ?>
            <div class="tm-state tm-state--expired" style="display:none">
                <div class="tm-expired-badge">
                    <span class="tm-expired-icon">⏱</span>
                    <strong><?php esc_html_e( 'Membership Expired', 'token-membership' ); ?></strong>
                </div>
                <p><?php esc_html_e( 'Your membership token has expired. Renew to restore access.', 'token-membership' ); ?></p>
                <p class="tm-price-display" style="display:none"></p>
                <button class="tm-btn tm-btn--buy" type="button"
                        data-project-id="<?php echo esc_attr( $project_id ); ?>">
                    <?php esc_html_e( 'Renew Membership', 'token-membership' ); ?>
                </button>
            </div>

            <?php /* Purchase in progress — step indicator */ ?>
            <div class="tm-state tm-state--minting" style="display:none">
                <div class="tm-progress">
                    <div class="tm-progress-step tm-progress-step--active" data-step="1">
                        <span class="tm-step-dot"></span>
                        <span class="tm-step-label"><?php esc_html_e( 'Preparing', 'token-membership' ); ?></span>
                    </div>
                    <span class="tm-step-connector"></span>
                    <div class="tm-progress-step tm-progress-step--pending" data-step="2">
                        <span class="tm-step-dot"></span>
                        <span class="tm-step-label"><?php esc_html_e( 'Sign transaction', 'token-membership' ); ?></span>
                    </div>
                    <span class="tm-step-connector"></span>
                    <div class="tm-progress-step tm-progress-step--pending" data-step="3">
                        <span class="tm-step-dot"></span>
                        <span class="tm-step-label"><?php esc_html_e( 'Confirming', 'token-membership' ); ?></span>
                    </div>
                    <span class="tm-step-connector"></span>
                    <div class="tm-progress-step tm-progress-step--pending" data-step="4">
                        <span class="tm-step-dot"></span>
                        <span class="tm-step-label"><?php esc_html_e( 'Unlocking', 'token-membership' ); ?></span>
                    </div>
                </div>
                <p class="tm-minting-status"><?php esc_html_e( 'Preparing…', 'token-membership' ); ?></p>
            </div>

            <?php /* Error state */ ?>
            <div class="tm-state tm-state--error" style="display:none">
                <p class="tm-error tm-error-msg"></p>
                <button class="tm-btn tm-btn--retry" type="button">
                    <?php esc_html_e( 'Try Again', 'token-membership' ); ?>
                </button>
            </div>

            <?php /* Success / celebration state — shown briefly after mint before content appears */ ?>
            <div class="tm-state tm-state--success" style="display:none">
                <div class="tm-success-icon">🎉</div>
                <p><?php esc_html_e( "Welcome! You're now a member.", 'token-membership' ); ?></p>
            </div>

            <?php /* Access granted — render actual content */ ?>
            <div class="tm-state tm-state--access" style="display:none">
                <p class="tm-expiry-notice" style="display:none"></p>
                <?php echo wp_kses_post( do_shortcode( $content ) ); ?>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }
}

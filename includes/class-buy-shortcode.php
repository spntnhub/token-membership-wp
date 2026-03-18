<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * TM_Buy_Shortcode
 *
 * Renders a standalone buy / membership button without gating any content.
 *
 * Usage:
 *   [token_buy project_id="abc123"]
 *   [token_buy project_id="abc123" label="Subscribe Now"]
 *
 * When wallet is connected and user already holds the token the shortcode
 * shows an "Active Member" badge instead of the buy flow.
 * All state transitions are handled by the same access-check.js used for
 * the [token_membership] gate — the only difference is the "access" state
 * shows a badge rather than gated content.
 */
class TM_Buy_Shortcode {

    public static function tm_register() {
        add_shortcode( 'token_buy', [ self::class, 'render' ] );
    }

    public static function tm_render( $atts ) {
        $opts = get_option( TM_OPTION_KEY, [] );

        $atts = shortcode_atts(
            [
                'project_id'  => $opts['default_project_id'] ?? '',
                'label'       => __( 'Get Membership', 'token-membership' ),
                'description' => '',
            ],
            $atts,
            'token_buy'
        );

        $project_id  = sanitize_text_field( $atts['project_id'] );
        $label       = sanitize_text_field( $atts['label'] );
        $description = sanitize_text_field( $atts['description'] );

        if ( ! $project_id ) {
            if ( current_user_can( 'manage_options' ) ) {
                return '<p class="tm-error">' .
                    esc_html__( '[token_buy] Error: no project_id set. Configure it in Settings → Token Membership.', 'token-membership' ) .
                    '</p>';
            }
            return '';
        }

        $unique_id = 'tm-buy-' . esc_attr( $project_id ) . '-' . wp_rand( 1000, 9999 );

        ob_start();
        ?>
        <div class="tm-gate tm-gate--buy-only" id="<?php echo esc_attr( $unique_id ); ?>"
             data-project-id="<?php echo esc_attr( $project_id ); ?>">

            <?php /* Loading state */ ?>
            <div class="tm-state tm-state--loading">
                <p><?php esc_html_e( 'Checking access…', 'token-membership' ); ?></p>
            </div>

            <?php /* Not connected */ ?>
            <div class="tm-state tm-state--disconnected" style="display:none">
                <?php if ( $description ) : ?>
                    <p><?php echo esc_html( $description ); ?></p>
                <?php endif; ?>
                <button class="tm-btn tm-btn--connect" type="button">
                    <?php esc_html_e( 'Connect Wallet', 'token-membership' ); ?>
                </button>
            </div>

            <?php /* No access — show price and buy button */ ?>
            <div class="tm-state tm-state--no-access" style="display:none">
                <?php if ( $description ) : ?>
                    <p><?php echo esc_html( $description ); ?></p>
                <?php endif; ?>
                <p class="tm-price-display" style="display:none"></p>
                <button class="tm-btn tm-btn--buy" type="button"
                        data-project-id="<?php echo esc_attr( $project_id ); ?>">
                    <?php echo esc_html( $label ); ?>
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

            <?php /* Error state */ ?>
            <div class="tm-state tm-state--error" style="display:none">
                <p class="tm-error tm-error-msg"></p>
                <button class="tm-btn tm-btn--retry" type="button">
                    <?php esc_html_e( 'Try Again', 'token-membership' ); ?>
                </button>
            </div>

            <?php /* Success / celebration state */ ?>
            <div class="tm-state tm-state--success" style="display:none">
                <div class="tm-success-icon">🎉</div>
                <p><?php esc_html_e( "Welcome! You're now a member.", 'token-membership' ); ?></p>
            </div>

            <?php /* Access granted — member badge (no content to gate) */ ?>
            <div class="tm-state tm-state--access" style="display:none">
                <div class="tm-member-badge">
                    <span class="tm-member-icon">✓</span>
                    <div>
                        <strong><?php esc_html_e( 'Active Member', 'token-membership' ); ?></strong>
                        <p class="tm-expiry-notice" style="display:none"></p>
                    </div>
                </div>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }
}

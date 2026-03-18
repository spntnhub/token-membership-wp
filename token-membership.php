<?php
/**
 * Plugin Name:       Token Membership
 * Plugin URI:        https://spntn.com/token-membership
 * Description:       Gate your content using blockchain token ownership. Users connect their wallet — if they hold the required membership token, the content is unlocked instantly.
 * Version:           1.4.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            SPNTN
 * Author URI:        https://spntn.com
 * License:           GPL v2 or later
 * Text Domain:       token-membership
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'TM_VERSION',       '1.4.0' );
define( 'TM_PLUGIN_DIR',    plugin_dir_path( __FILE__ ) );
define( 'TM_PLUGIN_URL',    plugin_dir_url( __FILE__ ) );
define( 'TM_OPTION_KEY',    'tm_token_membership_settings' );
// Exchange URL for one-time plugin setup codes (never changes).
define( 'TM_EXCHANGE_URL',  'https://nft-saas-production.up.railway.app/api/v2/keys/setup-code/exchange' );

require_once TM_PLUGIN_DIR . 'includes/class-settings.php';
require_once TM_PLUGIN_DIR . 'includes/class-shortcode.php';
require_once TM_PLUGIN_DIR . 'includes/class-wallet-session.php';
require_once TM_PLUGIN_DIR . 'includes/class-buy-shortcode.php';

// ── Boot ──────────────────────────────────────────────────────────────────────

add_action( 'init', [ 'TM_Shortcode', 'tm_register' ] );
add_action( 'init', [ 'TM_Buy_Shortcode', 'tm_register' ] );
add_action( 'init', 'tm_register_block' );
add_action( 'admin_menu', [ 'TM_Settings', 'tm_add_menu' ] );
add_action( 'admin_init', [ 'TM_Settings', 'tm_register_settings' ] );
add_action( 'admin_enqueue_scripts', 'tm_admin_enqueue_assets' );
add_action( 'wp_enqueue_scripts', 'tm_enqueue_assets' );
add_action( 'wp_ajax_tm_record_mint',        'tm_ajax_record_mint' );
add_action( 'wp_ajax_nopriv_tm_record_mint', 'tm_ajax_record_mint' );
add_action( 'wp_ajax_tm_exchange_setup_code', 'tm_ajax_exchange_setup_code' );
add_action( 'wp_ajax_tm_test_connection',     'tm_ajax_test_connection' );

// ── Admin assets (settings page only) ────────────────────────────────────────

function tm_admin_enqueue_assets( $hook ) {
    if ( $hook !== 'settings_page_token-membership' ) return;
    wp_localize_script( 'jquery', 'tmAdmin', [
        'nonce'   => wp_create_nonce( 'tm_admin_nonce' ),
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
    ] );
}

// ── AJAX: exchange a setup code for credentials ───────────────────────────────

function tm_ajax_exchange_setup_code() {
    check_ajax_referer( 'tm_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
    }

    $code = strtoupper( sanitize_text_field( $_POST['code'] ?? '' ) );
    if ( ! $code ) {
        wp_send_json_error( [ 'message' => 'Code is required.' ] );
    }

    $response = wp_remote_get(
        TM_EXCHANGE_URL . '?code=' . rawurlencode( $code ),
        [ 'timeout' => 15 ]
    );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( [ 'message' => $response->get_error_message() ] );
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ! ( $body['success'] ?? false ) ) {
        wp_send_json_error( [ 'message' => $body['error'] ?? 'Invalid or expired code.' ] );
    }

    wp_send_json_success( [
        'apiUrl'           => $body['apiUrl']           ?? '',
        'apiKey'           => $body['apiKey']           ?? '',
        'defaultProjectId' => $body['defaultProjectId'] ?? '',
    ] );
}

// ── AJAX: test the saved API connection ───────────────────────────────────────

function tm_ajax_test_connection() {
    check_ajax_referer( 'tm_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
    }

    $options = get_option( TM_OPTION_KEY, [] );
    $api_url = $options['api_url'] ?? '';
    $api_key = $options['api_key'] ?? '';

    if ( ! $api_url || ! $api_key ) {
        wp_send_json_error( [ 'message' => 'API URL and API Key are not configured yet.' ] );
    }

    $response = wp_remote_get( trailingslashit( $api_url ) . 'api/auth/key-info', [
        'headers' => [ 'x-api-key' => $api_key ],
        'timeout' => 10,
    ] );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( [ 'message' => $response->get_error_message() ] );
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $code === 200 && ( $body['success'] ?? false ) ) {
        wp_send_json_success( [
            'email' => $body['email'] ?? '',
            'tier'  => $body['tier']  ?? '',
        ] );
    } else {
        wp_send_json_error( [ 'message' => $body['error'] ?? 'Connection failed (HTTP ' . $code . ').' ] );
    }
}

// ── Gutenberg Block Registration ──────────────────────────────────────────────

function tm_register_block() {
    if ( ! function_exists( 'register_block_type' ) ) return;

    // Register editor script
    wp_register_script(
        'tm-block-editor',
        TM_PLUGIN_URL . 'blocks/token-gate/editor.js',
        [ 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-element' ],
        TM_VERSION,
        true
    );

    // Register editor stylesheet
    wp_register_style(
        'tm-block-editor-style',
        TM_PLUGIN_URL . 'blocks/token-gate/editor.css',
        [ 'wp-block-editor' ],
        TM_VERSION
    );

    register_block_type( 'token-membership/gate', [
        'editor_script'   => 'tm-block-editor',
        'editor_style'    => 'tm-block-editor-style',
        'render_callback' => [ 'TM_Shortcode', 'render_block' ],
        'attributes'      => [
            'projectId'   => [ 'type' => 'string', 'default' => '' ],
            'title'       => [ 'type' => 'string', 'default' => 'Members Only' ],
            'description' => [ 'type' => 'string', 'default' => 'This content is for members only.' ],
        ],
    ] );
}

// ── Asset Enqueue ─────────────────────────────────────────────────────────────

function tm_enqueue_assets() {
    wp_enqueue_style(
        'tm-token-membership',
        TM_PLUGIN_URL . 'assets/css/token-membership.css',
        [],
        TM_VERSION
    );
    wp_enqueue_script(
        'tm-access-check',
        TM_PLUGIN_URL . 'assets/js/access-check.js',
        [],
        TM_VERSION,
        true
    );
    wp_enqueue_script(
        'tm-wallet-connect',
        TM_PLUGIN_URL . 'assets/js/wallet-connect.js',
        [],
        TM_VERSION,
        true
    );
    wp_enqueue_script(
        'tm-ethers',
        TM_PLUGIN_URL . 'assets/js/ethers.umd.min.js',
        [],
        '6.13.2',
        true
    );
    wp_enqueue_style(
        'tm-block-editor',
        TM_PLUGIN_URL . 'blocks/token-gate/editor.css',
        [],
        TM_VERSION
    );
    wp_enqueue_script(
        'tm-block-editor',
        TM_PLUGIN_URL . 'blocks/token-gate/editor.js',
        [],
        TM_VERSION,
        true
    );
}

// ── AJAX: sign mint (server-side proxy to keep API key secret) ───────────────────

add_action( 'wp_ajax_tm_sign_mint',        'tm_ajax_sign_mint' );
add_action( 'wp_ajax_nopriv_tm_sign_mint', 'tm_ajax_sign_mint' );

function tm_ajax_sign_mint() {
    check_ajax_referer( 'tm_nonce', 'nonce' );

    $options = get_option( TM_OPTION_KEY, [] );
    $api_url = $options['api_url'] ?? '';
    $api_key = $options['api_key'] ?? '';

    if ( ! $api_url || ! $api_key ) {
        wp_send_json_error( [ 'message' => 'Plugin not configured.' ] );
    }

    $body = [
        'projectId'    => sanitize_text_field( wp_unslash( $_POST['projectId'] ?? '' ) ),
        'buyerAddress' => sanitize_text_field( wp_unslash( $_POST['buyerAddress'] ?? '' ) ),
    ];

    $response = wp_remote_post( trailingslashit( $api_url ) . 'api/v2/access/sign', [
        'headers' => [
            'Content-Type' => 'application/json',
            'x-api-key'    => $api_key,
        ],
        'body'    => wp_json_encode( $body ),
        'timeout' => 30,
    ] );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( [ 'message' => $response->get_error_message() ] );
    }

    $code = wp_remote_retrieve_response_code( $response );
    $data = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $code !== 200 ) {
        wp_send_json_error( $data );
    }

    wp_send_json_success( $data['data'] ?? $data );
}

// ── AJAX: record mint after on-chain transaction ──────────────────────────────

function tm_ajax_record_mint() {
    check_ajax_referer( 'tm_nonce', 'nonce' );

    $options = get_option( TM_OPTION_KEY, [] );
    $api_url = $options['api_url'] ?? '';
    $api_key = $options['api_key'] ?? '';

    if ( ! $api_url || ! $api_key ) {
        wp_send_json_error( [ 'message' => 'Plugin not configured.' ] );
    }

    $body = [
        'projectId'     => sanitize_text_field( wp_unslash( $_POST['projectId'] ?? '' ) ),
        'walletAddress' => sanitize_text_field( wp_unslash( $_POST['walletAddress'] ?? '' ) ),
        'tokenId'       => intval( $_POST['tokenId'] ?? 0 ),
        'txHash'        => sanitize_text_field( wp_unslash( $_POST['txHash'] ?? '' ) ),
    ];

    $response = wp_remote_post( trailingslashit( $api_url ) . 'api/v2/project/token/mint', [
        'headers' => [
            'Content-Type' => 'application/json',
            'x-api-key'    => $api_key,
        ],
        'body'    => wp_json_encode( $body ),
        'timeout' => 15,
    ] );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( [ 'message' => $response->get_error_message() ] );
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    wp_send_json_success( $data );
}

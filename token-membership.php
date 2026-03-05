<?php
/**
 * Plugin Name:       Token Membership for WordPress
 * Plugin URI:        https://spntn.com/token-membership
 * Description:       Gate your content using blockchain token ownership. Users connect their wallet — if they hold the required membership token, the content is unlocked instantly.
 * Version:           1.2.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            SPNTN
 * Author URI:        https://spntn.com
 * License:           GPL v2 or later
 * Text Domain:       token-membership
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'TM_VERSION',     '1.0.0' );
define( 'TM_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'TM_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'TM_OPTION_KEY',  'token_membership_settings' );

require_once TM_PLUGIN_DIR . 'includes/class-settings.php';
require_once TM_PLUGIN_DIR . 'includes/class-shortcode.php';
require_once TM_PLUGIN_DIR . 'includes/class-wallet-session.php';

// ── Boot ──────────────────────────────────────────────────────────────────────

add_action( 'init', [ 'TM_Shortcode', 'register' ] );
add_action( 'admin_menu', [ 'TM_Settings', 'add_menu' ] );
add_action( 'admin_init', [ 'TM_Settings', 'register_settings' ] );
add_action( 'wp_enqueue_scripts', 'tm_enqueue_assets' );
add_action( 'wp_ajax_tm_record_mint',        'tm_ajax_record_mint' );
add_action( 'wp_ajax_nopriv_tm_record_mint', 'tm_ajax_record_mint' );

// ── Asset Enqueue ─────────────────────────────────────────────────────────────

function tm_enqueue_assets() {
    $options    = get_option( TM_OPTION_KEY, [] );
    $api_url    = $options['api_url'] ?? '';
    $project_id = $options['default_project_id'] ?? '';

    // ethers.js v6 CDN
    wp_enqueue_script(
        'ethers-js',
        'https://cdnjs.cloudflare.com/ajax/libs/ethers/6.13.2/ethers.umd.min.js',
        [],
        '6.13.2',
        true
    );

    wp_enqueue_script(
        'tm-wallet-connect',
        TM_PLUGIN_URL . 'assets/js/wallet-connect.js',
        [ 'ethers-js' ],
        TM_VERSION,
        true
    );

    wp_enqueue_script(
        'tm-access-check',
        TM_PLUGIN_URL . 'assets/js/access-check.js',
        [ 'tm-wallet-connect' ],
        TM_VERSION,
        true
    );

    wp_enqueue_style(
        'tm-styles',
        TM_PLUGIN_URL . 'assets/css/token-membership.css',
        [],
        TM_VERSION
    );

    // Pass config to JS
    wp_localize_script( 'tm-access-check', 'TM_Config', [
        'apiUrl'           => esc_url_raw( $api_url ),
        'defaultProjectId' => sanitize_text_field( $project_id ),
        'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
        'nonce'            => wp_create_nonce( 'tm_nonce' ),
    ] );
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
        'projectId'    => sanitize_text_field( $_POST['projectId'] ?? '' ),
        'buyerAddress' => sanitize_text_field( $_POST['buyerAddress'] ?? '' ),
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
        'projectId'     => sanitize_text_field( $_POST['projectId'] ?? '' ),
        'walletAddress' => sanitize_text_field( $_POST['walletAddress'] ?? '' ),
        'tokenId'       => intval( $_POST['tokenId'] ?? 0 ),
        'txHash'        => sanitize_text_field( $_POST['txHash'] ?? '' ),
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

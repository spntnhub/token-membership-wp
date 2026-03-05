<?php
/**
 * TM_Wallet_Session
 * Server-side placeholder — wallet session is primarily managed client-side
 * via localStorage (wallet-connect.js). This class is reserved for future
 * server-side session features (e.g. cookie-based server rendering).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TM_Wallet_Session {

    const STORAGE_KEY = 'tm_wallet_address';

    /**
     * Returns the connected wallet address from the cookie set by JS,
     * or empty string if not connected.
     */
    public static function get_wallet(): string {
        $wallet = $_COOKIE[ self::STORAGE_KEY ] ?? '';
        return preg_match( '/^0x[0-9a-fA-F]{40}$/', $wallet ) ? strtolower( $wallet ) : '';
    }

    /**
     * Checks access server-side via the SPNTN API.
     * Allows server-rendered gating for themes that need it.
     * Returns null on error (fall back to client-side check).
     */
    public static function check_access( string $project_id, string $wallet_address ): ?bool {
        if ( ! $project_id || ! $wallet_address ) return null;

        $opts    = get_option( TM_OPTION_KEY, [] );
        $api_url = $opts['api_url'] ?? '';
        if ( ! $api_url ) return null;

        $response = wp_remote_post( trailingslashit( $api_url ) . 'api/v2/access/check', [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( [
                'projectId'     => $project_id,
                'walletAddress' => $wallet_address,
            ] ),
            'timeout' => 5,
        ] );

        if ( is_wp_error( $response ) ) return null;

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return (bool) ( $data['access'] ?? false );
    }
}

<?php
defined( 'ABSPATH' ) || exit;

/**
 * Minimal Facebook Graph API client for pushing Team News posts to the club's
 * Facebook Page. Needs a long-lived Page access token with pages_manage_posts
 * + pages_read_engagement (ASTRO → Settings) and the "enable API posting"
 * toggle switched on — until then the portal only offers the assisted manual
 * flow (copy text / download photos).
 */
class WAS_Facebook {

    const GRAPH = 'https://graph.facebook.com/v21.0/';

    public static function is_enabled() {
        return (int) get_option( 'was_fb_api_enabled', 0 )
            && get_option( 'was_fb_page_id', '' ) !== ''
            && get_option( 'was_fb_page_token', '' ) !== '';
    }

    /**
     * Publish a post (message + up to 5 photos) to the page.
     * Returns the Facebook post id, or WP_Error.
     */
    public static function push_post( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) return new WP_Error( 'was_fb', 'Post not found.' );

        $message = $post->post_title . "\n\n" . $post->post_content . "\n\n" . get_permalink( $post );
        $page_id = get_option( 'was_fb_page_id', '' );

        // Upload each photo unpublished; Graph fetches them by public URL.
        $media     = array();
        $photo_ids = array_map( 'absint', (array) get_post_meta( $post_id, WAS_Team_News::META_PHOTOS, true ) );
        foreach ( array_slice( $photo_ids, 0, WAS_Team_News::MAX_PHOTOS ) as $aid ) {
            $url = wp_get_attachment_url( $aid );
            if ( ! $url ) continue;
            $fbid = self::upload_photo( $page_id, $url );
            if ( is_wp_error( $fbid ) ) return $fbid;
            $media[] = $fbid;
        }

        $body = array( 'message' => $message );
        foreach ( $media as $i => $fbid ) {
            $body[ 'attached_media[' . $i . ']' ] = wp_json_encode( array( 'media_fbid' => $fbid ) );
        }

        $res = self::request( $page_id . '/feed', $body );
        if ( is_wp_error( $res ) ) return $res;
        if ( empty( $res['id'] ) ) return new WP_Error( 'was_fb', 'Facebook did not return a post id.' );
        return $res['id'];
    }

    private static function upload_photo( $page_id, $url ) {
        $res = self::request( $page_id . '/photos', array(
            'url'       => $url,
            'published' => 'false',
        ) );
        if ( is_wp_error( $res ) ) return $res;
        if ( empty( $res['id'] ) ) return new WP_Error( 'was_fb', 'Facebook did not return a photo id.' );
        return $res['id'];
    }

    private static function request( $endpoint, $body ) {
        $body['access_token'] = get_option( 'was_fb_page_token', '' );

        $response = wp_remote_post( self::GRAPH . $endpoint, array(
            'timeout' => 30,
            'body'    => $body,
        ) );
        if ( is_wp_error( $response ) ) return $response;

        $json = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $json ) ) {
            return new WP_Error( 'was_fb', 'Unexpected response from Facebook (HTTP ' . wp_remote_retrieve_response_code( $response ) . ').' );
        }
        if ( isset( $json['error'] ) ) {
            return new WP_Error( 'was_fb', $json['error']['message'] ?? 'Unknown Facebook error.' );
        }
        return $json;
    }
}

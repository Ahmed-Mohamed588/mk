<?php

class MKLang_API {

    public static function send_request( $action, $data = array() ) {
        
        $license_key = get_option( 'mklang_license_key' );
        $domain = site_url();
        
        // إنشاء رقم مميز للطلب
        $request_id = uniqid( 'req_', true );

        $payload = array_merge( array(
            'action'      => $action,
            'license_key' => $license_key,
            'domain'      => $domain,
            'request_id'  => $request_id
        ), $data );

        $args = array(
            'body'        => json_encode( $payload ),
            'headers'     => array( 'Content-Type' => 'application/json' ),
            'timeout'     => 60, // مهلة قصيرة، لو عدت هندخل في وضع التتبع
            'blocking'    => true,
            'sslverify'   => false,
        );

        // المحاولة الأولى
        $response = wp_remote_post( MKLANG_API_URL, $args );

        if ( is_wp_error( $response ) ) {
            $error_msg = $response->get_error_message();
            
            // لو الخطأ هو Timeout (cURL 28)، ده معناه السيرفر لسه شغال
            // ندخل وضع التتبع (Polling)
            if ( strpos( $error_msg, 'timed out' ) !== false || strpos( $error_msg, 'cURL error 28' ) !== false ) {
                return self::poll_for_result( $request_id, $license_key, $domain );
            }
            
            return array( 'status' => 'error', 'message' => 'Connection Error: ' . $error_msg );
        }

        return self::process_response( $response );
    }

    /**
     * دالة التتبع: تسأل السيرفر كل شوية "خلصت ولا لسه؟"
     */
    private static function poll_for_result( $request_id, $license_key, $domain ) {
        // هنحاول لمدة 5 دقائق (60 مرة × 5 ثواني)
        $max_attempts = 60; 

        for ( $i = 0; $i < $max_attempts; $i++ ) {
            sleep( 5 ); // انتظر 5 ثواني

            $payload = array(
                'action'      => 'check_request',
                'license_key' => $license_key,
                'domain'      => $domain,
                'request_id'  => $request_id
            );

            $response = wp_remote_post( MKLANG_API_URL, array(
                'body'    => json_encode( $payload ),
                'headers' => array( 'Content-Type' => 'application/json' ),
                'timeout' => 30,
                'sslverify' => false
            ));

            if ( ! is_wp_error( $response ) ) {
                $result = json_decode( wp_remote_retrieve_body( $response ), true );
                
                // لو الحالة success، مبروك الترجمة وصلت
                if ( isset( $result['status'] ) && $result['status'] === 'success' ) {
                    return $result;
                }
                // لو فيه خطأ حقيقي (مش لسه بيحمل)
                if ( isset( $result['status'] ) && $result['status'] === 'error' && $result['message'] !== 'Request not found' ) {
                    return $result;
                }
            }
        }

        return array( 'status' => 'error', 'message' => 'Timeout: Server took too long to respond.' );
    }

    private static function process_response( $response ) {
        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code != 200 ) return array( 'status' => 'error', 'message' => "Server Error ($code)" );
        
        $result = json_decode( $body, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) return array( 'status' => 'error', 'message' => 'Invalid JSON Response' );

        return $result;
    }
}
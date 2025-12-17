<?php

class MKLang_API {

    /**
     * إرسال طلب للسيرفر
     * 
     * @param string $action نوع العملية (activate, check_balance, translate_content, check_request)
     * @param array $data البيانات المطلوب إرسالها
     * @param string $request_id معرّف الطلب الفريد (اختياري)
     * @return array الرد من السيرفر
     */
    public static function send_request( $action, $data = array(), $request_id = '' ) {
        
        $license_key = get_option( 'mklang_license_key' );
        $domain = site_url();
        
        // إنشاء request_id فريد إذا لم يكن موجود
        if ( empty( $request_id ) ) {
            $request_id = uniqid( 'req_', true );
        }

        $payload = array_merge( array(
            'action'      => $action,
            'license_key' => $license_key,
            'domain'      => $domain,
            'request_id'  => $request_id
        ), $data );

        // للطلبات السريعة نستخدم timeout عادي
        // للترجمة نستخدم timeout قصير جداً لأن السيرفر هيشتغل في الخلفية
        $is_quick_action = in_array( $action, array( 'activate', 'check_balance', 'check_request' ) );
        
        $args = array(
            'body'        => json_encode( $payload ),
            'headers'     => array( 'Content-Type' => 'application/json' ),
            'timeout'     => $is_quick_action ? 30 : 10, // 10 ثواني للترجمة، 30 للباقي
            'blocking'    => true,
            'sslverify'   => false,
        );

        error_log( 'MKLang: Sending ' . $action . ' request with ID: ' . $request_id );

        $response = wp_remote_post( MKLANG_API_URL, $args );

        // ============================================================
        // معالجة Timeout في طلبات الترجمة
        // ============================================================
        if ( is_wp_error( $response ) && $action === 'translate_content' ) {
            $error_msg = $response->get_error_message();
            
            // لو حصل timeout، ده طبيعي - السيرفر هيكمل شغله
            if ( strpos( $error_msg, 'timed out' ) !== false || 
                 strpos( $error_msg, 'cURL error 28' ) !== false ||
                 strpos( $error_msg, 'Operation timed out' ) !== false ) {
                
                error_log( 'MKLang: Translation request timeout (expected), server processing in background: ' . $request_id );
                
                // نرجع pending عشان JavaScript يبدأ الـ Polling
                return array( 
                    'status' => 'pending', 
                    'message' => 'السيرفر يعمل على الترجمة في الخلفية...',
                    'request_id' => $request_id
                );
            }
            
            // لو خطأ تاني غير الـ timeout
            error_log( 'MKLang: Connection error for ' . $action . ': ' . $error_msg );
            return array( 'status' => 'error', 'message' => 'Connection Error: ' . $error_msg );
        }

        // ============================================================
        // معالجة أخطاء الاتصال العامة
        // ============================================================
        if ( is_wp_error( $response ) ) {
            $error_msg = $response->get_error_message();
            error_log( 'MKLang: WP_Error for ' . $action . ': ' . $error_msg );
            return array( 'status' => 'error', 'message' => 'Connection Error: ' . $error_msg );
        }

        // معالجة الرد الناجح
        return self::process_response( $response );
    }

    /**
     * معالجة الرد من السيرفر
     * 
     * @param array|WP_Error $response الرد من wp_remote_post
     * @return array البيانات المعالجة
     */
    private static function process_response( $response ) {
        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code != 200 ) {
            error_log( 'MKLang: API returned HTTP code ' . $code );
            return array( 
                'status' => 'error', 
                'message' => "Server Error (HTTP $code)" 
            );
        }
        
        $result = json_decode( $body, true );
        
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            error_log( 'MKLang: Invalid JSON response from API. Body: ' . substr( $body, 0, 500 ) );
            return array( 
                'status' => 'error', 
                'message' => 'Invalid JSON Response from server' 
            );
        }

        return $result;
    }
}
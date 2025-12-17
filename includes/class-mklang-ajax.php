<?php

class MKLang_Ajax {

    public function __construct() {
        add_action( 'wp_ajax_mklang_translate_post', array( $this, 'handle_translation' ) );
        add_action( 'wp_ajax_mklang_check_translation_status', array( $this, 'check_translation_status' ) );
        add_action( 'wp_ajax_mklang_save_wizard', array( $this, 'save_wizard_settings' ) );
    }

    /**
     * بدء الترجمة - يرجع فوراً بـ request_id
     */
    public function handle_translation() {
        check_ajax_referer( 'mklang_nonce', 'nonce' );

        $post_id = intval( $_POST['post_id'] );
        $target_lang = sanitize_text_field( $_POST['target_lang'] );

        if ( ! $post_id || ! $target_lang ) {
            wp_send_json_error( 'بيانات ناقصة.' );
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            wp_send_json_error( 'المقال غير موجود.' );
        }

        // التحقق من وجود ترجمة سابقة
        global $wpdb;
        $table = $wpdb->prefix . 'mklang_translations';
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT translated_id FROM $table WHERE original_id = %d AND lang_code = %s",
            $post_id, $target_lang
        ));

        if ( $existing && get_post( $existing ) ) {
            wp_send_json_error( 'يوجد ترجمة سابقة لهذا المحتوى.' );
        }

        // إنشاء request_id فريد
        $request_id = uniqid( 'req_', true ) . '_' . $post_id . '_' . $target_lang;

        // حفظ معلومات الطلب محلياً
        update_option( 'mklang_pending_' . $request_id, array(
            'post_id' => $post_id,
            'target_lang' => $target_lang,
            'status' => 'starting',
            'created_at' => time()
        ), false );

        // إرسال الطلب للسيرفر بدون انتظار
        $this->send_translation_request_async( $post, $target_lang, $request_id );

        // الرجوع فوراً للمستخدم
        wp_send_json_success( array(
            'message' => 'بدأت عملية الترجمة...',
            'request_id' => $request_id,
            'status' => 'pending'
        ));
    }

    /**
     * إرسال طلب الترجمة بشكل غير متزامن (Async)
     */
    private function send_translation_request_async( $post, $target_lang, $request_id ) {
        $full_content = "<h1>" . $post->post_title . "</h1>" . $post->post_content;
        
        // تحديث الحالة
        update_option( 'mklang_pending_' . $request_id, array(
            'post_id' => $post->ID,
            'target_lang' => $target_lang,
            'status' => 'translating',
            'updated_at' => time()
        ), false );

        // إرسال الطلب للسيرفر مع timeout قصير
        $api_response = MKLang_API::send_request( 'translate_content', array(
            'content' => $full_content,
            'target_lang' => $target_lang,
            'post_id' => $post->ID
        ), $request_id );

        // حفظ النتيجة (سواء نجحت أو فشلت)
        update_option( 'mklang_pending_' . $request_id, array(
            'post_id' => $post->ID,
            'target_lang' => $target_lang,
            'status' => isset( $api_response['status'] ) ? $api_response['status'] : 'error',
            'response' => $api_response,
            'updated_at' => time()
        ), false );
    }

    /**
     * التحقق من حالة الترجمة (يتم استدعاؤه من JavaScript كل 3 ثواني)
     */
    public function check_translation_status() {
        check_ajax_referer( 'mklang_nonce', 'nonce' );

        $request_id = sanitize_text_field( $_POST['request_id'] );
        
        if ( empty( $request_id ) ) {
            wp_send_json_error( 'Request ID مفقود' );
        }

        // جلب الحالة المحلية أولاً
        $local_data = get_option( 'mklang_pending_' . $request_id, false );

        if ( ! $local_data ) {
            wp_send_json_error( 'الطلب غير موجود' );
        }

        // لو الحالة لسه pending أو translating، نسأل السيرفر
        if ( in_array( $local_data['status'], array( 'starting', 'translating', 'pending' ) ) ) {
            
            // سؤال السيرفر عن الحالة
            $server_response = MKLang_API::send_request( 'check_request', array(), $request_id );

            if ( isset( $server_response['status'] ) && $server_response['status'] === 'success' ) {
                // الترجمة خلصت! نعمل المقال
                $result = $this->finalize_translation( 
                    $local_data['post_id'], 
                    $local_data['target_lang'], 
                    $server_response,
                    $request_id
                );

                if ( $result['success'] ) {
                    // حذف البيانات المؤقتة
                    delete_option( 'mklang_pending_' . $request_id );
                    
                    wp_send_json_success( array(
                        'status' => 'completed',
                        'message' => 'تمت الترجمة بنجاح!',
                        'cost' => $result['cost'],
                        'new_id' => $result['new_id'],
                        'edit_link' => $result['edit_link']
                    ));
                } else {
                    wp_send_json_error( $result['message'] );
                }

            } elseif ( isset( $server_response['status'] ) && $server_response['status'] === 'pending' ) {
                // لسه بيشتغل
                wp_send_json_success( array(
                    'status' => 'pending',
                    'message' => 'جاري الترجمة، يرجى الانتظار...'
                ));

            } elseif ( isset( $server_response['status'] ) && $server_response['status'] === 'error' ) {
                // فيه خطأ
                delete_option( 'mklang_pending_' . $request_id );
                wp_send_json_error( $server_response['message'] );
            }

        } elseif ( $local_data['status'] === 'success' && isset( $local_data['response'] ) ) {
            // الترجمة خلصت وكانت محفوظة محلياً
            wp_send_json_success( array(
                'status' => 'completed',
                'message' => 'تمت الترجمة بنجاح!'
            ));

        } else {
            // خطأ
            wp_send_json_error( isset( $local_data['response']['message'] ) ? $local_data['response']['message'] : 'حدث خطأ' );
        }
    }

    /**
     * إتمام الترجمة وإنشاء المقال
     */
    private function finalize_translation( $post_id, $target_lang, $api_response, $request_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return array( 'success' => false, 'message' => 'المقال الأصلي غير موجود' );
        }

        $preferred_status = get_option( 'mklang_post_status', 'draft' );
        
        // إنشاء المقال المترجم
        $translated_html = $api_response['translated_content'];
        $final_title = $post->post_title . ' (' . $target_lang . ')';
        $final_content = $translated_html;

        // استخراج العنوان من الترجمة
        if ( preg_match( '/<h1>(.*?)<\/h1>/s', $translated_html, $matches ) ) {
            $final_title = strip_tags( $matches[1] );
            $final_content = preg_replace( '/<h1>.*?<\/h1>/s', '', $translated_html, 1 );
        }

        $new_post_args = array(
            'post_title'   => $final_title,
            'post_content' => $final_content,
            'post_status'  => $preferred_status,
            'post_type'    => $post->post_type,
            'post_author'  => get_current_user_id(),
            'post_parent'  => $post->post_parent,
        );

        $new_post_id = wp_insert_post( $new_post_args );

        if ( ! $new_post_id || is_wp_error( $new_post_id ) ) {
            return array( 'success' => false, 'message' => 'فشل إنشاء المقال المترجم' );
        }

        // نسخ الميتا والتصنيفات
        $this->duplicate_post_meta( $post_id, $new_post_id );
        $this->translate_and_assign_taxonomies( $post_id, $new_post_id, $target_lang );

        update_post_meta( $new_post_id, '_mklang_lang', $target_lang );
        update_post_meta( $new_post_id, '_mklang_original_id', $post_id );
        $dir = in_array( $target_lang, ['ar', 'he', 'fa', 'ur'] ) ? 'rtl' : 'ltr';
        update_post_meta( $new_post_id, '_mklang_dir', $dir );

        // تسجيل في قاعدة البيانات
        global $wpdb;
        $table = $wpdb->prefix . 'mklang_translations';
        $wpdb->insert( $table, array( 
            'original_id'   => $post_id, 
            'translated_id' => $new_post_id,
            'lang_code'     => $target_lang,
            'type'          => $post->post_type,
            'created_at'    => current_time( 'mysql' )
        ));

        // تحديث الرصيد
        if ( isset( $api_response['remaining_credits'] ) ) {
            update_option( 'mklang_credits', $api_response['remaining_credits'] );
        }

        $cost = isset( $api_response['cost'] ) ? floatval( $api_response['cost'] ) : 0;

        error_log( sprintf( 
            'MKLang: Translation finalized - Post: %d -> %d, Cost: $%.4f', 
            $post_id, $new_post_id, $cost
        ));

        return array(
            'success' => true,
            'new_id' => $new_post_id,
            'cost' => number_format( $cost, 4 ),
            'edit_link' => get_edit_post_link( $new_post_id )
        );
    }

    public function save_wizard_settings() {
        check_ajax_referer( 'mklang_wizard_nonce', 'nonce' );
        
        if ( isset( $_POST['default_lang'] ) ) {
            update_option( 'mklang_default_lang', sanitize_text_field( $_POST['default_lang'] ) );
        }
        
        if ( isset( $_POST['active_langs'] ) && is_array( $_POST['active_langs'] ) ) {
            $clean = array_map( 'sanitize_text_field', $_POST['active_langs'] );
            update_option( 'mklang_active_langs', $clean );
        }
        
        update_option( 'mklang_setup_complete', true );
        
        wp_send_json_success();
    }
    
    private function duplicate_post_meta( $old_id, $new_id ) {
        $excluded_keys = array( '_mklang_lang', '_mklang_original_id', '_mklang_dir', '_edit_lock', '_edit_last' );
        
        $meta_keys = get_post_custom_keys( $old_id );
        if ( empty( $meta_keys ) ) return;
        
        foreach ( $meta_keys as $key ) {
            if ( in_array( $key, $excluded_keys ) || strpos( $key, '_edit_' ) === 0 ) {
                continue;
            }
            
            $meta_values = get_post_custom_values( $key, $old_id );
            foreach ( $meta_values as $value ) {
                add_post_meta( $new_id, $key, maybe_unserialize( $value ) );
            }
        }
    }

    private function translate_and_assign_taxonomies( $old_id, $new_id, $target_lang ) {
        $taxonomies = get_object_taxonomies( get_post_type( $old_id ) );
        
        foreach ( $taxonomies as $taxonomy ) {
            $terms = wp_get_object_terms( $old_id, $taxonomy );
            
            if ( empty( $terms ) || is_wp_error( $terms ) ) continue;
            
            $new_term_ids = array();
            
            foreach ( $terms as $term ) {
                $translated_term_id = get_term_meta( $term->term_id, "_mklang_trans_{$target_lang}_id", true );
                
                if ( ! $translated_term_id ) {
                    $new_term_name = $term->name . ' (' . strtoupper( $target_lang ) . ')';
                    $new_term_slug = $term->slug . '-' . $target_lang;
                    
                    $existing = term_exists( $new_term_name, $taxonomy );
                    
                    if ( $existing ) {
                        $translated_term_id = is_array( $existing ) ? $existing['term_id'] : $existing;
                    } else {
                        $inserted = wp_insert_term( $new_term_name, $taxonomy, array( 
                            'slug' => $new_term_slug,
                            'parent' => $term->parent 
                        ));
                        
                        if ( ! is_wp_error( $inserted ) ) {
                            $translated_term_id = $inserted['term_id'];
                            update_term_meta( $term->term_id, "_mklang_trans_{$target_lang}_id", $translated_term_id );
                            update_term_meta( $translated_term_id, "_mklang_original_id", $term->term_id );
                        }
                    }
                }
                
                if ( $translated_term_id ) {
                    $new_term_ids[] = (int) $translated_term_id;
                }
            }
            
            if ( ! empty( $new_term_ids ) ) {
                wp_set_object_terms( $new_id, $new_term_ids, $taxonomy );
            }
        }
    }
}
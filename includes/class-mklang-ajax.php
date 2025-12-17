<?php

class MKLang_Ajax {

    public function __construct() {
        add_action( 'wp_ajax_mklang_translate_post', array( $this, 'handle_translation' ) );
        add_action( 'wp_ajax_mklang_save_wizard', array( $this, 'save_wizard_settings' ) );
    }

    public function handle_translation() {
        // زيادة وقت السكريبت في الووردبريس نفسه
        @set_time_limit(300); 

        check_ajax_referer( 'mklang_nonce', 'nonce' );

        $post_id = intval( $_POST['post_id'] );
        $target_lang = sanitize_text_field( $_POST['target_lang'] );

        if ( ! $post_id || ! $target_lang ) wp_send_json_error( 'بيانات ناقصة.' );

        $post = get_post( $post_id );
        if ( ! $post ) wp_send_json_error( 'المقال غير موجود.' );

        // -----------------------------------------------------
        // 1. الخطوة الأولى: إنشاء الصفحة "المسودة" فوراً (Placeholder)
        // -----------------------------------------------------
        $preferred_status = get_option( 'mklang_post_status', 'draft' );
        
        $new_post_args = array(
            'post_title'   => $post->post_title . ' (Translating...)', // عنوان مؤقت
            'post_content' => 'Please wait, content is being generated...', // محتوى مؤقت
            'post_status'  => 'draft', // نبدأها دايماً مسودة للأمان
            'post_type'    => $post->post_type,
            'post_author'  => get_current_user_id(),
            'post_parent'  => $post->post_parent,
        );

        $new_post_id = wp_insert_post( $new_post_args );

        if ( ! $new_post_id ) {
            wp_send_json_error( 'فشل إنشاء صفحة الترجمة الأولية.' );
        }

        // نسخ الميتا والتصنيفات فوراً عشان لو الترجمة فشلت يفضل التصميم موجود
        $this->duplicate_post_meta( $post_id, $new_post_id );
        $this->translate_and_assign_taxonomies( $post_id, $new_post_id, $target_lang );

        update_post_meta( $new_post_id, '_mklang_lang', $target_lang );
        update_post_meta( $new_post_id, '_mklang_original_id', $post_id );
        $dir = in_array( $target_lang, ['ar', 'he', 'fa', 'ur'] ) ? 'rtl' : 'ltr';
        update_post_meta( $new_post_id, '_mklang_dir', $dir );

        // -----------------------------------------------------
        // 2. الخطوة الثانية: إرسال المحتوى للترجمة
        // -----------------------------------------------------
        $full_content_payload = "<h1>" . $post->post_title . "</h1>" . $post->post_content;

        $api_response = MKLang_API::send_request( 'translate_content', array(
            'content'     => $full_content_payload,
            'target_lang' => $target_lang
        ));

        // -----------------------------------------------------
        // 3. الخطوة الثالثة: تحديث الصفحة بالمحتوى المترجم
        // -----------------------------------------------------
        if ( isset( $api_response['status'] ) && $api_response['status'] === 'success' ) {
            
            $translated_html = $api_response['translated_content'];
            $final_title = $post->post_title . ' (' . $target_lang . ')';
            $final_content = $translated_html;

            if ( preg_match( '/<h1>(.*?)<\/h1>/s', $translated_html, $matches ) ) {
                $final_title = strip_tags( $matches[1] );
                $final_content = preg_replace( '/<h1>.*?<\/h1>/s', '', $translated_html, 1 );
            }

            // تحديث المقال الذي أنشأناه في الخطوة 1
            $update_args = array(
                'ID'           => $new_post_id,
                'post_title'   => $final_title,
                'post_content' => $final_content,
                'post_status'  => $preferred_status // تطبيق الحالة المفضلة (نشر/مسودة) الآن
            );
            
            wp_update_post( $update_args );

            // تسجيل في قاعدة البيانات المحلية
            global $wpdb;
            $table = $wpdb->prefix . 'mklang_translations';
            $wpdb->insert( $table, array( 
                'original_id'   => $post_id, 
                'translated_id' => $new_post_id,
                'lang_code'     => $target_lang,
                'created_at'    => current_time( 'mysql' )
            ));

            if ( isset( $api_response['remaining_credits'] ) ) {
                update_option( 'mklang_credits', $api_response['remaining_credits'] );
            }

            wp_send_json_success( array( 
                'message' => 'تمت الترجمة بنجاح!', 
                'new_id'  => $new_post_id,
                'cost'    => isset($api_response['cost']) ? $api_response['cost'] : 0
            ));

        } else {
            // في حالة فشل الترجمة، المقال لسه موجود بس حالته "مسودة" وفيه رسالة انتظار
            // ممكن نضيف ملحوظة عليه إنه فشل
            $error_msg = isset( $api_response['message'] ) ? $api_response['message'] : 'API Error';
            
            // تحديث العنوان ليشير للخطأ
            wp_update_post( array(
                'ID' => $new_post_id,
                'post_title' => $post->post_title . ' (فشل الترجمة)',
                'post_content' => 'حدث خطأ أثناء جلب الترجمة: ' . $error_msg
            ));

            wp_send_json_error( $error_msg );
        }
    }

    // (دوال المساعدة duplicate_post_meta و translate_and_assign_taxonomies و save_wizard_settings تبقى كما هي من الكود السابق)
    // يرجى التأكد من وجودها في الملف
    public function save_wizard_settings() {
        check_ajax_referer( 'mklang_wizard_nonce', 'nonce' );
        if ( isset( $_POST['default_lang'] ) ) update_option( 'mklang_default_lang', sanitize_text_field( $_POST['default_lang'] ) );
        if ( isset( $_POST['active_langs'] ) && is_array( $_POST['active_langs'] ) ) {
            $clean = array_map( 'sanitize_text_field', $_POST['active_langs'] );
            update_option( 'mklang_active_langs', $clean );
        }
        update_option( 'mklang_setup_complete', true );
        wp_send_json_success();
    }
    
    private function duplicate_post_meta( $old, $new ) {
        foreach ( get_post_custom_keys( $old ) as $k ) {
            if ( in_array($k, ['_mklang_lang','_mklang_original_id']) || strpos($k,'_edit_')===0 ) continue;
            foreach ( get_post_custom_values($k,$old) as $v ) add_post_meta( $new, $k, maybe_unserialize($v) );
        }
    }

    private function translate_and_assign_taxonomies( $old, $new, $lang ) {
         $taxonomies = get_object_taxonomies( get_post_type( $old ) );
        foreach ( $taxonomies as $taxonomy ) {
            $terms = wp_get_object_terms( $old, $taxonomy );
            if ( empty( $terms ) || is_wp_error( $terms ) ) continue;
            $new_term_ids = array();
            foreach ( $terms as $term ) {
                $translated_term_id = get_term_meta( $term->term_id, "_mklang_trans_{$lang}_id", true );
                if ( ! $translated_term_id ) {
                    $new_term_name = $term->name . ' (' . $lang . ')';
                    $existing = term_exists( $new_term_name, $taxonomy );
                    if ( $existing ) {
                        $translated_term_id = is_array( $existing ) ? $existing['term_id'] : $existing;
                    } else {
                        $inserted = wp_insert_term( $new_term_name, $taxonomy, array( 'slug' => $term->slug . '-' . $lang ) );
                        if ( ! is_wp_error( $inserted ) ) {
                            $translated_term_id = $inserted['term_id'];
                            update_term_meta( $term->term_id, "_mklang_trans_{$lang}_id", $translated_term_id );
                        }
                    }
                }
                if ( $translated_term_id ) $new_term_ids[] = (int) $translated_term_id;
            }
            if ( ! empty( $new_term_ids ) ) wp_set_object_terms( $new, $new_term_ids, $taxonomy );
        }
    }
}
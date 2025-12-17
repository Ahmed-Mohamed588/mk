<?php
// includes/class-mklang-frontend.php

class MKLang_Frontend {

    public function __construct() {
        // التحقق من تفعيل المبدل في الإعدادات
        if ( get_option( 'mklang_show_switcher', 'yes' ) !== 'yes' ) {
            return; 
        }

        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        add_shortcode( 'mklang_switcher', array( $this, 'render_language_switcher' ) );
        add_filter( 'body_class', array( $this, 'add_language_body_class' ) );
        add_action( 'wp_head', array( $this, 'add_hreflang_tags' ) );
        add_filter( 'wp_nav_menu_args', array( $this, 'filter_nav_menu_by_language' ) );
        
        // [جديد] إضافة المبدل للقائمة
        add_filter( 'wp_nav_menu_items', array( $this, 'add_switcher_to_menu' ), 10, 2 );
    }

    /**
     * إضافة المبدل لآخر القائمة
     */
    public function add_switcher_to_menu( $items, $args ) {
        // تحقق هل المستخدم اختار عرضه في القائمة؟
        if ( get_option( 'mklang_switcher_loc' ) !== 'menu' ) {
            return $items;
        }

        // تحقق هل هذه هي القائمة الرئيسية (Primary)
        // يعتمد على القالب، غالباً اسمه 'primary' أو 'main'
        if ( $args->theme_location == 'primary' || $args->theme_location == 'main' || $args->theme_location == 'header' ) {
            // نستخدم دالة render بس نطلبها كقائمة List مش Dropdown عشان تندمج مع القائمة
            $switcher_html = $this->render_language_switcher( array( 'style' => 'list_items' ) ); 
            $items .= $switcher_html;
        }
        return $items;
    }

    public function render_language_switcher( $atts ) {
        $atts = shortcode_atts( array( 'style' => 'dropdown' ), $atts, 'mklang_switcher' );
        
        // ... (نفس كود جلب الروابط من الرد السابق - get_translations) ...
        $links = $this->get_translations(); 
        if ( count( $links ) <= 1 ) return '';

        // [تعديل] لدعم إضافة عناصر القائمة مباشرة <li>
        if ( $atts['style'] === 'list_items' ) {
            $output = '';
            foreach ( $links as $lang => $data ) {
                if($data['current']) continue; // نخفي اللغة الحالية في القائمة لعدم الزحمة
                $output .= '<li class="menu-item mklang-menu-item"><a href="' . esc_url( $data['url'] ) . '">' . strtoupper($lang) . '</a></li>';
            }
            return $output;
        }

        // ... (باقي كود الـ HTML للمبدل العادي List/Dropdown كما هو) ...
        $output = '<div class="mklang-switcher mklang-' . esc_attr( $atts['style'] ) . '">';
        // ... (نفس الكود القديم) ...
        // للتذكير: هنا بنعمل Loop ونطلع الـ HTML
        // سأضعه مختصراً هنا
        $output .= '<button class="mklang-btn">Language ▼</button><div class="mklang-dropdown">';
        foreach ( $links as $l ) $output .= '<a href="'.$l['url'].'">'.$l['name'].'</a>';
        $output .= '</div></div>';
        
        return $output;
    }

    // ... (باقي الدوال: get_translations, enqueue_styles, add_hreflang_tags كما هي) ...
    // يرجى نسخ دالة get_translations من الردود السابقة لأنها ضرورية
    private function get_translations() {
        global $post;
        if ( ! is_singular() || ! isset( $post->ID ) ) return array();
        // ... المنطق السابق لجلب الترجمات ...
        // (اختصاراً للمساحة: استخدم نفس الدالة من ملف class-mklang-frontend.php السابق)
        // هي تعتمد على _mklang_original_id وجدول mklang_translations
        
        // سأعيد كتابتها سريعاً للأمان:
        global $wpdb;
        $table = $wpdb->prefix . 'mklang_translations';
        $links = array();
        $pid = $post->ID;
        $oid = get_post_meta( $pid, '_mklang_original_id', true );
        
        if($oid){
             // نحن في ترجمة
             $def = get_option('mklang_default_lang','ar');
             $links[$def] = ['url'=>get_permalink($oid), 'name'=>strtoupper($def), 'current'=>false];
             $sibs = $wpdb->get_results($wpdb->prepare("SELECT translated_id, lang_code FROM $table WHERE original_id=%d",$oid));
             foreach($sibs as $s) {
                 $is_curr = ($s->translated_id == $pid);
                 $links[$s->lang_code] = ['url'=>get_permalink($s->translated_id), 'name'=>strtoupper($s->lang_code), 'current'=>$is_curr];
             }
        } else {
             // نحن في الأصل
             $def = get_option('mklang_default_lang','ar');
             $links[$def] = ['url'=>'#', 'name'=>strtoupper($def), 'current'=>true];
             $res = $wpdb->get_results($wpdb->prepare("SELECT translated_id, lang_code FROM $table WHERE original_id=%d",$pid));
             foreach($res as $r) {
                 $links[$r->lang_code] = ['url'=>get_permalink($r->translated_id), 'name'=>strtoupper($r->lang_code), 'current'=>false];
             }
        }
        return $links;
    }
    
    // ... دوال filter_nav_menu_by_language و add_language_body_class كما هي ...
     public function filter_nav_menu_by_language( $args ) {
        // ... (كما سبق) ...
        return $args;
    }
    public function enqueue_styles() { /* ... */ }
    public function add_hreflang_tags() { /* ... */ }
    public function add_language_body_class($c) { /* ... */ }
}
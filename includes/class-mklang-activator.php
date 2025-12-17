<?php

class MKLang_Activator {

    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // اسم الجدول الخاص بالإضافة
        $table_name = $wpdb->prefix . 'mklang_translations';

        // جملة SQL لإنشاء الجدول
        // هذا الجدول سيربط بين المقال الأصلي والمقال المترجم
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            original_id bigint(20) NOT NULL,
            translated_id bigint(20) NOT NULL,
            lang_code varchar(10) NOT NULL,
            type varchar(20) DEFAULT 'post' NOT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            KEY original_id (original_id),
            KEY lang_code (lang_code)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        // إعداد خيارات افتراضية عند التفعيل لأول مرة
        if (!get_option('mklang_license_key')) {
            add_option('mklang_license_key', '');
            add_option('mklang_license_status', 'inactive');
        }
    }
}
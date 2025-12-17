<?php
/**
 * Plugin Name:       MKLang AI Translator
 * Plugin URI:        https://mklang.com
 * Description:       MKLang هو البديل الذكي لـ WPML. ترجمة احترافية بالذكاء الاصطناعي لمواقع ووردبريس.
 * Version:           1.0.0
 * Author:            MKLang Team
 * Text Domain:       mklang
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; 
}

// 1. تعريف الثوابت
define( 'MKLANG_VERSION', '1.0.0' );
define( 'MKLANG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MKLANG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
// هام: تأكد من تغيير هذا الرابط لرابط السيرفر الحقيقي الخاص بك عند الرفع
define( 'MKLANG_API_URL', 'https://platform.mk-app.com/api/v1.php' );

// 2. استدعاء الملفات الأساسية
$files = array(
    'includes/class-mklang-activator.php',
    'includes/class-mklang-api.php',
    'includes/class-mklang-menu.php',
    'includes/class-mklang-ajax.php',
    'includes/class-mklang-frontend.php' // <-- تمت الإضافة: ملف الواجهة الأمامية
);

foreach ( $files as $file ) {
    if ( file_exists( MKLANG_PLUGIN_DIR . $file ) ) {
        require_once MKLANG_PLUGIN_DIR . $file;
    }
}

// 3. دوال التفعيل
function activate_mklang() {
    if ( class_exists( 'MKLang_Activator' ) ) {
        MKLang_Activator::activate();
    }
    // وضع علامة أن المعالج لم يكتمل بعد عند التفعيل لأول مرة
    if ( ! get_option( 'mklang_setup_complete' ) ) {
        update_option( 'mklang_setup_complete', false );
    }
}
register_activation_hook( __FILE__, 'activate_mklang' );

// 4. تشغيل الكلاسات (Backend)
if ( class_exists( 'MKLang_Menu' ) ) {
    $mklang_menu = new MKLang_Menu();
    add_action( 'admin_menu', array( $mklang_menu, 'add_plugin_menu' ) );
}

if ( class_exists( 'MKLang_Ajax' ) ) {
    new MKLang_Ajax();
}

// 5. تحميل ملفات التنسيق والجافا سكريبت
function mklang_enqueue_assets( $hook ) {
    // تحميل فقط في صفحات MKLang لتجنب التعارض
    if ( strpos( $hook, 'mklang' ) === false ) {
        return;
    }

    // CSS العام للوحة التحكم
    wp_enqueue_style( 'mklang-admin-css', MKLANG_PLUGIN_URL . 'admin/css/mklang-admin.css', array(), MKLANG_VERSION );

    // CSS/JS خاص بالمعالج (Wizard)
    if ( strpos( $hook, 'mklang-setup' ) !== false ) {
        wp_enqueue_style( 'mklang-wizard-css', MKLANG_PLUGIN_URL . 'admin/css/mklang-wizard.css', array(), MKLANG_VERSION );
        wp_enqueue_script( 'mklang-wizard-js', MKLANG_PLUGIN_URL . 'admin/js/mklang-wizard.js', array('jquery'), MKLANG_VERSION, true );
    } else {
        // JS العام لباقي الصفحات
        wp_enqueue_script( 'mklang-admin-js', MKLANG_PLUGIN_URL . 'admin/js/mklang-admin.js', array('jquery'), MKLANG_VERSION, true );
    }

    // تمرير البيانات لملفات JS العامة
    wp_localize_script( 'mklang-admin-js', 'mklang_obj', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'mklang_nonce' )
    ));
    
    // تمرير البيانات لملف المعالج
    wp_localize_script( 'mklang-wizard-js', 'mklang_wizard_obj', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'mklang_wizard_nonce' ),
        'redirect' => admin_url( 'admin.php?page=mklang-manager' )
    ));
}
add_action( 'admin_enqueue_scripts', 'mklang_enqueue_assets' );

// 6. دالة مساعدة لجلب اللغات المتاحة (ISO Codes)
function mklang_get_available_languages() {
    return array(
        'ar' => 'العربية (Arabic)',
        'en' => 'English',
        'fr' => 'Français (French)',
        'de' => 'Deutsch (German)',
        'es' => 'Español (Spanish)',
        'it' => 'Italiano (Italian)',
        'pt' => 'Português (Portuguese)',
        'ru' => 'Русский (Russian)',
        'zh' => '中文 (Chinese)',
        'ja' => '日本語 (Japanese)',
        'tr' => 'Türkçe (Turkish)',
        'hi' => 'हिन्दी (Hindi)'
    );
}

// 7. تشغيل الواجهة الأمامية (Frontend Switcher)
if ( class_exists( 'MKLang_Frontend' ) ) {
    new MKLang_Frontend();
}
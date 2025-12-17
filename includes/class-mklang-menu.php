<?php

class MKLang_Menu {

    public function add_plugin_menu() {
        // القائمة الرئيسية
        add_menu_page(
            'MKLang',
            'MKLang AI',
            'manage_options',
            'mklang-settings',
            array( $this, 'display_settings_page' ),
            'dashicons-translation',
            99
        );

        // صفحة مدير الترجمة
        add_submenu_page(
            'mklang-settings',
            'مدير الترجمة',
            'مدير الترجمة',
            'manage_options',
            'mklang-manager',
            array( $this, 'display_manager_page' )
        );

        // صفحة الإعدادات
        add_submenu_page(
            'mklang-settings',
            'الإعدادات',
            'الإعدادات',
            'manage_options',
            'mklang-settings',
            array( $this, 'display_settings_page' )
        );

        // === صفحة المعالج (Wizard) - مخفية ===
        add_submenu_page(
            null, // null يعني أنها مخفية من القائمة
            'MKLang Setup',
            'Setup',
            'manage_options',
            'mklang-setup',
            array( $this, 'display_wizard_page' )
        );
    }

    public function display_settings_page() {
        if ( file_exists( MKLANG_PLUGIN_DIR . 'admin/views/settings-page.php' ) ) {
            require_once MKLANG_PLUGIN_DIR . 'admin/views/settings-page.php';
        }
    }

    public function display_manager_page() {
        // تحويل المستخدم للمعالج إذا لم يكمله
        if ( get_option( 'mklang_license_status' ) === 'active' && ! get_option( 'mklang_setup_complete' ) ) {
            echo '<script>window.location.href = "' . admin_url('admin.php?page=mklang-setup') . '";</script>';
            exit;
        }

        if ( file_exists( MKLANG_PLUGIN_DIR . 'admin/views/manager-page.php' ) ) {
            require_once MKLANG_PLUGIN_DIR . 'admin/views/manager-page.php';
        }
    }

    public function display_wizard_page() {
        if ( file_exists( MKLANG_PLUGIN_DIR . 'admin/views/setup-wizard.php' ) ) {
            require_once MKLANG_PLUGIN_DIR . 'admin/views/setup-wizard.php';
        }
    }
}
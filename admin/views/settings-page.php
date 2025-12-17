<?php
// admin/views/settings-page.php

$message = '';
$msg_type = '';

// 1. معالجة حفظ الترخيص
if ( isset( $_POST['submit_license'] ) ) {
    check_admin_referer( 'mklang_save_settings', 'mklang_nonce' );
    $input_key = sanitize_text_field( $_POST['mklang_license'] );
    $response = MKLang_API::send_request( 'activate', array( 'license_key' => $input_key ) );

    if ( isset( $response['status'] ) && $response['status'] === 'success' ) {
        update_option( 'mklang_license_key', $input_key );
        update_option( 'mklang_license_status', 'active' );
        update_option( 'mklang_credits', $response['credits'] );
        $message = 'تم التفعيل بنجاح!';
        $msg_type = 'updated';
    } else {
        $message = 'خطأ: ' . ( isset( $response['message'] ) ? $response['message'] : 'غير معروف' );
        $msg_type = 'error';
    }
}

// 2. معالجة حفظ الإعدادات العامة واللغات
if ( isset( $_POST['save_settings'] ) ) {
    check_admin_referer( 'mklang_save_settings', 'mklang_nonce' );

    // اللغات
    if ( isset( $_POST['default_lang'] ) ) {
        update_option( 'mklang_default_lang', sanitize_text_field( $_POST['default_lang'] ) );
    }
    
    $active_langs = ( isset( $_POST['active_langs'] ) && is_array( $_POST['active_langs'] ) ) 
        ? array_map( 'sanitize_text_field', $_POST['active_langs'] ) 
        : array();
    update_option( 'mklang_active_langs', $active_langs );

    // الإعدادات الجديدة
    update_option( 'mklang_post_status', sanitize_text_field( $_POST['mklang_post_status'] ) );
    update_option( 'mklang_show_switcher', isset( $_POST['mklang_show_switcher'] ) ? 'yes' : 'no' );
    update_option( 'mklang_switcher_loc', sanitize_text_field( $_POST['mklang_switcher_loc'] ) );

    // تحديث القوائم
    if ( function_exists( 'mklang_register_nav_menus' ) ) {
        mklang_register_nav_menus();
    }

    $message = 'تم حفظ الإعدادات بنجاح.';
    $msg_type = 'updated';
}

$status = get_option( 'mklang_license_status' );
$credits = get_option( 'mklang_credits', '0.00' );
$default_lang = get_option( 'mklang_default_lang', 'ar' );
$active_langs = get_option( 'mklang_active_langs', array() );
$post_status = get_option( 'mklang_post_status', 'draft' );
$show_switcher = get_option( 'mklang_show_switcher', 'yes' );
$switcher_loc = get_option( 'mklang_switcher_loc', 'shortcode' );

$all_languages = mklang_get_available_languages();
?>

<div class="wrap mklang-wrap">
    <h1>⚙️ إعدادات MKLang AI</h1>
    
    <?php if ( ! empty( $message ) ): ?>
        <div class="notice <?php echo $msg_type; ?> is-dismissible"><p><?php echo $message; ?></p></div>
    <?php endif; ?>

    <div class="mklang-card">
        <?php if ( $status === 'active' ): ?>
            <div style="background:#f9f9f9; padding:15px; border:1px solid #ddd; border-radius:5px; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center;">
                <div><strong>حالة الترخيص:</strong> <span style="color:green;">نشط ✅</span></div>
                <div><strong>الرصيد المتبقي:</strong> <span style="font-size:20px; color:#46b450; font-weight:bold;">$<?php echo number_format( floatval( $credits ), 2 ); ?></span></div>
            </div>

            <form method="post" action="">
                <?php wp_nonce_field( 'mklang_save_settings', 'mklang_nonce' ); ?>
                
                <h2 class="title">🌐 اللغات</h2>
                <table class="form-table">
                    <tr>
                        <th>اللغة الأصلية</th>
                        <td>
                            <select name="default_lang" id="default_lang">
                                <?php foreach( $all_languages as $code => $name ): ?>
                                    <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $default_lang, $code ); ?>>
                                        <?php echo esc_html( $name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>لغات الترجمة</th>
                        <td>
                            <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap:10px;">
                                <?php foreach( $all_languages as $code => $name ): ?>
                                    <?php $disabled = ( $code === $default_lang ); ?>
                                    <label style="background:#fff; border:1px solid #ddd; padding:5px 10px; display:flex; align-items:center; <?php echo $disabled ? 'opacity:0.5' : ''; ?>">
                                        <input type="checkbox" name="active_langs[]" value="<?php echo $code; ?>" 
                                               <?php checked( in_array( $code, $active_langs ) ); ?> 
                                               <?php disabled( $disabled ); ?>>
                                        <span style="margin-right:5px;"><?php echo $name; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                </table>

                <hr>

                <h2 class="title">🛠 إعدادات العرض والنشر</h2>
                <table class="form-table">
                    <tr>
                        <th>مبدل اللغات (Switcher)</th>
                        <td>
                            <label>
                                <input type="checkbox" name="mklang_show_switcher" value="yes" 
                                       <?php checked( $show_switcher, 'yes' ); ?>>
                                تفعيل مبدل اللغات في الموقع
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>مكان الظهور</th>
                        <td>
                            <select name="mklang_switcher_loc">
                                <option value="shortcode" <?php selected( $switcher_loc, 'shortcode' ); ?>>
                                    استخدام Shortcode فقط [mklang_switcher]
                                </option>
                                <option value="menu" <?php selected( $switcher_loc, 'menu' ); ?>>
                                    إضافة تلقائية للقائمة الرئيسية (Main Menu)
                                </option>
                            </select>
                            <p class="description">إذا اخترت "القائمة الرئيسية"، سيتم إضافة المبدل كآخر عنصر في القائمة.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>حالة المقال المترجم</th>
                        <td>
                            <select name="mklang_post_status">
                                <option value="draft" <?php selected( $post_status, 'draft' ); ?>>
                                    مسودة (Draft) - للمراجعة
                                </option>
                                <option value="publish" <?php selected( $post_status, 'publish' ); ?>>
                                    نشر فوراً (Publish)
                                </option>
                            </select>
                            <p class="description">هل تريد نشر الترجمة مباشرة أم حفظها كمسودة للمراجعة؟</p>
                        </td>
                    </tr>
                </table>

                <hr>

                <div style="background: #e7f3ff; border-left: 4px solid #0073aa; padding: 15px; margin: 20px 0;">
                    <h3 style="margin-top: 0;">ℹ️ معلومات التسعير</h3>
                    <p style="margin-bottom: 0;">
                        <strong>ملحوظة:</strong> الأسعار يتم تحديدها من خلال السيرفر الرئيسي ولا يمكن تعديلها من هنا.<br>
                        التكلفة المعروضة في صفحة الترجمة هي التكلفة النهائية التي سيتم خصمها من رصيدك.
                    </p>
                </div>

                <p class="submit">
                    <input type="submit" name="save_settings" class="button button-primary" value="حفظ التغييرات">
                </p>
            </form>

        <?php else: ?>
            <form method="post" action="">
                <?php wp_nonce_field( 'mklang_save_settings', 'mklang_nonce' ); ?>
                <h2>تفعيل الإضافة</h2>
                <p>أدخل مفتاح الترخيص الخاص بك لتفعيل الإضافة:</p>
                <p>
                    <input type="text" name="mklang_license" class="regular-text" 
                           placeholder="أدخل مفتاح الترخيص هنا" required>
                </p>
                <p>
                    <input type="submit" name="submit_license" class="button button-primary" value="تفعيل الآن">
                </p>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($){
    // منع اختيار اللغة الأصلية في لغات الترجمة
    $('#default_lang').change(function(){
        var val = $(this).val();
        $('input[name="active_langs[]"]').prop('disabled', false).parent().css('opacity', '1');
        $('input[name="active_langs[]"][value="'+val+'"]')
            .prop('checked', false)
            .prop('disabled', true)
            .parent().css('opacity', '0.5');
    }).trigger('change');
});
</script>
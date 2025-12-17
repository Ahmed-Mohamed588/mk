<?php
// جلب قائمة اللغات
$languages = mklang_get_available_languages();
?>

<div class="mklang-wizard-wrapper">
    <div class="wizard-header">
        <h1>إعداد MKLang AI</h1>
        <p>قم بضبط لغات موقعك في خطوات بسيطة</p>
    </div>

    <div class="wizard-steps">
        <div class="step active" data-step="1">1. لغة الموقع الحالية</div>
        <div class="step" data-step="2">2. لغات الترجمة</div>
        <div class="step" data-step="3">3. جاهز</div>
    </div>

    <form id="mklang-wizard-form">
        
        <div class="wizard-content active" id="step-content-1">
            <h2>ما هي اللغة الحالية لمحتوى موقعك؟</h2>
            <p>اختر اللغة التي كُتبت بها مقالاتك ومنتجاتك حالياً.</p>
            
            <select name="default_lang" id="default_lang" class="wizard-select">
                <?php foreach($languages as $code => $name): ?>
                    <option value="<?php echo esc_attr($code); ?>" <?php selected('ar', $code); // افتراضي عربي ?>>
                        <?php echo esc_html($name); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div class="wizard-actions">
                <button type="button" class="button button-primary next-step" data-next="2">التالي &raquo;</button>
            </div>
        </div>

        <div class="wizard-content" id="step-content-2">
            <h2>إلى أي لغات تريد الترجمة؟</h2>
            <p>يمكنك اختيار لغة واحدة أو أكثر. سيتم إضافة هذه اللغات للنظام.</p>
            
            <div class="languages-grid">
                <?php foreach($languages as $code => $name): ?>
                    <label class="lang-card">
                        <input type="checkbox" name="active_langs[]" value="<?php echo esc_attr($code); ?>">
                        <span class="lang-name"><?php echo esc_html($name); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="wizard-actions">
                <button type="button" class="button prev-step" data-prev="1">&laquo; السابق</button>
                <button type="button" class="button button-primary next-step" data-next="3">التالي &raquo;</button>
            </div>
        </div>

        <div class="wizard-content" id="step-content-3">
            <div style="text-align: center; padding: 40px 0;">
                <span class="dashicons dashicons-yes" style="font-size: 80px; width: 80px; height: 80px; color: #46b450;"></span>
                <h2>كل شيء جاهز!</h2>
                <p>تم حفظ إعداداتك بنجاح. يمكنك الآن البدء في ترجمة المحتوى.</p>
                
                <button type="button" id="finish-wizard" class="button button-primary button-hero">البدء في الترجمة 🚀</button>
            </div>
        </div>

    </form>
</div>
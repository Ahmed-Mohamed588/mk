jQuery(document).ready(function($) {
    
    // التنقل بين الخطوات
    $('.next-step').click(function() {
        var nextStep = $(this).data('next');
        
        // التحقق في الخطوة 2 (يجب اختيار لغة واحدة على الأقل)
        if (nextStep === 3) {
            if ($('input[name="active_langs[]"]:checked').length === 0) {
                alert('الرجاء اختيار لغة واحدة على الأقل للترجمة.');
                return;
            }
            // حفظ البيانات عند الوصول للخطوة الأخيرة
            saveWizardData();
        }

        gotoStep(nextStep);
    });

    $('.prev-step').click(function() {
        var prevStep = $(this).data('prev');
        gotoStep(prevStep);
    });

    function gotoStep(step) {
        $('.wizard-content').removeClass('active');
        $('#step-content-' + step).addClass('active');
        
        $('.step').removeClass('active');
        $('.step[data-step="' + step + '"]').addClass('active');
    }

    // زر الإنهاء
    $('#finish-wizard').click(function() {
        window.location.href = mklang_wizard_obj.redirect;
    });

    // دالة الحفظ
    function saveWizardData() {
        var formData = $('#mklang-wizard-form').serialize();
        
        $.ajax({
            url: mklang_wizard_obj.ajax_url,
            type: 'POST',
            data: formData + '&action=mklang_save_wizard&nonce=' + mklang_wizard_obj.nonce,
            success: function(response) {
                if (!response.success) {
                    alert('حدث خطأ أثناء الحفظ');
                }
            }
        });
    }

    // منع اختيار اللغة الأصلية في لغات الترجمة (UI Logic)
    $('#default_lang').change(function() {
        var current = $(this).val();
        $('input[name="active_langs[]"]').prop('disabled', false).parent().css('opacity', '1');
        
        $('input[name="active_langs[]"][value="' + current + '"]')
            .prop('checked', false)
            .prop('disabled', true)
            .parent().css('opacity', '0.5');
    }).trigger('change');

});
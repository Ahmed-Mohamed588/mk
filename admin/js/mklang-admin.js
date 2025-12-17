jQuery(document).ready(function($) {
    
    // ----------------------------------------------------------------
    // 1. تحديد الكل والتعامل مع أزرار الـ Bulk Action
    // ----------------------------------------------------------------
    $('#cb-select-all-1').click(function() {
        var checked = this.checked;
        $('.mklang-checkbox').each(function() {
            this.checked = checked;
        });
        toggleBulkButton();
    });

    $('.mklang-checkbox').change(function() {
        toggleBulkButton();
    });

    function toggleBulkButton() {
        var count = $('.mklang-checkbox:checked').length;
        var btn = $('#mklang-bulk-translate-btn');
        if (count > 0) {
            btn.prop('disabled', false).text('ترجمة المحدد (' + count + ')');
        } else {
            btn.prop('disabled', true).text('ترجمة المحدد (Bulk)');
        }
    }

    // ----------------------------------------------------------------
    // 2. الترجمة الفردية (Single Action)
    // ----------------------------------------------------------------
    $('.mklang-translate-btn').click(function(e) {
        e.preventDefault();
        var btn = $(this);
        var postId = btn.data('id');
        translateItem(postId, btn);
    });

    // ----------------------------------------------------------------
    // 3. الترجمة الجماعية (Bulk Action - The Queue System)
    // ----------------------------------------------------------------
    $('#mklang-bulk-translate-btn').click(function(e) {
        e.preventDefault();
        
        var selectedIds = [];
        $('.mklang-checkbox:checked').each(function() {
            selectedIds.push($(this).val());
        });

        if (selectedIds.length === 0) return;

        if (!confirm('هل أنت متأكد من ترجمة ' + selectedIds.length + ' عنصر؟ سيتم خصم الرصيد بناءً على عدد الكلمات.')) {
            return;
        }

        // إظهار شريط التقدم
        $('#mklang-progress-area').slideDown();
        $('#mklang-bulk-translate-btn').prop('disabled', true);
        
        // بدء الطابور
        processQueue(selectedIds, 0);
    });

    // دالة الطابور (Recursive Function)
    function processQueue(ids, index) {
        var total = ids.length;
        
        // تحديث شريط التقدم
        var percent = Math.round((index / total) * 100);
        $('#mklang-progress-bar').css('width', percent + '%');
        $('#mklang-progress-text').text(index + ' / ' + total);

        // شرط التوقف
        if (index >= total) {
            $('#mklang-progress-bar').css('width', '100%').css('background', '#46b450'); // أخضر
            $('#mklang-current-item').text('✅ اكتملت العملية بنجاح!');
            $('#mklang-bulk-translate-btn').text('اكتملت العملية');
            alert('تم الانتهاء من ترجمة ' + total + ' عنصر بنجاح.');
            return;
        }

        var currentId = ids[index];
        var row = $('#post-' + currentId);
        
        // تعليم الصف الحالي بأنه قيد المعالجة
        row.addClass('processing');
        $('#mklang-current-item').text('جاري ترجمة: ' + row.find('strong').first().text() + '...');

        // استدعاء دالة الترجمة الفعلية
        // نمرر null للزر لأننا لا نملك زر محدد في الـ Bulk
        translateItem(currentId, null, function(success) {
            // عند الانتهاء (سواء نجاح أو فشل)، ننتقل للعنصر التالي
            row.removeClass('processing');
            processQueue(ids, index + 1);
        });
    }


    // ----------------------------------------------------------------
    // 4. دالة الترجمة الجوهرية (Core Translation Function)
    // ----------------------------------------------------------------
    function translateItem(postId, btnObject, callback) {
        var row = $('#post-' + postId);
        var statusCol = row.find('.col-status');
        var actionCol = row.find('.col-actions');
        var spinner = actionCol.find('.spinner');

        // تحديث واجهة المستخدم (Loading UI)
        spinner.addClass('is-active');
        if (btnObject) btnObject.prop('disabled', true).text('جارٍ...');

        $.ajax({
            url: mklang_obj.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'mklang_translate_post',
                post_id: postId,
                target_lang: 'en', // يمكن تغييره ليكون ديناميكياً
                nonce: mklang_obj.nonce
            },
            success: function(response) {
                spinner.removeClass('is-active');
                
                if (response.success) {
                    // نجاح
                    row.addClass('success');
                    statusCol.html('<span class="dashicons dashicons-yes" style="color:green;"></span> مترجم');
                    actionCol.html('<span style="color:green; font-weight:bold;">تم ($' + response.data.cost + ')</span>');
                    if (callback) callback(true);
                } else {
                    // فشل
                    row.addClass('error');
                    // عرض رسالة الخطأ الصغيرة تحت الحالة
                    statusCol.append('<div style="color:red; font-size:10px;">' + response.data + '</div>');
                    if (btnObject) btnObject.prop('disabled', false).text('إعادة المحاولة');
                    if (callback) callback(false);
                }
            },
            error: function(xhr, status, error) {
                spinner.removeClass('is-active');
                row.addClass('error');
                if (btnObject) btnObject.prop('disabled', false).text('خطأ شبكة');
                console.error(error);
                if (callback) callback(false);
            }
        });
    }

});
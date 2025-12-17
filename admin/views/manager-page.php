<?php
// admin/views/manager-page.php

$post_type = isset( $_GET['post_type_filter'] ) ? sanitize_text_field( $_GET['post_type_filter'] ) : 'post';
$paged = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;

// اللغات
$default_lang = get_option( 'mklang_default_lang', 'ar' );
$active_langs = get_option( 'mklang_active_langs', array() );

// استعلام
$args = array(
    'post_type' => $post_type,
    'post_status' => 'publish',
    'posts_per_page' => 20,
    'paged' => $paged,
    'meta_query' => array(
        array(
            'key' => '_mklang_original_id',
            'compare' => 'NOT EXISTS'
        )
    )
);

$query = new WP_Query( $args );
?>

<style>
.mklang-progress-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.7);
    z-index: 999999;
    align-items: center;
    justify-content: center;
}
.mklang-progress-modal.active {
    display: flex;
}
.mklang-progress-content {
    background: #fff;
    padding: 40px;
    border-radius: 10px;
    max-width: 500px;
    width: 90%;
    box-shadow: 0 10px 50px rgba(0,0,0,0.3);
}
.mklang-progress-content h2 {
    margin: 0 0 20px;
    color: #005f99;
}
.mklang-progress-bar-container {
    background: #f0f0f1;
    height: 30px;
    border-radius: 15px;
    overflow: hidden;
    margin: 20px 0;
}
.mklang-progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #005f99, #0073aa);
    transition: width 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-weight: bold;
}
.mklang-progress-status {
    text-align: center;
    color: #666;
    font-size: 14px;
}
.mklang-lang-badge {
    display: inline-block;
    padding: 3px 8px;
    background: #f0f0f1;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    margin-right: 3px;
}
</style>

<div class="wrap mklang-wrap">
    <h1 class="wp-heading-inline">مدير الترجمة الاحترافي</h1>
    
    <div class="tablenav top">
        <div class="alignleft actions">
            <form method="get">
                <input type="hidden" name="page" value="mklang-manager">
                <select name="post_type_filter" onchange="this.form.submit()">
                    <?php foreach( get_post_types(['public'=>true], 'objects') as $pt ): ?>
                        <option value="<?php echo $pt->name; ?>" <?php selected($post_type, $pt->name); ?>>
                            <?php echo $pt->label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <noscript><button class="button">تطبيق</button></noscript>
            </form>
        </div>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th width="40%">العنوان (<?php echo strtoupper($default_lang); ?>)</th>
                <th>نوع المحتوى</th>
                <?php foreach($active_langs as $lang): ?>
                    <th style="text-align:center;">
                        <span class="mklang-lang-badge"><?php echo strtoupper($lang); ?></span>
                    </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if($query->have_posts()): while($query->have_posts()): $query->the_post(); global $post; ?>
                <tr id="post-<?php echo $post->ID; ?>">
                    <td>
                        <strong><?php the_title(); ?></strong>
                        <div class="row-actions">
                            <a href="<?php echo get_edit_post_link(); ?>" target="_blank">تعديل الأصل</a> | 
                            <a href="<?php the_permalink(); ?>" target="_blank">عرض</a>
                        </div>
                    </td>
                    <td><?php echo $post->post_type; ?></td>
                    
                    <?php foreach($active_langs as $lang): 
                        global $wpdb;
                        $tbl = $wpdb->prefix . 'mklang_translations';
                        $trans_id = $wpdb->get_var( $wpdb->prepare(
                            "SELECT translated_id FROM $tbl WHERE original_id = %d AND lang_code = %s",
                            $post->ID, $lang
                        ));
                    ?>
                        <td style="text-align:center;">
                            <?php if($trans_id && get_post($trans_id)): ?>
                                <a href="<?php echo get_edit_post_link($trans_id); ?>" target="_blank" 
                                   class="button button-small" title="تعديل الترجمة">
                                    <span class="dashicons dashicons-edit"></span>
                                </a>
                            <?php else: ?>
                                <button class="button button-small button-primary mklang-add-trans" 
                                        data-id="<?php echo $post->ID; ?>" 
                                        data-lang="<?php echo $lang; ?>"
                                        data-title="<?php echo esc_attr(get_the_title()); ?>"
                                        title="ترجم إلى <?php echo strtoupper($lang); ?>">
                                    <span class="dashicons dashicons-translation"></span>
                                </button>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="<?php echo count($active_langs)+2; ?>">لا يوجد محتوى.</td></tr>
            <?php endif; wp_reset_postdata(); ?>
        </tbody>
    </table>

    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <?php 
            echo paginate_links([
                'total' => $query->max_num_pages, 
                'current' => $paged, 
                'base' => add_query_arg('paged', '%#%')
            ]); 
            ?>
        </div>
    </div>
</div>

<!-- Progress Modal -->
<div class="mklang-progress-modal" id="mklangProgressModal">
    <div class="mklang-progress-content">
        <h2>🌐 جاري الترجمة...</h2>
        <p id="mklangProgressTitle">يرجى الانتظار</p>
        
        <div class="mklang-progress-bar-container">
            <div class="mklang-progress-bar-fill" id="mklangProgressBar" style="width: 0%;">
                <span id="mklangProgressPercent">0%</span>
            </div>
        </div>
        
        <div class="mklang-progress-status" id="mklangProgressStatus">
            جاري الاتصال بالسيرفر...
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    
    var activeRequests = {}; // لتتبع الطلبات
    
    // ============================================================
    // عند الضغط على زر الترجمة
    // ============================================================
    $('.mklang-add-trans').click(function(e) {
        e.preventDefault();
        
        var btn = $(this);
        var postId = btn.data('id');
        var lang = btn.data('lang');
        var title = btn.data('title');
        
        // فتح الـ Modal
        $('#mklangProgressModal').addClass('active');
        $('#mklangProgressTitle').text('ترجمة: ' + title + ' → ' + lang.toUpperCase());
        $('#mklangProgressBar').css('width', '0%');
        $('#mklangProgressPercent').text('0%');
        $('#mklangProgressStatus').text('جاري الاتصال بالسيرفر...');
        
        // تعطيل الزر
        btn.prop('disabled', true);
        
        // بدء الترجمة
        startTranslation(postId, lang, btn, title);
    });
    
    // ============================================================
    // بدء الترجمة
    // ============================================================
    function startTranslation(postId, lang, btn, title) {
        
        // Update progress: 10%
        updateProgress(10, 'إرسال الطلب للسيرفر...');
        
        $.ajax({
            url: mklang_obj.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'mklang_translate_post',
                post_id: postId,
                target_lang: lang,
                nonce: mklang_obj.nonce
            },
            success: function(response) {
                if (response.success && response.data.request_id) {
                    var requestId = response.data.request_id;
                    
                    // Update progress: 20%
                    updateProgress(20, 'تم إرسال الطلب، جاري الترجمة...');
                    
                    // بدء المراقبة
                    activeRequests[requestId] = {
                        postId: postId,
                        lang: lang,
                        btn: btn,
                        title: title,
                        startTime: Date.now()
                    };
                    
                    pollStatus(requestId);
                    
                } else {
                    showError('فشل بدء الترجمة: ' + (response.data || 'خطأ غير معروف'));
                    btn.prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                showError('خطأ في الاتصال: ' + error);
                btn.prop('disabled', false);
            }
        });
    }
    
    // ============================================================
    // مراقبة حالة الترجمة (Polling)
    // ============================================================
    function pollStatus(requestId) {
        var request = activeRequests[requestId];
        if (!request) return;
        
        var attempts = 0;
        var maxAttempts = 200; // 10 دقائق
        
        var pollInterval = setInterval(function() {
            attempts++;
            
            // حساب الوقت المنقضي
            var elapsed = Math.floor((Date.now() - request.startTime) / 1000);
            
            // Update progress: 20% -> 90% بناءً على الوقت
            var progress = Math.min(20 + (attempts * 0.35), 90);
            updateProgress(progress, 'جاري الترجمة... (' + elapsed + 's)');
            
            $.ajax({
                url: mklang_obj.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'mklang_check_translation_status',
                    request_id: requestId,
                    nonce: mklang_obj.nonce
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.status === 'completed') {
                            // خلصت! 🎉
                            clearInterval(pollInterval);
                            delete activeRequests[requestId];
                            
                            updateProgress(100, '✅ تمت الترجمة بنجاح!');
                            
                            setTimeout(function() {
                                $('#mklangProgressModal').removeClass('active');
                                location.reload(); // إعادة تحميل الصفحة
                            }, 1500);
                            
                        } else if (response.data.status === 'pending') {
                            // لسه بيشتغل
                            console.log('Translation in progress...');
                        }
                    } else {
                        // خطأ
                        clearInterval(pollInterval);
                        delete activeRequests[requestId];
                        showError(response.data || 'حدث خطأ أثناء الترجمة');
                        request.btn.prop('disabled', false);
                    }
                },
                error: function() {
                    console.log('Polling error, retrying...');
                }
            });
            
            // التحقق من الحد الأقصى
            if (attempts >= maxAttempts) {
                clearInterval(pollInterval);
                delete activeRequests[requestId];
                showError('انتهت مهلة الانتظار (10 دقائق). يرجى التحقق من السيرفر.');
                request.btn.prop('disabled', false);
            }
            
        }, 3000); // كل 3 ثواني
    }
    
    // ============================================================
    // تحديث شريط التقدم
    // ============================================================
    function updateProgress(percent, message) {
        $('#mklangProgressBar').css('width', percent + '%');
        $('#mklangProgressPercent').text(Math.round(percent) + '%');
        $('#mklangProgressStatus').text(message);
    }
    
    // ============================================================
    // عرض الخطأ
    // ============================================================
    function showError(message) {
        updateProgress(0, '❌ ' + message);
        $('#mklangProgressBar').css('background', 'linear-gradient(90deg, #dc3232, #ff4444)');
        
        setTimeout(function() {
            $('#mklangProgressModal').removeClass('active');
            $('#mklangProgressBar').css('background', 'linear-gradient(90deg, #005f99, #0073aa)');
        }, 3000);
    }
    
    // إغلاق الـ Modal عند الضغط خارجه
    $('#mklangProgressModal').click(function(e) {
        if (e.target === this) {
            // لا تغلق إذا كانت هناك ترجمة نشطة
            if (Object.keys(activeRequests).length === 0) {
                $(this).removeClass('active');
            }
        }
    });
});
</script>
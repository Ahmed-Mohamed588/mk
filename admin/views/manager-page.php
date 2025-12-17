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
    // نستبعد المقالات المترجمة بالفعل (عايزين نظهر الأصول بس)
    'meta_query' => array(
        array(
            'key' => '_mklang_original_id',
            'compare' => 'NOT EXISTS' // هاتلي بس المقالات اللي ملهاش أصل (يعني هي الأصل)
        )
    )
);
// فلترة إضافية: هات المقالات اللي لغتها هي اللغة الأصلية (أو غير محددة)
$query = new WP_Query( $args );
?>

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
                        <img src="<?php echo MKLANG_PLUGIN_URL . 'assets/flags/' . $lang . '.png'; ?>" 
                             onerror="this.style.display='none';this.nextSibling.style.display='inline'" 
                             style="width:20px;"> 
                        <span style="display:none"><?php echo strtoupper($lang); ?></span>
                    </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if($query->have_posts()): while($query->have_posts()): $query->the_post(); global $post; ?>
                <tr>
                    <td>
                        <strong><?php the_title(); ?></strong>
                        <div class="row-actions">
                            <a href="<?php echo get_edit_post_link(); ?>" target="_blank">تعديل الأصل</a> | 
                            <a href="<?php the_permalink(); ?>" target="_blank">عرض</a>
                        </div>
                    </td>
                    <td><?php echo $post->post_type; ?></td>
                    
                    <?php foreach($active_langs as $lang): 
                        // هل توجد ترجمة؟
                        global $wpdb;
                        $tbl = $wpdb->prefix . 'mklang_translations';
                        $trans_id = $wpdb->get_var( $wpdb->prepare(
                            "SELECT translated_id FROM $tbl WHERE original_id = %d AND lang_code = %s",
                            $post->ID, $lang
                        ));
                    ?>
                        <td style="text-align:center;">
                            <?php if($trans_id && get_post($trans_id)): ?>
                                <a href="<?php echo get_edit_post_link($trans_id); ?>" target="_blank" class="button button-small" title="تعديل الترجمة">
                                    <span class="dashicons dashicons-edit" style="font-size:14px; padding-top:3px;"></span>
                                </a>
                            <?php else: ?>
                                <button class="button button-small button-primary mklang-add-trans" 
                                        data-id="<?php echo $post->ID; ?>" 
                                        data-lang="<?php echo $lang; ?>"
                                        title="ترجم إلى <?php echo strtoupper($lang); ?>">
                                    <span class="dashicons dashicons-plus" style="font-size:14px; padding-top:3px;"></span>
                                </button>
                                <span class="spinner" style="float:none;"></span>
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
            <?php echo paginate_links(['total' => $query->max_num_pages, 'current' => $paged, 'base' => add_query_arg('paged', '%#%')]); ?>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('.mklang-add-trans').click(function(e) {
        e.preventDefault();
        var btn = $(this);
        var postId = btn.data('id');
        var lang = btn.data('lang');
        var spinner = btn.next('.spinner');

        btn.prop('disabled', true);
        spinner.addClass('is-active');

        $.ajax({
            url: mklang_obj.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'mklang_translate_post',
                post_id: postId,
                target_lang: lang, // إرسال اللغة المحددة
                nonce: mklang_obj.nonce
            },
            success: function(res) {
                spinner.removeClass('is-active');
                if(res.success) {
                    // تحويل الزر (+) إلى قلم تعديل
                    // سنقوم بإعادة تحميل الصفحة أو استبدال الزر برابط التعديل
                    // للأناقة: سنضع علامة صح ثم نحوله لرابط
                    btn.replaceWith('<span class="dashicons dashicons-yes" style="color:green"></span>');
                    setTimeout(function(){
                         location.reload(); // تحديث الصفحة لرؤية رابط التعديل
                    }, 1000);
                } else {
                    alert('خطأ: ' + res.data);
                    btn.prop('disabled', false);
                }
            },
            error: function() {
                spinner.removeClass('is-active');
                alert('خطأ في الاتصال');
                btn.prop('disabled', false);
            }
        });
    });
});
</script>
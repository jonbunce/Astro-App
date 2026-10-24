/* Adult Teams admin — Media Library picker for badge / crest fields */
jQuery(function ($) {
    var frame;
    $(document).on('click', '.aat-pick', function (e) {
        e.preventDefault();
        var target = $(this).data('target');
        var $input = $('#' + target);
        var $prev  = $('img[data-preview="' + target + '"]');
        if (!window.wp || !wp.media) { return; }
        frame = wp.media({
            title: 'Choose an image',
            button: { text: 'Use this image' },
            library: { type: 'image' },
            multiple: false
        });
        frame.on('select', function () {
            var att = frame.state().get('selection').first().toJSON();
            var url = (att.sizes && att.sizes.medium) ? att.sizes.medium.url : att.url;
            $input.val(url).trigger('change');
            $prev.attr('src', url).css('opacity', 1);
        });
        frame.open();
    });
    // Keep previews in sync when a URL is typed/cleared
    $(document).on('change', 'input[type=url]', function () {
        var $prev = $('img[data-preview="' + this.id + '"]');
        if (!$prev.length) { return; }
        if (this.value) { $prev.attr('src', this.value).css('opacity', 1); }
    });
});

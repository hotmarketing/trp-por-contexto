(function ($) {
    'use strict';

    var searchTimer = null;

    // Page search autocomplete.
    $('#trp-co-page-search').on('input', function () {
        var query = $(this).val();
        var $results = $('#trp-co-search-results');

        clearTimeout(searchTimer);

        if (query.length < 2) {
            $results.hide().empty();
            return;
        }

        searchTimer = setTimeout(function () {
            $.ajax({
                url: trpCoAdmin.ajaxUrl,
                data: {
                    action: 'trp_co_search_pages',
                    nonce: trpCoAdmin.nonce,
                    q: query
                },
                success: function (response) {
                    if (!response.success || !response.data.length) {
                        $results.hide().empty();
                        return;
                    }

                    // Construir con .text()/.attr(): el título de la página no debe interpretarse como HTML.
                    $results.empty();
                    response.data.forEach(function (item) {
                        $('<div class="trp-co-result-item"></div>')
                            .attr('data-id', parseInt(item.id, 10))
                            .text(item.title)
                            .appendTo($results);
                    });

                    $results.show();
                }
            });
        }, 300);
    });

    // Select a page from results.
    $(document).on('click', '.trp-co-result-item', function () {
        var id = $(this).data('id');
        var title = $(this).text();

        $('#trp-co-page-search').val(title);
        $('#trp-co-page-id').val(id);
        $('#trp-co-search-results').hide().empty();
    });

    // Close results when clicking outside.
    $(document).on('click', function (e) {
        if (!$(e.target).closest('#trp-co-page-search, #trp-co-search-results').length) {
            $('#trp-co-search-results').hide();
        }
    });

    // Toggle HTML source view.
    $(document).on('click', '.trp-co-toggle-source', function () {
        var $source = $(this).next('.trp-co-html-source');
        $source.toggle();
        $(this).text($source.is(':visible') ? 'Hide HTML' : 'Show HTML');
    });

    // Override type toggle — adjust form fields based on selected type.
    function updateOverrideTypeUI() {
        var type = $('input[name="override_type"]:checked').val();
        var $label = $('#trp-co-original-label');
        var $textarea = $('#trp-co-original');
        var $desc = $('#trp-co-original-desc');

        if (type === 'selector') {
            $label.text('Element ID');
            $textarea.attr('rows', '1').attr('placeholder', 'my-element-id');
            $desc.text('The HTML id attribute of the element (without the # symbol).');
        } else {
            $label.text('Original string (global translation)');
            $textarea.attr('rows', '4').attr('placeholder', '');
            $desc.text('Paste the translated string as it appears in the HTML (may include HTML tags).');
        }
    }

    $('input[name="override_type"]').on('change', updateOverrideTypeUI);
    updateOverrideTypeUI();

})(jQuery);

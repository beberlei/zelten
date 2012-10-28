$(document).ready(function() {
    var win = $(window);

    win.scroll(function () {
        if (win.height() + win.scrollTop() == $(document).height()) {
            win.trigger('scroll-bottom');
        }
    });

    $('.show-popover').popover({
        placement: 'bottom',
        trigger: 'hover'
    });

    win.bind('scroll-bottom', function() {
        $.ajax({
            url: ZeltenMessages.url + '?criteria[before_id]=' + ZeltenMessages.last.id + '&criteria[before_id_entity]=' + ZeltenMessages.last.entity,
            success: function(data) {
                var newEntries = $(data);
                newEntries.find('.stream-message').each(function() {
                    var el = $(this);
                    ZeltenMessages.last = {
                        id: el.data('message-id'),
                        entity: el.data('entity')
                    };
                });

                $("#stream").append(newEntries);
            }
        });
    });
});

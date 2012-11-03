define(["jquery", "backbone", "zelten/view/notificationcount"], function($, Backbone, NotificationCountView) {
    // Notification Count
    $(".notifications").each(function() {
        var view = new NotificationCountView({
            url: Zelten.ApplicationOptions.base + '/stream/notifications',
            el: $(this)
        });
        view.render();
    });
});

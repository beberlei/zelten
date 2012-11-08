define(["jquery", "backbone", "zelten/view/notificationcount", "zelten/view/userlink"], function($, Backbone, NotificationCountView, UserLinkView) {

    $(".user-details").each(function() {
        var userLink = new UserLinkView({
            el: $(this)
        });
        userLink.render();
    });

});


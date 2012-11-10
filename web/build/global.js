define(["zelten/view/notificationcount", "zelten/view/user_profile"], function (NotificationCountView, UserProfile) {

    $(document).ready(function() {
        var view = new NotificationCountView({
            url: Zelten.ApplicationOptions.base + '/stream/notifications/count',
            el: $("#notifications")
        });
        view.render();

        var userProfile = new UserProfile({
            el: $(".current-profile")
        });
        userProfile.render();

    });
});


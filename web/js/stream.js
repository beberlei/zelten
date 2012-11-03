define(
    ["zelten/view/stream", "zelten/collection/message", "zelten/collection/follower", "zelten/collection/following", "zelten/view/notificationcount"],
    function (StreamView, MessageCollection, FollowerCollection, FollowingCollection, NotificationCountView) {

    $(document).ready(function() {
        var followers = new FollowerCollection();
        var following = new FollowingCollection();

        var app = new StreamView({
            mentionedEntity: $("#stream").data('mentioned-entity'),
            url: Zelten.ApplicationOptions.base + '/stream/',
            el: $("#stream"),
            collection: new MessageCollection(),
            followers: followers,
            following: following
        });
        app.render();

        $(".notifications").each(function() {
            var view = new NotificationCountView({
                url: Zelten.ApplicationOptions.base + '/stream/notifications/count',
                el: $(this)
            });
            view.render();
        });
    });
});

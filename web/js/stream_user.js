define(
    ["zelten/view/stream", "zelten/collection/message", "zelten/collection/follower", "zelten/collection/following", "zelten/view/userlist", "zelten/view/notificationcount"],
    function (StreamView, MessageCollection, FollowerCollection, FollowingCollection, UserListView, NotificationCountView) {

    $(document).ready(function() {
        var followers = new FollowerCollection();
        var following = new FollowingCollection();

        var entity = $("#stream").data('entity');

        var app = new StreamView({
            entity: entity,
            url: Zelten.ApplicationOptions.base + '/stream/',
            el: $("#stream"),
            collection: new MessageCollection(),
            followers: followers,
            following: following
        });
        app.render();
        app.checkNewMessages();

        var following = new UserListView({
            url: Zelten.ApplicationOptions.base + '/profile/' + entity + '/following',
            el: $(".following")
        });
        following.render();

        var followers = new UserListView({
            url: Zelten.ApplicationOptions.base + '/profile/' + entity + '/followers',
            el: $(".follower")
        });
        followers.render();

        $(".notifications").each(function() {
            var view = new NotificationCountView({
                url: Zelten.ApplicationOptions.base + '/stream/notifications/count',
                el: $(this)
            });
            view.render();
        });
    });
});


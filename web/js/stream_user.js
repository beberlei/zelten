define(
    ["zelten/view/stream", "zelten/collection/message", "zelten/collection/follower", "zelten/collection/following", "zelten/view/userlist"],
    function (StreamView, MessageCollection, FollowerCollection, FollowingCollection, UserListView) {

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

    return app;
});


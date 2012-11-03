define(
    ["zelten/view/stream", "zelten/collection/message", "zelten/collection/follower", "zelten/collection/following"],
    function (StreamView, MessageCollection, FollowerCollection, FollowingCollection) {

    $(document).ready(function() {
        var followers = new FollowerCollection();
        var following = new FollowingCollection();

        var app = new StreamView({
            mentionedEntity: $("#stream").data('mentioned-entity'),
            url: Zelten.ApplicationOptions.base + '/stream',
            el: $("#stream"),
            collection: new MessageCollection(),
            followers: followers,
            following: following
        });
        app.render();
    });
});

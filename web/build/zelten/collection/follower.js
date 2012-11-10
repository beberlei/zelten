define(["zelten/collection/user"], function(UserCollection) {
    var followerCollection = UserCollection.extend({
        path: '/profile/followers'
    });
    return followerCollection;
});

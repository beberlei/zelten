define(["zelten/collection/user"], function(UserCollection) {
    var followingCollection = UserCollection.extend({
        path: '/profile/following'
    });
    return followingCollection;
});

define(["zelten/model/user", "zelten/collection"], function(User, Collection) {

    var userCollection = Collection.extend({
        model: User,
        url: function() {
            return this.getBasePath() + this.path;
        },
        parse: function(resp, jxhr) {
            return resp.list;
        }
    });

    return userCollection;
});

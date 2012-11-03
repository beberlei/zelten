define(["backbone"], function(Backbone) {

    var messageCollection = Backbone.Collection.extend({
        comparator: function(message) {
            return message.get('published') * -1;
        }
    });

    return messageCollection;
});

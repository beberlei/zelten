define(["backbone"], function(Backbone) {

    var collection = Backbone.Collection.extend({
        getBasePath: function() {
            return (Zelten && Zelten.ApplicationOptions && Zelten.ApplicationOptions.base)
                ? Zelten.ApplicationOptions.base
                : '';
        }
    });

    return collection;
});

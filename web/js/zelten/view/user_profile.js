define(["jquery", "backbone", "bootstrap"], function() {
    var UserProfile = Backbone.View.extend({
        render: function() {
            this.$el.find(".avatar").popover({
                title: $("#profile-dialog").data('name'),
                content: $("#profile-dialog").html(),
                placement: 'left'
            });
        }
    });

    return UserProfile;
});

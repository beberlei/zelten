define(["jquery", "backbone", "zelten/view/userlink"], function($, Backbone, UserLinkView) {

    $(".user-details").each(function() {
        var userLink = new UserLinkView({
            el: $(this)
        });
        userLink.render();
    });
});


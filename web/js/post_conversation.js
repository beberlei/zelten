define(["zelten/view/conversation"], function(ConversationView) {
    $(document).ready(function() {
        var app = new ConversationView({
            el: $(".conversation")
        });
        app.render();
    });
});

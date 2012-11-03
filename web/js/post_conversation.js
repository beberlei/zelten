define(["zelten/view/conversation"], function(ConversationView) {
    var app = new ConversationView({
        el: $(".conversation")
    });
    app.render();
});

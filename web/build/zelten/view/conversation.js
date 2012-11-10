define(["backbone", "zelten/model/message", "zelten/view/message", "zelten/view/userlink"], function(Backbone, Message, MessageView, UserLinkView) {
    var conversationView = Backbone.View.extend({
        render: function() {
            var el = this.$el.find('.stream-message');
            var message = new Message({
                id: el.data('message-id'),
                entity: el.data('entity'),
                published: el.data('published'),
                element: el
            });
            var messageView = new MessageView({
                model: message,
                el: message.get('element')
            });
            messageView.render();

            // add all the other user details links
            this.$el.find('.others a.user-details').each(function() {
                var view = new UserLinkView({
                    el: $(this)
                });
                view.render();
            });
            this.$el.find('.others .timeago').timeago();
        }
    });


    return conversationView;
});

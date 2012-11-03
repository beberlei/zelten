define(
    ["backbone", "zelten/view/writestatus", "zelten/view/modaldialog", "zelten/view/userlink", "zelten/model/message", "timeago", "bootstrap"],
    function(Backbone, WriteStatusView, ModalConfirmDialogView, UserLinkView, Message) {

    var messageView = Backbone.View.extend({
        events: {
            "click a.show-conversation": "clickShowConversations",
            "click a.show-reply": "clickReply",
            "click a.repost": "clickRepost",
            "click a.more-content": "showMoreContent"
        },
        initialize: function(args) {
            this.messages = args.messages;
            this.replyToView = new WriteStatusView({
                mentions: this.$el.data('mentions'),
                collection: args.messages,
                el: this.$el.find(".stream-message-add-replyto")
            });
        },
        showMoreContent: function(e) {
            $(e.currentTarget).hide();
            this.$el.find('.hidden-content').slideDown();
            return false;
        },
        clickReply: function(e) {
            this.$el.find('.reply-form').toggle();
            return false;
        },
        clickRepost: function(e) {
            var modal = new ModalConfirmDialogView({
                params: {
                    title: 'Do you want to repost this message?',
                    post: this.$el.find('.message-body').html(),
                    label: 'Yes, repost!'
                },
                success: _.bind(this.confirmClickRepost, this, $(e.currentTarget).attr('href'))
            });
            modal.render();

            return false;
        },
        confirmClickRepost: function(url) {
            $.ajax({
                type: 'POST',
                url: url,
                success: _.bind(this.repostSuccess, this)
            });
            return false;
        },
        repostSuccess: function(data) {
            this.$el.find('a.repost').css('pointer-events', 'none');

            var newMessage = $(data);

            var message = new Message({
                id: newMessage.data('message-id'),
                entity: newMessage.data('entity'),
                published: newMessage.data('published'),
                element: newMessage
            });

            if (this.messages) {
                this.messages.add(newMessage);
            }
        },
        clickShowConversations: function(e) {
            var link = $(e.currentTarget);

            if (this.$el.find('.conversation-pane').is(':hidden')) {
                // dont use link here, because we want to disable ALL conversation links
                this.$el.find('a.show-conversation').attr('disabled', true).css('pointer-events', 'none');

                $.ajax({
                    url: link.attr('href'),
                    success: _.bind(this.showConversation, this)
                });

                this.$el.find('.conversation-pane').slideDown().addClass('loading');
            } else {
                this.$el.find('.conversation-pane').slideUp().removeClass('loading');
            }

            return false;
        },
        showConversation: function(data) {
            this.$el.find('.conversation-pane').removeClass('loading');
            this.$el.find('.conversations').html(data);
            var cnt = this.$el.find('.conversations .conversation-message').length;
            this.$el.find('a.show-conversation').filter('.btn').append(' ' + cnt);
            this.$el.find('a.show-conversation').attr('disabled', false).css('pointer-events', 'auto');

            this.$el.find('.conversations .timeago').timeago();
            this.$el.find('.conversations').each(function() {
                var view = new UserLinkView({
                    el: $(this)
                });
                view.render();
            });
        },
        render: function() {
            this.$el.find('.show-tooltip').tooltip({});
            this.$el.find('.show-popover').popover({
                placement: 'bottom',
                trigger: 'hover'
            });
            this.$el.find('.timeago').timeago();
            this.$el.find('a.user-details').each(function() {
                var view = new UserLinkView({
                    el: $(this)
                });
                view.render();
            });
            this.replyToView.render();
        }
    });

    return messageView;
});

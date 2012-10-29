var Zelten = {};

Zelten.MessageView = Backbone.View.extend({
    events: {
    },
    render: function() {
        this.$el.find('.show-tooltip').tooltip({

        });
        this.$el.find('.show-popover').popover({
            placement: 'bottom',
            trigger: 'hover'
        });
    }
});

Zelten.MessageStreamApplication = Backbone.View.extend({
    events: {
        'scroll-bottom': 'loadOlderPosts',
        'submit form.stream-add-post': 'writeMessage',
        'keyup form.stream-add-post .message': 'updateShareButton',
        'change form.stream-add-post .message': 'updateShareButton'
    },
    initialize: function() {
        this.title = document.title;
        this.newMessages = '';
        this.newMessagesCount = 0;
        this.win = $(window);
        this.win.scroll(_.bind(this.scrollCheck, this));
        setInterval(_.bind(this.checkNewMessages, this), 1000*60);
    },
    updateShareButton: function(e) {
        var msg = $(e.currentTarget).val();
        this.$el.find('.stream-add-post-btn').attr('disabled', (msg.length == 0));
    },
    writeMessage: function(e) {
        var form = $(e.currentTarget);
        var msg = form.find('.message').val();

        if (msg.length == 0) {
            return false;
        }

        form.find('.stream-add-post-btn').attr('disabled', true);

        $.ajax({
            url:  form.attr('action'),
            type: 'POST',
            data: form.serialize(),
            success: _.bind(this.writeMessageSuccess, this)
        });

        return false;
    },
    writeMessageSuccess: function(data) {
        var newMessage = $(data);
        var message = new Zelten.MessageView({
            el: newMessage
        });
        message.render();

        ZeltenMessages.first = {
            id: newMessage.data('message-id'),
            entity: newMessage.data('entity')
        };

        this.$el.find('.stream-messages').prepend(newMessage);
        this.$el.find('.stream-add-post-btn').attr('disabled', false);
        this.$el.find('.stream-add-post').each(function() {
            this.reset();
        });
    },
    checkNewMessages: function() {
        $.ajax({
            url: ZeltenMessages.url + '?criteria[since_id]=' + ZeltenMessages.first.id + '&criteria[since_id_entity]=' + ZeltenMessages.first.entity,
            success: _.bind(this.checkNewMessagesSuccess, this)
        });

    },
    checkNewMessagesSuccess: function(data) {
        var newEntries = $(data).find('.stream-messages');
        var done = false;
        var cnt = this.newMessagesCount;
        newEntries.find('.stream-message').each(function() {
            cnt++;
            var el = $(this);
            if (!done) {
                done = true;
                ZeltenMessages.first = {
                    id: el.data('message-id'),
                    entity: el.data('entity')
                };
            }

            var message = new Zelten.MessageView({
                el: el
            });
            message.render();
        });

        if (cnt > 0) {
            this.newMessages = newEntries.html() + this.newMessages;
            this.$el.find('.stream-notifications').html('<div class="alert new-messages">There are ' + cnt + ' new messages.</div>');
            this.$el.find('.new-messages').click(_.bind(this.showNewMessages, this));
            document.title = '(' + cnt + ') ' + this.title;
        }
    },
    showNewMessages: function() {
        this.$el.find('.new-messages').remove();
        this.$el.find('.stream-messages').prepend(this.newMessages);
        this.newMessages = '';
        this.newMessagesCount = 0;
        document.title = this.title;
    },
    scrollCheck: function () {
        if (this.win.height() + this.win.scrollTop() == $(document).height()) {
            this.$el.trigger('scroll-bottom');
        }
    },
    render: function() {
        this.$el.find('.stream-message').each(function() {
            var message = new Zelten.MessageView({
                el: $(this)
            });
            message.render();
        });
    },
    loadOlderPosts: function() {
        if (this.isLoadingOlderPosts) {
            return;
        }
        this.isLoadingOlderPosts = true;

        this.loading = $('<div class="loading"></div>');
        this.$el.find('.stream-messages').append(this.loading);

        $.ajax({
            url: ZeltenMessages.url + '?criteria[before_id]=' + ZeltenMessages.last.id + '&criteria[before_id_entity]=' + ZeltenMessages.last.entity,
            success: _.bind(this.loadOlderPostsSuccess, this)
        });
    },
    loadOlderPostsSuccess:function(data) {
        this.loading.remove();

        var newEntries = $(data).find('.stream-messages');
        var streamMessages = this.$el.find('.stream-messages');

        newEntries.find('.stream-message').each(function() {
            var el = $(this);
            ZeltenMessages.last = {
                id: el.data('message-id'),
                entity: el.data('entity')
            };

            var message = new Zelten.MessageView({
                el: el
            });
            message.render();
            streamMessages.append(el);
        });

        this.isLoadingOlderPosts = false;
    }
});

$(document).ready(function() {
    var app = new Zelten.MessageStreamApplication({
        el: $("#stream")
    });
    app.render();
});

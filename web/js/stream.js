var Zelten = {};

Zelten.WriteStatusView = Backbone.View.extend({
    events: {
        "click": "showActions",
        "keyup .message": "showActions",
        "change .message": "showActions",
        "click .stream-message-add-cancel": "cancelPosting",
        "submit": "writeMessage"
    },
    initialize: function(args) {
        this.messageList = args.messageList;
    },
    cancelPosting: function() {
        var actions = this.$el.find(".stream-message-add .actions");
        actions.slideUp();
    },
    writeMessage: function(e) {
        var form = $(e.currentTarget);
        var msg = form.find('.message').val();

        if (typeof(msg) == 'undefined' || msg.length == 0) {
            return false;
        }

        form.find('.stream-message-add-btn').attr('disabled', true);

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
            el: newMessage,
            messageList: this.messageList,
        });
        message.render();

        ZeltenMessages.first = {
            id: newMessage.data('message-id'),
            entity: newMessage.data('entity')
        };

        this.messageList.prepend(newMessage);
        this.$el.find('.stream-message-add-btn').attr('disabled', false);
        this.$el.each(function() {
            this.reset();
        });
    },
    showActions: function() {
        var actions = this.$el.find(".actions");
        if (actions.is(':hidden')) {
            actions.slideDown();
        }

        var msg = this.$el.find('.message').val();
        this.$el.find('.stream-message-add-btn').attr('disabled', (msg.length == 0));
    }
});

Zelten.MessageView = Backbone.View.extend({
    events: {
        "click a.show-conversation": "clickShowConversations"
    },
    initialize: function(args) {
        this.replyToView = new Zelten.WriteStatusView({
            messageList: args.messageList,
            el: this.$el.find(".stream-message-add-replyto")
        });
    },
    clickShowConversations: function(e) {
        var link = $(e.currentTarget);
        link.attr('disabled', true);
        $.ajax({
            url: link.attr('href'),
            success: _.bind(this.showConversation, this)
        });
        this.$el.find('.conversations').addClass('loading');

        return false;
    },
    showConversation: function(data) {
        this.$el.find('.conversations').removeClass('loading');
        this.$el.find('.conversations').html(data);
        var cnt = this.$el.find('.conversations .conversation-message').length;
        this.$el.find('a.show-conversation').append(cnt);
    },
    render: function() {
        this.$el.find('.stream-message-add-comment .message').autoResize({extraSpace: 0});
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
        'scroll-bottom': 'loadOlderPosts'
    },
    initialize: function() {
        this.title = document.title;
        this.newMessages = $("<div</div>");
        this.newMessagesCount = 0;
        this.win = $(window);
        this.win.scroll(_.bind(this.scrollCheck, this));
        setInterval(_.bind(this.checkNewMessages, this), 1000*60);
        this.postStatus = new Zelten.WriteStatusView({
            el: this.$el.find('.stream-add-message-box .stream-add-message'),
            messageList: this.$el.find('.stream-messages')
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
        var newMessages = $("<div></div>");
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
                messageList: this.$el.find('.stream-messages'),
                el: el
            });
            message.render();
            newMessages.append(el);
        });

        if (cnt > 0) {
            this.newMessages.prepend(newMessages);
            this.$el.find('.stream-notifications').html('<div class="alert new-messages">There are ' + cnt + ' new messages.</div>');
            this.$el.find('.new-messages').click(_.bind(this.showNewMessages, this));
            document.title = '(' + cnt + ') ' + this.title;
        }
    },
    showNewMessages: function() {
        this.$el.find('.new-messages').remove();
        this.$el.find('.stream-messages').prepend(this.newMessages);
        this.newMessages = $("<div></div>");
        this.newMessagesCount = 0;
        document.title = this.title;
    },
    scrollCheck: function () {
        if (this.win.height() + this.win.scrollTop() == $(document).height()) {
            this.$el.trigger('scroll-bottom');
        }
    },
    render: function() {
        var messageList = this.$el.find('.stream-messages');
        this.$el.find('.stream-add-post .message').autoResize({extraSpace: 10});
        this.$el.find('.stream-message').each(function() {
            var message = new Zelten.MessageView({
                messageList: messageList,
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
                messageList: this.$el.find('.stream-messages'),
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

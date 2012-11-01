var Zelten = Zelten || {};

Zelten.ModalConfirmDialogView = Backbone.View.extend({
    events: {
        "click .cancel": "cancelAction",
        "click .action-success": "successAction"
    },
    initialize: function(args) {
        this.success = args.success;
        this.params = args.params;
    },
    template: _.template($("#modal-confirm-dialog").html()),
    cancelAction: function() {
        this.$el.modal('hide');
        this.remove();
    },
    successAction: function() {
        this.success();
        this.cancelAction();
    },
    render: function() {
        var dialog = $(this.template(this.params));
        this.setElement(dialog);
        dialog.modal('show');
        $("body").append(dialog);
    }
});

Zelten.FollowerCollection = Backbone.Collection.extend({
});

Zelten.UserView = Backbone.View.extend({
    events: {
        "submit .follow": "followUser"
    },
    followUser: function(e) {
        var form = $(e.currentTarget);
        form.find('.btn').attr('disabled', true);
        $.ajax({
            data: form.serialize(),
            type: 'POST',
            url: form.attr('action'),
            success: _.bind(this.followUserSuccess, this)
        });
        return false;
    },
    followUserSuccess: function() {
        this.$el.find('form.follow .btn')
                .addClass('btn-danger')
                .removeClass('btn-success')
                .attr('value', 'Unfollow')
                .attr('disabled', true);
    }
});

Zelten.WriteStatusView = Backbone.View.extend({
    events: {
        "click": "showActions",
        "keyup .message": "showActions",
        "change .message": "showActions",
        "click .stream-message-add-cancel": "cancelPosting",
        "submit": "writeMessage"
    },
    initialize: function(args) {
        this.mentions = args.mentions || '';
        this.messageList = args.messageList;
        this.$el.find('textarea').autoResize({
            extraSpace: 10,
            animate: {duration: 50, complete: function() {}}
        });
    },
    cancelPosting: function() {
        var actions = this.$el.find(".actions");
        this.$el.find('.message').css('height', 40);
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

            this.$el.find('.message').css('height', 60);
            if (this.mentions.length > 0) {
                this.$el.find('.message').val(this.mentions + ' ');
            }
        }

        var msg = this.$el.find('.message').val();
        this.$el.find('.stream-message-add-btn').attr('disabled', (msg.length == 0));
    }
});

Zelten.MessageView = Backbone.View.extend({
    events: {
        "click a.show-conversation": "clickShowConversations",
        "click a.repost": "clickRepost",
        "click a.more-content": "showMoreContent"
    },
    initialize: function(args) {
        this.replyToView = new Zelten.WriteStatusView({
            mentions: this.$el.data('mentions'),
            messageList: args.messageList,
            el: this.$el.find(".stream-message-add-replyto")
        });
    },
    showMoreContent: function(e) {
        $(e.currentTarget).hide();
        this.$el.find('.hidden-content').slideDown();
        return false;
    },
    clickRepost: function(e) {
        var modal = new Zelten.ModalConfirmDialogView({
            params: {
                title: 'Do you want to repost this message?',
                post: this.$el.find('.message-body').html(),
                label: 'Yes, repost!'
            },
            success: _.bind(this.confirmClickRepost, this)
        });
        modal.render();
    },
    confirmClickRepost: function() {
        alert("not supported yet :(");
    },
    clickShowConversations: function(e) {
        var link = $(e.currentTarget);

        // dont use link here, because we want to disable ALL conversation links
        this.$el.find('a.show-conversation').attr('disabled', true).css('pointer-events', 'none');

        $.ajax({
            url: link.attr('href'),
            success: _.bind(this.showConversation, this)
        });

        this.$el.find('.conversations-pane').slideDown();
        this.$el.find('.conversations').addClass('loading');

        return false;
    },
    showConversation: function(data) {
        this.$el.find('.conversations').removeClass('loading');
        this.$el.find('.conversations').html(data);
        var cnt = this.$el.find('.conversations .conversation-message').length;
        this.$el.find('a.show-conversation').filter('.btn').append(' ' + cnt);
    },
    render: function() {
        this.$el.find('.show-tooltip').tooltip({});
        this.$el.find('.show-popover').popover({
            placement: 'bottom',
            trigger: 'hover'
        });
        this.$el.find('a.user-details').clickover({
            title:'User-Details',
            content: '&nbsp;',
            html: true,
            width: 400,
            template: '<div class="popover popover-user-details"><div class="arrow"></div><div class="popover-inner"><div class="popover-content loading"><p></p></div></div></div>'
        }).bind('shown', function(e) {
            var link = $(this);
            $.get($(e.currentTarget).attr('href'), function(data) {
                data = $(data);
                var userView = new Zelten.UserView({
                    el: data
                });

                link.data('clickover')
                    .tip()
                    .find('.popover-content')
                    .removeClass('loading').html(data);
            });
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
            el: this.$el.find('.stream-add-message-box .stream-message-add'),
            messageList: this.$el.find('.stream-messages')
        });
    },
    checkNewMessages: function() {
        var query = '?criteria[since_id]=' + ZeltenMessages.first.id + '&criteria[since_id_entity]=' + ZeltenMessages.first.entity
        if (ZeltenMessages.mentioned_entity) {
            query += '&criteria[mentioned_entity]=' + ZeltenMessages.mentioned_entity;
        }

        $.ajax({
            url: ZeltenMessages.url + query,
            success: _.bind(this.checkNewMessagesSuccess, this)
        });

    },
    checkNewMessagesSuccess: function(data) {
        var newEntries = $(data).find('.stream-messages');
        var done = false;
        var cnt = this.newMessagesCount;
        var newMessages = $("<div></div>");
        var messageList = this.$el.find('.stream-messages');
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
                messageList: messageList,
                el: el
            });
            message.render();
            newMessages.append(el);
        });

        if (cnt == 0) {
            return;
        }

        if (cnt == 1) {
            var msg = 'There is 1 new message.';
        } else {
            var msg = 'There are ' + cnt + ' new messages.';
        }

        this.newMessages.prepend(newMessages);
        this.$el.find('.stream-notifications').html('<div class="alert new-messages"><a href="#">' + msg + '</a></div>');
        this.$el.find('.new-messages').click(_.bind(this.showNewMessages, this));
        document.title = '(' + cnt + ') ' + this.title;
        this.newMessagesCount = cnt;
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

        var query = '?criteria[before_id]=' + ZeltenMessages.last.id + '&criteria[before_id_entity]=' + ZeltenMessages.last.entity;
        if (ZeltenMessages.mentioned_entity) {
            query += '&criteria[mentioned_entity]=' + ZeltenMessages.mentioned_entity;
        }

        $.ajax({
            url: ZeltenMessages.url + query,
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
                messageList: streamMessages,
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

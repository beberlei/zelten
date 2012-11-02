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

Zelten.UserListView = Backbone.View.extend({
    initialize: function(args) {
        this.url = args.url;
        this.loadUsers();
    },
    loadUsers: function() {
        this.$el.find('.people-list').addClass('loading');

        $.ajax({
            url: this.url,
            success: _.bind(this.loadUsersSuccess, this)
        });
    },
    loadUsersSuccess: function(data) {
        this.$el.find('.people-list').removeClass('loading');
        var data = $(data);
        data.find('.user').each(_.bind(this.addUser, this));
        this.$el.find('.total').text(data.find('.total').text());
    },
    addUser: function(idx, el) {
        el = $(el);
        var view = new Zelten.UserLinkView({
            el: el.find('.user-details')
        });
        view.render();

        this.$el.append(el);
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
        this.$el.find('textarea').autoResize({
            extraSpace: 0,
            animate: {duration: 50, complete: function() {}}
        });
    },
    cancelPosting: function() {
        var actions = this.$el.find(".actions");
        this.$el.find('.message').data('AutoResizer').config.extraSpace = 0;
        this.$el.find('.message').css('height', 30);
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

        var message = new Zelten.Message({
            id: newMessage.data('message-id'),
            entity: newMessage.data('entity'),
            published: newMessage.data('published'),
            element: newMessage
        });

        this.collection.add(message);

        this.$el.find('.stream-message-add-btn').attr('disabled', false);
        this.$el.each(function() {
            this.reset();
        });
        this.cancelPosting();
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

        var messageBox = this.$el.find('.message');
        messageBox.data('AutoResizer').config.extraSpace = 50;
        var msg = messageBox.val();
        this.$el.find('.stream-message-add-btn').attr('disabled', (msg.length == 0));
        this.$el.find('.status-length-left').text(256 - msg.length);
    }
});

Zelten.MessageView = Backbone.View.extend({
    events: {
        "click a.show-conversation": "clickShowConversations",
        "click a.show-reply": "clickReply",
        "click a.repost": "clickRepost",
        "click a.more-content": "showMoreContent"
    },
    initialize: function(args) {
        this.messages = args.messages;
        this.replyToView = new Zelten.WriteStatusView({
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
    },
    clickRepost: function(e) {
        var modal = new Zelten.ModalConfirmDialogView({
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

        var message = new Zelten.Message({
            id: newMessage.data('message-id'),
            entity: newMessage.data('entity'),
            published: newMessage.data('published'),
            element: newMessage
        });

        this.messages.add(newMessage);
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

        $(data).find('.user-details').each(function() {
            var view = new Zelten.UserLinkView({
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
        this.$el.find('a.user-details').each(function() {
            var view = new Zelten.UserLinkView({
                el: $(this)
            });
            view.render();
        });
    }
});

Zelten.UserLinkView = Backbone.View.extend({
    render: function() {
        this.$el.clickover({
            title:'User-Details',
            content: '&nbsp;',
            html: true,
            placement: 'bottom',
            width: 400,
            template: '<div class="popover popover-user-details"><div class="arrow"></div><div class="popover-inner"><div class="popover-content loading"><p></p></div></div></div>'
        }).bind('shown', this.clickoverIsShown);
    },
    clickoverIsShown: function(e) {
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
    }
});

Zelten.MessageCollection = Backbone.Collection.extend({
    comparator: function(message) {
        return message.get('published') * -1;
    }
});

Zelten.Message = Backbone.Model.extend({
});

Zelten.MessageStreamApplication = Backbone.View.extend({
    events: {
        'scroll-bottom': 'loadOlderPosts',
        'click .filter-post-type button': 'filterByPostType',
        'click a.loadOlderPosts': 'loadOlderPosts'
    },
    initialize: function(args) {
        this.entity = args.entity;
        this.postType = 'all';
        this.mentionedEntity = args.mentionedEntity;
        this.url = args.url;
        this.title = document.title;
        this.newMessagesCount = 0;
        this.win = $(window);
        this.win.scroll(_.bind(this.scrollCheck, this));
        setInterval(_.bind(this.checkNewMessages, this), 1000*15);
        this.collection.bind('add', _.bind(this.renderMessage, this));
    },
    filterByPostType: function(e) {
        var newPostType = $(e.currentTarget).attr('value');
        if (newPostType == this.postType) {
            return false;
        }

        this.postType = newPostType;
        this.collection.reset();
        this.checkNewMessages();

        return true;
    },
    checkNewMessages: function() {
        var firstMessage = this.collection.first();
        var query = '';

        if (this.postType != 'all') {
            query += 'criteria[post_types]=' + this.postType;
        }

        if (firstMessage) {
            query += 'criteria[since_id]=' + firstMessage.id + '&criteria[since_id_entity]=' + firstMessage.get('entity')
        } else {
            this.$el.find('.stream-messages').addClass('loading');
        }

        if (this.mentionedEntity && this.postType != 'follower') {
            query += '&criteria[mentioned_entity]=' + this.mentionedEntity;
        }

        if (this.entity) {
            query += '&criteria[entity]=' + this.entity;
        }

        $.ajax({
            url: this.url + ((query.length > 0) ? '?' : '') + query,
            success: _.bind(this.checkNewMessagesSuccess, this)
        });
    },
    checkNewMessagesSuccess: function(data) {
        var newEntries = $(data).find('.stream-message').addClass('hidden-content');
        var initialLoad = this.$el.find('.stream-message').length == 0;

        this.newMessagesCount = this.newMessagesCount + newEntries.length;

        this.$el.find('.stream-messages').removeClass('loading');

        if (this.newMessagesCount == 0) {
            return;
        }

        newEntries.each(_.bind(this.addMessage, this));

        if (initialLoad) {
            this.showNewMessages();
            return;
        }

        if (this.newMessagesCount == 1) {
            var msg = 'There is 1 new message.';
        } else {
            var msg = 'There are ' + this.newMessagesCount + ' new messages.';
        }

        this.$el.find('.stream-notifications').html('<div class="alert new-messages"><a href="#">' + msg + '</a></div>');
        this.$el.find('.new-messages').click(_.bind(this.showNewMessages, this));
        document.title = '(' + this.newMessagesCount + ') ' + this.title;
    },
    showNewMessages: function() {
        this.$el.find('.new-messages').remove();
        this.$el.find('.stream-message').removeClass('hidden-content');
        this.newMessagesCount = 0;
        document.title = this.title;
    },
    scrollCheck: function () {
        if (this.win.height() + this.win.scrollTop() == $(document).height()) {
            this.$el.trigger('scroll-bottom');
        }
    },
    renderExistingMessages: function() {
        var messageList = this.$el.find('.stream-messages');
        messageList.find('.stream-message').each(_.bind(this.addMessage, this));
    },
    addMessage: function(idx, snippet) {
        el = $(snippet);
        var message = new Zelten.Message({
            id: el.data('message-id'),
            entity: el.data('entity'),
            published: el.data('published'),
            element: el
        });
        this.collection.add(message);
    },
    renderMessage: function(message, col, options) {
        var messageList = this.$el.find('.stream-messages');
        var messageView = new Zelten.MessageView({
            messages: this.collection,
            model: message,
            el: message.get('element')
        });
        messageView.render();

        this.collection.on('reset', function() {
            messageView.remove();
        });

        // message already rendered
        if (messageList.children('*[data-message-id="' + message.id + '"]').length == 1) {
            return;
        }

        if (options.index == 0) {
            messageList.prepend(messageView.$el);
        } else {
            messageList.find('.stream-message').eq(options.index - 1).after(messageView.$el);
        }
    },
    renderWriteStatus: function() {
        this.postStatus = new Zelten.WriteStatusView({
            collection: this.collection,
            el: this.$el.find('.stream-add-message-box .stream-message-add'),
        });
    },
    render: function() {
        this.$el.find('.show-tooltip').tooltip({});

        this.renderExistingMessages();
        this.renderWriteStatus();
    },
    loadOlderPosts: function() {
        if (this.isLoadingOlderPosts) {
            return false;
        }

        this.isLoadingOlderPosts = true;

        this.loading = $('<div class="loading"></div>');
        this.$el.find('.stream-messages').append(this.loading);

        var query = '';
        var lastMessage = this.collection.last();

        if (this.postType != 'all') {
            query += 'criteria[post_types]=' + this.postType;
        }

        if (lastMessage) {
            query += '&criteria[before_id]=' + lastMessage.id + '&criteria[before_id_entity]=' + lastMessage.get('entity');
        }

        if (this.mentionedEntity) {
            query += '&criteria[mentioned_entity]=' + this.mentionedEntity;
        }

        if (this.entity) {
            query += '&criteria[entity]=' + this.entity;
        }

        $.ajax({
            url: this.url + ((query.length > 0) ? '?' : '') + query,
            success: _.bind(this.loadOlderPostsSuccess, this),
            error: _.bind(this.loadOlderPostsError, this)
        });

        return false;
    },
    loadOlderPostsError: function() {
        this.isLoadingOlderPosts = false;
    },
    loadOlderPostsSuccess:function(data) {
        this.loading.remove();

        var newEntries = $(data).find('.stream-messages');
        var streamMessages = this.$el.find('.stream-messages');

        newEntries.find('.stream-message').each(_.bind(this.addMessage, this));

        this.isLoadingOlderPosts = false;
    }
});


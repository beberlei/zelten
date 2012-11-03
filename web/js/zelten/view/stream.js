define(
    ["backbone", "zelten/model/message", "zelten/view/message", "zelten/view/writestatus", "bootstrap"],
    function(Backbone, Message, MessageView, WriteStatusView) {

    var messageStream = Backbone.View.extend({
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
            this.followers = args.followers;
            this.following = args.following;

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

            this.$el.find('.stream-messages').removeClass('loading');

            if (newEntries.length == 0) {
                return;
            }

            newEntries.each(_.bind(this.addMessage, this));
            
            if (this.newMessagesCount == 0) {
                return;
            }

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
            var el = $(snippet);
            var message = new Message({
                id: el.data('message-id'),
                entity: el.data('entity'),
                published: el.data('published'),
                element: el
            });

            // message already rendered
            var messageList = this.$el.find('.stream-messages');
            if (messageList.children('*[data-message-id="' + message.id + '"]').length == 0) {
                this.newMessagesCount++;
            }

            this.collection.add(message);
        },
        renderMessage: function(message, col, options) {
            var messageList = this.$el.find('.stream-messages');
            var messageView = new MessageView({
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
            this.postStatus = new WriteStatusView({
                collection: this.collection,
                el: this.$el.find('.stream-add-message-box .stream-message-add')
            });
            this.postStatus.render();
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

    return messageStream;
});

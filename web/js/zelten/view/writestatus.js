define(["backbone", "zelten/model/message", "autoresize", "select2"], function(Backbone, Message) {

    var writeStatusView = Backbone.View.extend({
        events: {
            "click .message": "showActions",
            "keyup .message": "showActions",
            "change .message": "showActions",
            "click .stream-message-add-cancel": "cancelPosting",
            "submit": "writeMessage"
        },
        initialize: function(args) {
            this.mentions = args.mentions || '';
            this.hasPermissions = this.$el.find('.complete-permissions').length > 0;
        },
        render: function() {
            this.$el.find('textarea').autoResize({
                extraSpace: 0,
                animate: {duration: 50, complete: function() {}}
            });

            this.actions    = this.$el.find(".actions");
            this.messageBox = this.$el.find('.message');
            this.btn        = this.$el.find('.stream-message-add-btn');
            this.charsLeft  = this.$el.find('.status-length-left');

            if (this.hasPermissions) {
                this.$el.find('.complete-permissions').select2({
                    tags: ['Everybody']
                });
            }
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

            var message = new Message({
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
            if (this.actions.is(':hidden')) {
                this.actions.slideDown();

                if (this.hasPermissions) {
                    this.$el.find('.complete-permissions').select2('data', {id: 'public', text: 'Everybody'});
                }

                this.messageBox.css('height', 60);
                this.messageBox.data('AutoResizer').config.extraSpace = 50;

                if (this.mentions.length > 0) {
                    this.messageBox.val(this.mentions + ' ');
                }
            }

            var msg = this.messageBox.val();
            this.btn.attr('disabled', (msg.length == 0));
            this.charsLeft.text(256 - msg.length);
        }
    });

    return writeStatusView;
});

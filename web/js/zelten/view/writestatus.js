define(["backbone", "zelten/model/message", "zelten/view/modaldialog", "autosize", "select2"], function(Backbone, Message, ModalConfirmDialogView) {

    var writeStatusView = Backbone.View.extend({
        events: {
            "click .message": "showActions",
            "click .stream-message-add-cancel": "cancelPosting",
            "click .link-add-toggle": "linkAddToggle",
            "paste .link-add": "linkAddPaste",
            "click .link-add-btn": "linkAddClick",
            "submit": "writeMessage",
            "click .close": "closePanel"
        },
        initialize: function(args) {
            this.mentions = args.mentions || '';
            this.hasPermissions = this.$el.find('.complete-permissions').length > 0;
        },
        closePanel: function(e) {
            var link = $(e.currentTarget);
            link.parents('.' + link.data('dismiss')).hide();
            this.linkAddReset();
        },
        linkAddPaste: function(e) {
            setTimeout(_.bind(this.linkAddFetchDetails, this, $(e.currentTarget)), 0);
        },
        linkAddClick: function(e) {
            this.linkAddFetchDetails(this.$el.find('.link-add'));
        },
        linkAddFetchDetails: function(link) {
            this.$el.find('.link-add-btn').attr('disabled', true);
            $.ajax({
                url: link.data('parse-link') + '?url=' + link.val(),
                type: 'GET',
                dataType: 'json',
                success: _.bind(this.linkAddShowDetails, this),
                error: _.bind(this.linkAddError, this)
            });
        },
        linkAddShowDetails: function(data) {
            var form = this.$el.find('.link-add-form');
            form.hide();
            form.find('.link-add-title').val(data.title);
            form.find('.link-add-description').val(data.description);
            form.find('.link-add-image').val(data.image);

            var details = this.$el.find('.link-add-details').show();
            details.find('.link-add-title').text(data.title);
            details.find('.link-add-description').text(data.description);
            details.find('.link-add-image').attr('src', data.image);
        },
        linkAddError: function() {
            this.$el.find('.link-add-btn').attr('disabled', false);
        },
        linkAddToggle: function(e) {
            if (this.$el.find('.actions').is(':hidden')) {
                this.showActions();
            }

            if (this.$el.find('.link-add-form').is(':hidden')) {
                this.linkAddReset();
                this.$el.find('.link-add-btn').attr('disabled', false);
                this.$el.find('.link-add-details').hide();
                this.$el.find('.link-add-form').toggle();
                this.$el.find('.link-add').focus();
            } else {
                this.$el.find('.link-add-form').toggle();
            }

            return false;
        },
        linkAddReset: function() {
            this.$el.find('.link-add-form input[type="hidden"]').val(''); // reset
            this.$el.find('.link-add').val('');
        },
        render: function() {
            this.$el.find('textarea').autosize({});

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
            actions.slideUp();
        },
        writeMessage: function(e) {
            var form = $(e.currentTarget);
            var msg = form.find('.message').val();

            if (typeof(msg) == 'undefined' || msg.length == 0) {
                form.find('.message').addClass('error');
                return false;
            }

            if (msg.length > 256) {
                var modal = new ModalConfirmDialogView({
                    params: {
                        title: 'Publish this Post as Essay?',
                        post: 'The text of this status message is longer than 256 chars. Do you want to post the status as an essay instead?',
                        label: 'Yes, publish!'
                    },
                    success: _.bind(this.sendMessage, this, form)
                });
                modal.render();
                return false;
            }

            this.sendMessage(form);
            return false;
        },
        sendMessage: function(form) {

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

            if (this.collection) {
                this.collection.add(message);
            }

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


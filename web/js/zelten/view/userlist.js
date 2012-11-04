define(["backbone", "zelten/view/userlink"], function(Backbone, UserLinkView) {

    var userListView = Backbone.View.extend({
        initialize: function(args) {
            this.url = args.url;
            this.loadUsers();
        },
        loadUsers: function() {
            this.$el.find('.people-list').addClass('loading');

            $.ajax({
                url: this.url,
                timeout: 4000,
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
            var view = new UserLinkView({
                el: el.find('.user-details')
            });
            view.render();

            this.$el.append(el);
        }
    });

    return userListView;
});


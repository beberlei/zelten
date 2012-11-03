
Zelten.NotificationCountView = Backbone.View.extend({
    initialize: function(args) {
        this.countUrl = args.url;
        setInterval(_.bind(this.checkNewNotification, this), 30000);
        this.$el.append(
            '<span class="notification-count badge badge-info hidden-content">0</span>'
        );
    },
    checkNewNotification: function() {
        $.ajax({
            url: this.countUrl,
            success: _.bind(this.checkNewNotificationSuccess, this)
        });
    },
    checkNewNotificationSuccess: function(data) {
        if (data.count == 0) {
            return;
        }

        this.$el.find('.notification-count').text(data.count).show();
    },
    render: function() {
        this.checkNewNotification();
    }
});


var Zelten = Zelten || {};

Zelten.NotificationCountView = Backbone.View.extend({
    events: {
        "click": "saveCheckedNotifications"
    },
    initialize: function(args) {
        this.countUrl = args.url;
        setInterval(_.bind(this.checkNewNotification, this), 30000);
        this.$el.append(
            '<span class="notification-count badge badge-info hidden-content">0</span>'
        );
    },
    checkNewNotification: function() {
        var query = '?criteria[since_time]=' + this.lastUpdateTimestamp();
        $.ajax({
            url: this.countUrl + query,
            success: _.bind(this.checkNewNotificationSuccess, this)
        });
    },
    checkNewNotificationSuccess: function(data) {
        if (data.count == 0) {
            return;
        }

        this.$el.find('.notification-count').text(data.count).show();
    },
    saveCheckedNotifications: function() {
        var ts = Math.round((new Date()).getTime() / 1000);
        $.cookie('zelten_notifications_update', ts);
    },
    lastUpdateTimestamp: function() {
        console.log($.cookie('zelten_notifications_update'));
        return $.cookie('zelten_notifications_update');
    }
});


/**
 * View that Regularly checks for notifications and updates
 * a notification count badge on the annotated view element.
 */
define(["backbone"], function(Backbone) {

    var NotificationCountView = Backbone.View.extend({
        initialize: function(args) {
            this.countUrl = args.url;
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
            console.log(data);
            if (data.count == 0) {
                return;
            }

            this.$el.find('.notification-count').text(data.count).show();
        },
        render: function() {
            setInterval(_.bind(this.checkNewNotification, this), 30000);
            this.checkNewNotification();
        }
    });

    return NotificationCountView;
});


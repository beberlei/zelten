/**
 * View that Regularly checks for notifications and updates
 * a notification count badge on the annotated view element.
 */
define(["backbone"], function(Backbone) {

    var NotificationCountView = Backbone.View.extend({
        initialize: function(args) {
            this.countUrl = args.url;
        },
        checkNewNotification: function() {
            $.ajax({
                url: this.countUrl,
                success: _.bind(this.checkNewNotificationSuccess, this)
            });
        },
        checkNewNotificationSuccess: function(data) {
            this.$el.find('.count').text(data.count);
        },
        render: function() {
            this.$el.find('.count').text("0");
            setInterval(_.bind(this.checkNewNotification, this), 30000);
            this.checkNewNotification();
        }
    });

    return NotificationCountView;
});


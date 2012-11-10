define(["backbone"], function(Backbone) {

    var userView = Backbone.View.extend({
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
            var btn = this.$el.find('form.follow .btn');
            var action = this.$el.find('form.follow .action');

            if (action.val() == 'follow') {
                action.val('unfollow');
                btn.addClass('btn-danger')
                   .removeClass('btn-success')
                   .attr('value', 'Unfollow')
                   .attr('disabled', false);
            } else {
                action.val('follow');
                btn.removeClass('btn-danger')
                   .addClass('btn-success')
                   .attr('value', 'Follow')
                   .attr('disabled', false);
            }
        }
    });

    return userView;
});

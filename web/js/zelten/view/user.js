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
            this.$el.find('form.follow .btn')
                    .addClass('btn-danger')
                    .removeClass('btn-success')
                    .attr('value', 'Unfollow')
                    .attr('disabled', true);
        }
    });

    return userView;
});

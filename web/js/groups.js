var Zelten = Zelten || {};

Zelten.Follower = Backbone.Model.extend({
});

Zelten.Group = Backbone.Model.extend({
});

Zelten.GroupCollection = Backbone.Collection.extend({
    model: Zelten.Group
});

Zelten.FollowerCollection = Backbone.Collection.extend({
    url: ZeltenConfig.groupFollowers,
    model: Zelten.Follower
});

Zelten.GroupsApplication = Backbone.View.extend({
    events: {
        "submit form.group-add": "createGroup"
    },
    initialize: function() {
        var groups = new Zelten.GroupCollection();
        this.$el.find('.group').each(function (el) {
            groups.add({name: el.find('.group-name').text()});
        });
        this.groups = groups;
    },
    createGroup: function(e) {
        var form = $(e.currentTarget);
        $.ajax({
            url:  form.attr('action'),
            type: 'POST',
            data: form.serialize(),
            success: _.bind(this.createGroupSuccess, this)
        });
        this.$el.find('.group-add-btn').attr('disabled', true);

        return false;
    },
    createGroupSuccess: function(data) {
        this.$el.find('.group-add-btn').attr('disabled', false);
        this.$el.find('.groups').append(data);
    },
    render: function() {
    }
});


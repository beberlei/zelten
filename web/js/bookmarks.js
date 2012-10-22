var Bookmark = Backbone.Model.extend({
    urlRoot: '/bookmarks',
    defaults: {
        content: {url: '#', title:'No title', description: ''}
    }
});

var BookmarksCollection = Backbone.Collection.extend({
    initialize: function(args) {
        if (args.filter) {
            var filter = (args.filter.length > 0) ? '?' + args.filter : '';
            this.url   = this.url + filter;
        }
    },
    url: '/bookmarks'
});

var BookmarkView = Backbone.View.extend({
    initialize: function() {
        this.template = _.template($("#bookmark-template").html());
    },
    events: {
        "click .bookmark-delete": "deletePost",
        "click .bookmark-read": "readPost"
    },
    readPostDisplay: function(data) {
        this.$el.find('.read').html(data);
    },
    readPost: function() {
        $.ajax({
            url: this.model.url(),
            success: _.bind(this.readPostDisplay, this)
        });
        return false;
    },
    deletePost: function() {
        this.model.destroy();
        this.remove();
    },
    render: function() {
        this.$el.html(this.template(this.model.toJSON()));
        return this.$el;
    }
});

var BookmarkList = Backbone.View.extend({
    initialize: function() {
        this.collection.bind('reset', _.bind(this.addAll, this));
    },
    addAll: function() {
        this.$el.html('');
        this.collection.each(_.bind(this.addOne, this));
    },
    addOne: function(bookmark) {
        var view = new BookmarkView({
            model: bookmark
        });

        this.$el.append(view.render());
    },
    prependTo: function(bookmark) {
        var view = new BookmarkView({
            model: bookmark
        });

        this.$el.prepend(view.render());
    }
});

var BookmarkApplication = Backbone.View.extend({
    currentImages: [],
    currentImage: 0,
    events: {
        'click .url-load': 'addBookmark',
        'click .image-forward': 'imageForward',
        'click .image-backward': 'imageBackward',
        'click .image-none': 'imageNone',
        'submit form.add': 'saveBookmark',
        'click a.reset': 'resetForm',
        'click #tabs a': 'clickTab'
    },
    resetForm: function() {
        this.$el.find('form.add').each(function() { this.reset(); });
        this.$el.find('.details').slideUp();

        return false;
    },
    saveBookmark: function() {
        var url = $("#bookmark_url").val();

        if (url.length == 0) {
            return false;
        }

        var bookmark = new Bookmark({
            content: {
                url: $("#bookmark_url").val(),
                title: $("#bookmark_title").val(),
                description: $("#bookmark_description").val(),
                image: $("#bookmark_image").val(),
                privacy: $("#bookmark_privacy").val()
            }
        });
        bookmark.save();

        this.collection.add(bookmark);
        this.resetForm();

        return false;
    },
    imageNone: function() {
        this.$el.find('.image_picker').html('');
        this.$el.find('#bookmark_image').val('');
    },
    imageForward: function() {
        this.currentImage++;
        this.displayImage();
        return false;
    },
    imageBackward: function() {
        this.currentImage--;
        this.displayImage();
        return false;
    },
    displayImage: function() {
        var url = this.currentImages[this.currentImage];

        if (typeof(this.currentImages[this.currentImage-1]) == 'undefined') {
            this.$el.find('.image-backward').attr('disable', true);
        }

        if (typeof(this.currentImages[this.currentImage+1]) == 'undefined') {
            this.$el.find('.image-forward').attr('disable', true);
        }

        if (typeof(url) == 'undefined' || url.length == 0) {
            return false;
        }

        this.$el.find("#bookmark_image").val(url);

        var image = $("<img />");
        image.attr('src', url)
             .attr('width', 100).
             attr('height', 100);
        this.$el.find('.image_picker').html(image);

    },
    showBookmarkDetails: function(data) {
        this.$el.find('.details').slideDown();
        for (var key in data.bookmark) {
            this.$el.find('#bookmark_' + key).val(data['bookmark'][key]);
        }
        this.currentImages = data.images;
        this.currentImage = 0;
        this.displayImage();
    },
    addBookmark: function(e) {
        var url = this.$el.find('input.url').val();
        $.ajax({
            url: '/bookmarks/parse?url=' + url,
            type: 'GET',
            dataType: 'json',
            success: _.bind(this.showBookmarkDetails, this),
            error: function(data) {
                alert('Error: Invalid url or the entered page returns something other than a valid result.');
            }
        });
        return false;
    },
    initialize: function() {
        this.myBookmarks = new BookmarkList({
            collection: this.collection,
            el: this.$el.find('#my').find('.list')
        });

        this.publicBookmarks = new BookmarkList({
            collection: new BookmarksCollection({
                filter: 'mode=public'
            }),
            el: this.$el.find('#public').find('.list')
        });
    },
    render: function() {
        this.myBookmarks.addAll();
    },
    clickTab: function(e) {
        e.preventDefault();
        $(this).tab('show');

        if ($(e.currentTarget).attr('href') == '#public') {
            this.publicBookmarks.collection.fetch();
        }
    }
});


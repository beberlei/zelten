var Bookmark = Backbone.Model.extend({
    urlRoot: '/bookmarks',
    defaults: {
        content: {url: '#', title:'No title', description: ''}
    }
});

var BookmarksCollection = Backbone.Collection.extend({
    initialize: function(models, args) {
        if (typeof(args) != 'undefined' && args.filterMode) {
            var filter = (args.filterMode.length > 0) ? ('?' + args.filterMode) : '';
            this.url = function() {
                return '/bookmarks' + filter;
            }
        }
    },
    url: '/bookmarks'
});

var BookmarkView = Backbone.View.extend({
    initialize: function(args) {
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
        var data = this.model.toJSON();
        data.isOwner = (Zelten.entity == data.entity);
        data.publishedDate = new Date(data.published_at*1000);
        this.$el.html(this.template(data));
        return this.$el;
    }
});

var BookmarkList = Backbone.View.extend({
    initialize: function() {
        this.collection.bind('reset', _.bind(this.addAll, this));
        this.collection.bind('add', _.bind(this.prependTo, this));
    },
    addAll: function() {
        this.$el.html('');
        this.collection.each(_.bind(this.addOne, this));
    },
    createView: function(bookmark) {
        return new BookmarkView({
            model: bookmark
        });
    },
    addOne: function(bookmark) {
        this.$el.append(this.createView(bookmark).render());
    },
    prependTo: function(bookmark) {
        this.$el.prepend(this.createView(bookmark).render());
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
            entity: Zelten.entity,
            content: {
                url: $("#bookmark_url").val(),
                title: $("#bookmark_title").val(),
                description: $("#bookmark_description").val(),
                image: $("#bookmark_image").val(),
                privacy: $("#bookmark_privacy").val()
            }
        });
        bookmark.save({}, {
            success: _.bind(this.onBookmarkSaved, this),
            error: _.bind(this.onBookmarkError, this)
        });

        return false;
    },
    onBookmarkSaved: function(bookmark) {
        this.collection.add(bookmark);
        this.resetForm();
    },
    onBookmarkError: function(bookmark, response) {
        var errors = jQuery.parseJSON(response.responseText);
        this.showErrorMessage(errors);
    },
    showErrorMessage: function(errors) {
        var template = _.template($("#error-message").html());
        $(".errors").html(template(errors));
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
            error: _.bind(this.onBookmarkParseError, this)
        });
        return false;
    },
    onBookmarkParseError: function() {
        this.showErrorMessage({
            messages: ['Invalid url or the entered page returns something other than a valid result.']}
        );
    },
    initialize: function() {
        this.myBookmarks = new BookmarkList({
            collection: this.collection,
            el: this.$el.find('#my').find('.list')
        });

        this.publicBookmarks = new BookmarkList({
            collection: new BookmarksCollection([], {
                filterMode: 'mode=public'
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


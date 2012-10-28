var Bookmark = Backbone.Model.extend({
    urlRoot: '/bookmarks/',
    defaults: {
        content: {url: '#', title:'No title', description: ''}
    }
});

var BookmarksCollection = Backbone.Collection.extend({
    initialize: function(models, args) {
        if (typeof(args) != 'undefined' && args.filterMode) {
            var filter = (args.filterMode.length > 0) ? ('?' + args.filterMode) : '';
            this.url = function() {
                return '/bookmarks/' + filter;
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
        'keypress #bookmark_url': 'bookmarkKeyPress',
        'click .url-load': 'addBookmark',
        'submit form.add': 'saveBookmark',
        'click a.reset': 'resetForm'
    },
    bookmarkKeyPress: function(e) {
         if (e.which == 13){
             this.addBookmark(e);
             return false;
         }
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
    showBookmarkDetails: function(data) {
        $("#bookmark_url").removeClass('bookmark-url-loading');

        this.$el.find('.details').slideDown();
        for (var key in data) {
            this.$el.find('#bookmark_' + key).val(data[key]);
        }
    },
    validateUrl: function(url) {
        var el = document.createElement('a');
        el.href = url;

        if (el.protocol.indexOf('http') == -1 && el.protocol.indexOf('https') == -1) {
            return false;
        }

        if (el.hostname.indexOf('.') == -1) {
            return false;
        }

        return true;
    },
    addBookmark: function(e) {
        var el  = $(e.currentTarget);
        var url = el.val();

        if (url.indexOf('http') == -1) {
            url = url = 'http://' + url;
        }

        if (!this.validateUrl(url)) {
            return false;
        }

        if (url == $(el).data('last-value')) {
            return false;
        }

        el.data('last-value', url);
        el.addClass('bookmark-url-loading');

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
        $("#bookmark_url").removeClass('bookmark-url-loading');
    },
    initialize: function() {
        this.myBookmarks = new BookmarkList({
            collection: this.collection,
            el: this.$el.find('.list')
        });
    },
    render: function() {
        this.myBookmarks.addAll();
    }
});


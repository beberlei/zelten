({
    appDir: "web/js",
    baseUrl: ".",
    dir: "web/build",
    modules: [
        {name: "homepage"},
        {name: "stream"},
        {name: "stream_user"},
        {name: "bookmarks"},
        {name: "my_stream"},
        {name: "user_followers"},
        {name: "user_following"},
        {name: "stream_notifications"}
    ],
    shim: {
        'backbone': {
            //These script dependencies should be loaded before loading
            //backbone.js
            deps: ['underscore', 'jquery'],
            //Once loaded, use the global 'Backbone' as the
            //module value.
            exports: 'Backbone'
        },
        'timeago': ['jquery'],
        'select2': ['jquery'],
        'clickover': ['bootstrap'],
        'bootstrap': ['jquery'],
        'autoresize': ['jquery'],
        'autosize': ['jquery']
    }
})

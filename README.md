# Zelten Bookmarks

A bookmark manager for tent.io

This is open source, but not yet easily installable on any server.
If you want to do it, make sure to run:

    php console doctrine:schema:update

To generate the database schema and modify ``src/app.php`` to
include your redirect url, otherwise the application will not work
and redirect to the wrong location.


# Zelten

A social client that runs on the Tent protocol. https://tent.io/
Visit Zelten: http://zelten.cc

This is open source, and not yet easily installable on any
server.  If you want to do it, make sure to run:

    php console doctrine:schema:update

To generate the database schema and modify ``src/app.php`` to
include your redirect url, otherwise the application will not
work. It will redirect to the wrong location.

## Configuration

Configuration is mostly done through environment variables.
For example, when using Apache put the following in your Vhost:

    SetEnv DB_USER root
    SetEnv DB_HOST localhost
    SetEnv DB_PASSWORD test
    SetEnv DB_NAME zelten
    SetEnv TWITTER_KEY twitterkey
    SetEnv TWITTER_SECRET twsecret
    SetEnv APPSECRET somerandomstring

## Data

All the Mac secrets and OAuth tokens are saved in the database using
Blowish encryption and are safe from database theft.



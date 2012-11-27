# Zelten

Visit Zelten: [http://zelten.cc][zelten]

Zelten is a social client that communicates using the Tent 
protocol. You can learn more about Tent at [https://tent.io/][tentio]

Zelten is open source so feel free to contribute to the project. 
Installing Zelten on a server is not yet easy.  If you want to try, 
make sure to run:

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

[zelten]: http://zelten.cc "Zelten"
[tentio]: https://tent.io/ "Tent.io"

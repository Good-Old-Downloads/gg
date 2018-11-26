# GOGGames Installation

These instructions assume you have experience with installing, configuring, and securing web site software within Linux. Instructions have been tested with Ubuntu 16.04.4 LTS and Debian Stretch.

That said, it is possible to run GOGGames on a Windows server.
### Prerequisites

- PHP >= 7.2

  `apt-get install php`
- MariaDB

  `apt-get install mariadb-server`
- Java or OpenJDK (whichever version that whatever version of ElasticSearch you installed wants)

  `apt-get install openjdk-8-jdk`
- ElasticSearch >= 6.2

  https://www.elastic.co/downloads/elasticsearch
- memcached

  `apt-get install memcached`
- Composer

  https://getcomposer.org/download/

### Installing
##### Getting the sauce:

```bash
git clone https://wherever.the/code/ends-up-on.git
```

##### Installing the PHP requirements:
`cd` into the directory where the code now lies then install the site dependencies via Composer:
```bash
cd gg
php composer.phar install
```
##### Configuring the site:
Make a copy of config_blank.php named config.php and edit it.
```bash
cp config_blank.php config.php
vi config.php
```
config.php explantion:
```php
<?php
$CONFIG = [
    // The login URL, set up a script to change this once in a while if you're paranoid.
    "LOGIN_PATH" => 'ayylmaosecretloginpageyolo',
    "BASEDIR" => "/var/www/gg",

    // Storage paths for GOG iamges
    "BG_STORAGE" => "/var/www/gg/static/img/games/bg",
    "DETAILS_STORAGE" => "/var/www/god/static/img/games/details",
    "THUMB_STORAGE" => "/var/www/god/static/img/games/thumb",

    // Shows PHP errors, disables the Twig cache, probably does other things.
    // Set to false in production.
    "DEV" => true,

    // GG has no user system. Set up the login name and password here.
    // Again, use a script to change this once in a while if you're paranoid.
    "USER" => [
        "NAME" => "supasecretlogin",
        "PASS" => "123",

        // Key used with the http API
        "KEY" => "123-321-1337"
    ],

    // MySQL deets
    "DB" => [
        "DBNAME" => "gg",
        "DBUSER" => "root",
        "DBPASS" => ""
    ],

    // Memcached deets
    "MEMCACHED" => [
        "SERVER" => "127.0.0.1",
        "PORT" => 11211
    ]
];

// Keys for the Vigenère cipher. Make a lot of these.
// Shitty way to stop the most basic of HTML scrapers, if someone takes time to put the smallest amount of effort decypher this, then they deserve the links. (pretty sure someone from The Eye already made a dumper)
$VKEYS = [
    "RANDKEYONE",
    "RANDKEYTWO",
    "RANDKEYTHREE",
    "RANDKEYFOUR",
    "RANDKEYFIVE",
    "RANDKEYSIX",
    "RANDKESVEN"
];
```

##### Importing the empty database:
Login to MySQL, create a database, then import db.sql.
```
MariaDB [(none)]> CREATE DATABASE `gg`;
MariaDB [gg]> USE `gg`;
MariaDB [gg]> SOURCE db.sql;
```

##### Configuring the Nginx:
The Nginx config is pretty standard. I'll only list the relevant config values.
```nginx
server {
        listen 443 ssl http2;
        root /var/www/gg/web; # <-- must point to /web directory
        index index.php
        autoindex on;
        location = /index.php {
                try_files $uri =404;
                fastcgi_pass unix:/var/run/php/php7.2-fpm.sock;
                fastcgi_index index.php;
                fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
                include fastcgi_params;
        }
        location / {
            try_files $uri /index.php$is_args$args;
        }
        location ~ \.php$ {
                # prevent exposure of any other .php files!!!
                return 404;
        }
        location ~ /\.ht {
                deny all;
        }
}
```

##### Starting up memcached and ElasticSearch:
If you made it this far, you should know how to start these up already, and how to secure them both.

### Running tests
lol

### Coding style
hahaha
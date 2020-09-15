<?php
$CONFIG = [
    // The login URL, set up a script to change this once in a while if you're paranoid.
    "LOGIN_PATH" => 'ayylmaosecretloginpageyolo',
    "BASEDIR" => "/var/www/gg",

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

    // MySQL details
    "DB" => [
        "DBHOST" => "localhost",
        "DBNAME" => "gg",
        "DBUSER" => "root",
        "DBPASS" => "123"
    ],

    // Memcached details
    "MEMCACHED" => [
        "SERVER" => "127.0.0.1",
        "PORT" => 11211
    ],

    // ElasticSearch details
    "ES" => [
        "HOSTS" => [
            "localhost"
        ]
    ],

    // reCaptcha
    "CAPTCHA" => [
        "SECRET" => "",
        "SITE" => ""
    ]
];

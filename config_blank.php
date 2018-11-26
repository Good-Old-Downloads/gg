<?php
$CONFIG = array(
    "LOGIN_PATH" => 'login', // set the login url
    "BASEDIR" => "/var/www/god",
    "BG_STORAGE" => "/var/www/god/static/img/games/bg",
    "DETAILS_STORAGE" => "/var/www/god/static/img/games/details",
    "THUMB_STORAGE" => "/var/www/god/static/img/games/thumb",
    "DEV" => true,

    "USER" => [
        "NAME" => "mercs213",
        "PASS" => "",
        "KEY" => ""
    ],

    "DB" => [
        "DBNAME" => "god",
        "DBUSER" => "root",
        "DBPASS" => ""
    ],

    "MEMCACHED" => [
        "SERVER" => "127.0.0.1",
        "PORT" => 11211
    ]
);

$VKEYS = [
    "RANDKEYONE",
    "RANDKEYTWO",
    "RANDKEYTHREE",
    "RANDKEYFOUR",
    "RANDKEYFIVE",
    "RANDKEYSIX",
    "RANDKESVEN"
];
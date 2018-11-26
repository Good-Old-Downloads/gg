<?php
// Connect database
try {
    $dbh = new PDO(
        "mysql:host=localhost;dbname=".$CONFIG["DB"]["DBNAME"].";charset=utf8",
        $CONFIG["DB"]["DBUSER"],
        $CONFIG["DB"]["DBPASS"]);
} catch (\PDOException $e) {
    echo "brb lol";
    die;
}
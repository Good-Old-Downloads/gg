<?php
// Connect database
try {
    $dbh = new PDO(
        "mysql:host=".$CONFIG["DB"]["DBHOST"].";dbname=".$CONFIG["DB"]["DBNAME"].";charset=utf8",
        $CONFIG["DB"]["DBUSER"],
        $CONFIG["DB"]["DBPASS"]);
} catch (\PDOException $e) {
    echo "Could not establish a connection to MariaDB.";
    die;
}

<?php
/*
    GOGGames
    Copyright (C) 2018  GoodOldDownloads

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

if (php_sapi_name() !== 'cli') {
    echo "Only run from command line!";
    die;
}
ini_set('max_execution_time', 2400);
require 'vendor/autoload.php';
require 'config.php';
require 'db.php';
require 'Elastic.class.php';

function addLog($text){
    global $dbh;
    $now = date('U');

    // Prepare SQL query
    $set = $dbh->prepare("INSERT INTO `log` (`value`, `date`) VALUES (:text, :date)");
    $set->bindParam(':text', $text, \PDO::PARAM_STR);
    $set->bindParam(':date', $now, \PDO::PARAM_INT);
    return $set->execute();
}

switch ($argv[1]) {
    case 'updateGames':
        updateGames($dbh);
        break;

    case 'updateImages':
        updateImages($dbh);
        break;

    case 'updateGamesImages':
        updateGames($dbh);
        updateImages($dbh);
        break;

    case 'clearTags':
        clearTags($dbh);
        break;
    
    default:
        echo "Invalid Task";
        break;
}

function updateGames($dbh){
    addLog('Start task "updateGames"');
    // Prepare SQL query
    $add = $dbh->prepare("
        INSERT IGNORE INTO `games`
        (`id`, `title`, `indev`, `slug`, `slug_folder`, `url`, `release_date`, `developer`, `publisher`, `category`, `hidden`)
        VALUES (:id, :title, :indev, :slug, :slug, :url, :rlsdate, :dev, :pub, :cat, 1);
    ");
    $add->bindParam(':id', $id, \PDO::PARAM_INT);
    $add->bindParam(':title', $title, \PDO::PARAM_STR);
    $add->bindParam(':indev', $inDev, \PDO::PARAM_INT);
    $add->bindParam(':slug', $slug, \PDO::PARAM_STR);
    $add->bindParam(':url', $url, \PDO::PARAM_STR);
    $add->bindParam(':rlsdate', $releaseDate, \PDO::PARAM_INT);
    $add->bindParam(':dev', $developer, \PDO::PARAM_STR);
    $add->bindParam(':pub', $publisher, \PDO::PARAM_STR);
    $add->bindParam(':cat', $category, \PDO::PARAM_STR);

    $client = new GuzzleHttp\Client();
    $cookieJar = GuzzleHttp\Cookie\CookieJar::fromArray([
        'gog_lc' => 'US_USD_en-US'
    ], '.gog.com');
    $page = 1;
    while (true) {
        $res = $client->request('GET', "https://www.gog.com/games/ajax/filtered?mediaType=game&page=$page&limit=50", ['cookies' => $cookieJar]);
        $status = $res->getStatusCode();
        if ($status === 200) {
            $json = json_decode($res->getBody(), true);
            $products = $json['products'];
            $totalPages = intval($json['totalPages']);
            $page = intval($json['page']);
            if ($page <= $totalPages) {
                foreach ($products as $key => $product) {
                    $id = $product['id'];
                    $title = $product['title'];
                    $slug = $product['slug'];
                    $category = $product['category'];
                    $url = $product['url'];
                    if ($url != '') {
                        $url = 'https://www.gog.com'.$url;
                    }
                    $releaseDate = $product['releaseDate'];
                    $inDev = $product['isInDevelopment'];
                    $developer = $product['developer'];
                    $publisher = $product['publisher'];
                    $add->execute();
                    if ($add->rowCount() > 0) {
                        addLog("Added $title");
                    }
                }
                $page++;
            } else {
                break;
            }
        } else {
            break;
        }
    }
    addLog('Done task "updateGames"');
}

function updateImages($dbh){
    global $CONFIG;
    addLog('Start task "updateImages"');
    // List games to work on
    $empty = $dbh->prepare('SELECT `title`, `id`, `has_background`, `has_thumbnail` FROM `games` WHERE `has_background` = 0 OR `has_thumbnail` = 0');
    $empty->execute();
    $emptyImages = $empty->fetchAll(\PDO::FETCH_ASSOC);

    // Prepare SQL query
    $updateBG = $dbh->prepare("UPDATE `games` SET `has_background` = 1 WHERE id = :id");
    $updateBG->bindParam(':id', $id, \PDO::PARAM_INT);

    $updateThumb = $dbh->prepare("UPDATE `games` SET `has_thumbnail` = 1 WHERE id = :id");
    $updateThumb->bindParam(':id', $id, \PDO::PARAM_INT);

    $client = new GuzzleHttp\Client();

    $bgcount = 0;
    $thumbcount = 0;
    $affectedGames = [];
    $fail = [];

    // Work on images
    foreach ($emptyImages as $key => $game) {
        $id = $game['id'];
        $title = $game['title'];
        $affectedGames[$id] = [
            'title' => $title,
            'thumb' => false,
            'bg' => false
        ];
        $rand = md5(openssl_random_pseudo_bytes(5));
        try {
            $res = $client->request('GET', "https://api.gog.com/products/$id?$rand");
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $fail[] = $id;
            continue;
        }
        
        $json = json_decode($res->getBody(), true);
        $bgURL = $json['images']['background'];
        $bgBig = $bgURL;
        $thumb = str_replace("_glx_logo.jpg", "_196.jpg", $json['images']['logo']);
        $bgDetails = preg_replace('/(\.jpg)/', '_details_bg_1120.jpg', $bgURL);

        if ($game['has_background'] == '0') {
            // Try downloading backgrounds
            try {
                $client->request('GET', "https:$bgBig", ['sink' => "{$CONFIG['BG_STORAGE']}/$id.jpg"]);
                $client->request('GET', "https:$bgDetails", ['sink' => "{$CONFIG['DETAILS_STORAGE']}/$id.jpg"]);
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                continue;
            }
            $updateBG->execute();
            addLog("Added background for $title");
        }

        if ($game['has_thumbnail'] == '0') {
            // Try thumbnail
            try {
                $client->request('GET', "https:$thumb", ['sink' => "{$CONFIG['THUMB_STORAGE']}/$id.jpg"]);
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                continue;
            }
            $updateThumb->execute();
            addLog("Added thumbnail for $title");
        }
    }
    addLog('Done task "updateImages"');
}

function clearTags($dbh){
    global $CONFIG;
    $Elastic = new Elastic();
    $getIds = $dbh->prepare('
        SELECT `id` fROM `games`
        WHERE (`new` = 1 OR `updated` = 1) AND `last_update` <= (UNIX_TIMESTAMP() - 604800)
        AND `hidden` != 1
    ');
    $getIds->execute();
    $ids = $getIds->fetchAll(PDO::FETCH_COLUMN, 0);

    $clear = $dbh->prepare('
        UPDATE `games` SET `updated` = 0, `new` = 0
        WHERE (`new` = 1 OR `updated` = 1) AND `last_update` <= (UNIX_TIMESTAMP() - 604800)
        AND `hidden` != 1
    ');
    $clear->execute();

    foreach ($ids as $key => $id) {
        $Elastic->UpdateGame($id);
    }

    $count = $clear->rowCount();
    if ($count > 0) {
        addLog("Cleared tags of $count games.");
    }
}
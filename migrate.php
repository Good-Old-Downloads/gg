<?php
if (php_sapi_name() !== 'cli') {
    echo "Only run from command line!";
    die;
}
ini_set('max_execution_time', 2400);
require 'vendor/autoload.php';
require 'config.php';
require 'db.php';

$get = $dbh->prepare("SELECT `game_id`, `files`, `uploading` FROM old_gog.`games_filelist`");
$get->execute();
$old_data = $get->fetchAll(\PDO::FETCH_OBJ);

$getTimes = $dbh->prepare("SELECT `id`, UNIX_TIMESTAMP(`time_upload`) as time_upload, UNIX_TIMESTAMP(`time_update`) as time_update FROM old_gog.`games`");
$getTimes->execute();
$times = $getTimes->fetchAll(\PDO::FETCH_OBJ);

$setTime = $dbh->prepare("UPDATE `games` SET `last_update` = :update, `last_upload` = :upload WHERE `id` = :id");
$setTime->bindParam(':id', $id, \PDO::PARAM_INT);
$setTime->bindParam(':upload', $upload, \PDO::PARAM_INT);
$setTime->bindParam(':update', $update, \PDO::PARAM_INT);
foreach ($times as $key => $value) {
    $id = intval($value->id);
    $upload = $value->time_upload;
    $update = $value->time_update;
    $setTime->execute();
}

$getTags = $dbh->prepare("SELECT `id`, `updated`, `new`, `hidden` FROM old_gog.`games`");
$getTags->execute();
$tags = $getTags->fetchAll(\PDO::FETCH_OBJ);

$setTags = $dbh->prepare("UPDATE `games` SET `new` = :new, `updated` = :updated, `hidden` = :hidden WHERE `id` = :id");
$setTags->bindParam(':id', $id, \PDO::PARAM_INT);
$setTags->bindParam(':new', $new, \PDO::PARAM_INT);
$setTags->bindParam(':updated', $updated, \PDO::PARAM_INT);
$setTags->bindParam(':hidden', $hidden, \PDO::PARAM_INT);
foreach ($tags as $key => $value) {
    $id = intval($value->id);
    $updated = intval($value->updated);
    $new = intval($value->new);
    $hidden = intval($value->hidden);
    $setTags->execute();
}

foreach ($old_data as $key => $value) {
    if ($value->uploading == 1) {
        continue;
    }
    $id = intval($value->game_id);
    $file_info = json_decode($value->files);
    foreach ($file_info as $key => $file) {
        switch ($key) {
            case 'goodies':
                $set = $dbh->prepare("INSERT IGNORE INTO `links` (`game_id`, `link`, `type`) VALUES (:id, :link, 'GOODIES')");
                foreach ($file as $key => $link) {
                    $set->bindParam(':id', $id, \PDO::PARAM_INT);
                    $set->bindParam(':link', $link, \PDO::PARAM_STR);
                    $set->execute();
                }
                break;
            case 'game':
                $set = $dbh->prepare("INSERT IGNORE INTO `links` (`game_id`, `link`, `type`) VALUES (:id, :link, 'GAME')");
                foreach ($file as $key => $link) {
                    $set->bindParam(':id', $id, \PDO::PARAM_INT);
                    $set->bindParam(':link', $link, \PDO::PARAM_STR);
                    $set->execute();
                }
                break;
            case 'goodies_list':
                $set = $dbh->prepare("INSERT IGNORE INTO `files` (`game_id`, `name`, `type`) VALUES (:id, :name, 'GOODIES')");
                foreach ($file as $key => $name) {
                    $set->bindParam(':id', $id, \PDO::PARAM_INT);
                    $set->bindParam(':name', $name, \PDO::PARAM_STR);
                    $set->execute();
                }
                break;
            case 'game_list':
                $set = $dbh->prepare("INSERT IGNORE INTO `files` (`game_id`, `name`, `type`) VALUES (:id, :name, 'GAME')");
                foreach ($file as $key => $name) {
                    $set->bindParam(':id', $id, \PDO::PARAM_INT);
                    $set->bindParam(':name', $name, \PDO::PARAM_STR);
                    $set->execute();
                }
                break;
            case 'patch_list':
                $set = $dbh->prepare("INSERT IGNORE INTO `files` (`game_id`, `name`, `type`) VALUES (:id, :name, 'PATCHES')");
                foreach ($file as $key => $name) {
                    $set->bindParam(':id', $id, \PDO::PARAM_INT);
                    $set->bindParam(':name', $name, \PDO::PARAM_STR);
                    $set->execute();
                }
                break;
            case 'patch':
                $set = $dbh->prepare("INSERT IGNORE INTO `links` (`game_id`, `link`, `type`) VALUES (:id, :link, 'PATCHES')");
                foreach ($file as $key => $link) {
                    $set->bindParam(':id', $id, \PDO::PARAM_INT);
                    $set->bindParam(':link', $link, \PDO::PARAM_STR);
                    $set->execute();
                }
                break;
            
            default:
                echo "oh shit\n";
                echo $key;
                die;
                break;
        }
    }
}
<?php
$setup = false;
if(!file_exists('notifier.sqlite')) {
    $setup = true;
}

$db = new DB("notifier.sqlite");

if($setup) {
    $db->exec("CREATE TABLE entries (
        `id` INTEGER PRIMARY KEY AUTOINCREMENT,
        `extension_url` VARCHAR,
        `reviews` INTEGER
    );");
}

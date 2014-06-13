#!/usr/bin/env php
<?php

$path = dirname(__DIR__);

include($path . '/init.inc.php');
Pix_Table::$_save_memory = true;
if (isset($argv[0]) &&  $argv[0] === '--drop-table') {
    Pix_Setting::set('Table:DropTableEnable', true);
}

foreach(glob($path . '/models/*.php') AS $m) {
    $p = pathinfo($m);
    $o = new $p['filename'];
    if($o instanceof Pix_Table) {
        $o->dropTable();
        $o->createTable();
    }
}

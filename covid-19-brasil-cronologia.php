<?php

define('BIN_DIR', realpath(__DIR__ . '/bin'));

include BIN_DIR . '/download-minister.php';
include BIN_DIR . '/create-covid-table.php';
include BIN_DIR . '/publish-table.php';

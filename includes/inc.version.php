<?php

$svn_info = '$Id: inc.version.php 4 2010-07-16 11:11:29Z benjam $';

preg_match('/\$\w+: [-_\w\d.]+ (\d+) (\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}).*\$/', $svn_info, $matches);

// just so I don't have to edit files
// that have no other changes but this
define('VERSION', '0.8.0');
define('UPDATED', strtotime($matches[2]));
define('REVISION', (int) $matches[1]);

// and some random text so I have something in this file to change
/*

Hello World, how art thee?

*/


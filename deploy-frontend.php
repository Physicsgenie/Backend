<?php

require('config.php'); // No peeking

define('TOKEN', $TOKEN); // Secrets

define('REMOTE_REPOSITORY', 'git@github.com:Physicsgenie/Frontend.git'); // The SSH URL to your repository
define('DIR', '/home/pg/Frontend');      // The path to your repository; this must begin with a forward slash (/)
define('BRANCH', 'refs/heads/master');                                 // The branch route
define('LOGFILE', 'deploy.log');                                       // The name of the file you want to log to.
define('GIT', '/usr/bin/git');                                         // The path to the git executable
define('MAX_EXECUTION_TIME', 600);                                     // Override for PHP's max_execution_time (may need set in php.ini)
define('BEFORE_PULL', '/usr/bin/git fetch && /usr/bin/git reset --hard @{u}');               // A command to execute before pulling
define('AFTER_PULL', 'export PATH="/home/pg/Frontend/node_modules/bin:$PATH" && npm run build');                                              // A command to execute after successfully pulling

require_once('deployer.php');

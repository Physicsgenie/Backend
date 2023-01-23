<?php

require('config.php'); // No peeking

define('TOKEN', $TOKEN); // Secrets

define('REMOTE_REPOSITORY', 'git@github.com:Physicsgenie/Backend.git'); // The SSH URL to your repository
define('DIR', '/var/www/physicsgenie/wp-content/plugins/physics_genie/');      // The path to your repostiory; this must begin with a forward slash (/)
define('BRANCH', 'refs/heads/master');                                 // The branch route
define('LOGFILE', 'deploy.log');                                       // The name of the file you want to log to.
define('GIT', '/usr/bin/git');                                         // The path to the git executable
define('MAX_EXECUTION_TIME', 180);                                     // Override for PHP's max_execution_time (may need set in php.ini)
define('BEFORE_PULL', '/usr/bin/git fetch && /usr/bin/git reset --hard @{u}');               // A command to execute before pulling
define('AFTER_PULL', '');                                              // A command to execute after successfully pulling

require_once('deployer.php');

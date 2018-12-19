<?php
/* Production */
ini_set('display_errors', 0);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', false);
define('DISALLOW_FILE_MODS', true); // this disables all file modifications including updates and update notifications
define('WP_REDIS_DISABLED', true);

define('WP_DEBUG', true);

ini_set('log_errors', 1);
ini_set('error_log', getenv('WP_LOGS_DIR') . '/debug.log');

define('WP_PROXY_BYPASS_HOSTS', '155.33.31.198');

// disallow elasticpress sync from wp-admin
define( 'EP_DASHBOARD_SYNC', false );

// Redis
$redis_server = array(
//	'host'     => 'hc-prod-redis.gdrquz.0001.use1.cache.amazonaws.com',
//	'port'     => 6379,
	//'auth'     => '12345',
	//'database' => 0, // Optionally use a specific numeric Redis database. Default is 0.
);

<?php
/* Staging */
ini_set('display_errors', 0);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', false);
define('DISALLOW_FILE_MODS', true); // this disables all file modifications including updates and update notifications

/* Changes to vanilla Bedrock below this line */

/* Disable outgoing mail */
function wp_mail(){}

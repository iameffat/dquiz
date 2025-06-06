<?php
// includes/config.php

// Prevent direct access to this file
if (!defined('DQUIZ_EXEC')) {
    die('Direct access not permitted');
}

// ============== Database Credentials ==============
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'u259133487_dlquiz');
define('DB_PASSWORD', 'lVMXkD|4Ll');
define('DB_NAME', 'u259133487_dlquiz');

// ============== Timezone Configuration ==============
define('APP_TIMEZONE', 'Asia/Dhaka');
define('DB_TIMEZONE', '+06:00');

// ============== Cloudflare API Configuration ==============
define('CLOUDFLARE_ZONE_ID', 'de81d047d212760f1c53492a2bc1a4fc');
define('CLOUDFLARE_API_TOKEN', 'ssLJZCntDPfF5plkElUlMw2RFv5x5K3kslrDATd8');

// Legacy Cloudflare keys (not used if API token is set, but defined for compatibility)
define('CLOUDFLARE_USE_API_TOKEN', true); 
define('CLOUDFLARE_EMAIL', '');
define('CLOUDFLARE_GLOBAL_API_KEY', '');

?>
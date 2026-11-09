<?php

/** Shows output of phpinfo(). */

use Redaxo\Core\Core;

// Hardened mode must protect secrets even from (possibly compromised) admin accounts: phpinfo() would list
// $_SERVER/$_ENV including DATABASE_URL (with password), MAILER_DSN and REX_MIGRATE_TOKEN.
phpinfo(Core::isHardenedMode() ? INFO_ALL & ~INFO_ENVIRONMENT & ~INFO_VARIABLES : INFO_ALL);

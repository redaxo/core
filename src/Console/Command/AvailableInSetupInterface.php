<?php

namespace Redaxo\Core\Console\Command;

/**
 * Marks a command as available during the setup.
 *
 * Without this interface a command is only registered after the setup has been completed.
 *
 * @internal Only usable in rex core commands
 */
interface AvailableInSetupInterface {}

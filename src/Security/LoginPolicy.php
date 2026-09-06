<?php

namespace Redaxo\Core\Security;

use Redaxo\Core\Exception\InvalidArgumentException;

use function sprintf;

/** Policy for the backend login, configured by the project. */
final readonly class LoginPolicy
{
    /**
     * @param int $maxTriesUntilDelay Number of allowed login tries, until the login is delayed
     * @param int $maxTriesUntilBlock Number of allowed login tries, until the login is blocked
     * @param int $reloginDelay Delay in seconds after `$maxTriesUntilDelay` failed tries
     */
    public function __construct(
        public int $maxTriesUntilDelay = 3,
        public int $maxTriesUntilBlock = 50,
        public int $reloginDelay = 5,
        public bool $stayLoggedInEnabled = true,
    ) {
        foreach (['maxTriesUntilDelay' => $maxTriesUntilDelay, 'maxTriesUntilBlock' => $maxTriesUntilBlock, 'reloginDelay' => $reloginDelay] as $name => $value) {
            if ($value < 1) {
                throw new InvalidArgumentException(sprintf('Invalid value "%d" for "%s", it must be greater than zero.', $value, $name));
            }
        }
    }
}

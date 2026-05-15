<?php

namespace Redaxo\Core\Security;

use Redaxo\Core\Exception\InvalidArgumentException;

use function array_key_exists;

/**
 * @internal
 */
final readonly class LoginPolicy
{
    /** @param array<string, int|bool> $options */
    public function __construct(
        private array $options,
    ) {}

    /**
     * Returns the number of allowed login tries, until login will be delayed.
     *
     * @return positive-int
     */
    public function getMaxTriesUntilDelay(): int
    {
        $key = 'login_tries_until_delay';

        if (array_key_exists($key, $this->options)) {
            $val = (int) $this->options[$key];
            if ($val <= 0) {
                throw new InvalidArgumentException('Invalid value "' . $val . '" for option "' . $key . '".');
            }
            return $val;
        }

        // defaults, in case config.yml does not define values
        // e.g. because of a redaxo core update from a version.
        return 3;
    }

    /**
     * Returns the number of allowed login tries, until login will be blocked.
     *
     * @return positive-int
     */
    public function getMaxTriesUntilBlock(): int
    {
        $key = 'login_tries_until_blocked';

        if (array_key_exists($key, $this->options)) {
            $val = (int) $this->options[$key];
            if ($val <= 0) {
                throw new InvalidArgumentException('Invalid value "' . $val . '" for option "' . $key . '".');
            }
            return $val;
        }

        // defaults, in case config.yml does not define values
        // e.g. because of a redaxo core update from a version.
        return 50;
    }

    /**
     * Returns the relogin delay in seconds.
     *
     * @return positive-int
     */
    public function getReloginDelay(): int
    {
        $key = 'relogin_delay';

        if (array_key_exists($key, $this->options)) {
            $val = (int) $this->options[$key];
            if ($val <= 0) {
                throw new InvalidArgumentException('Invalid value "' . $val . '" for option "' . $key . '".');
            }
            return $val;
        }

        // defaults, in case config.yml does not define values
        // e.g. because of a redaxo core update from a version.
        return 5;
    }

    public function isStayLoggedInEnabled(): bool
    {
        $key = 'enable_stay_logged_in';

        if (array_key_exists($key, $this->options)) {
            return (bool) $this->options[$key];
        }

        // enabled by default
        return true;
    }
}

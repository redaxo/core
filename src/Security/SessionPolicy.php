<?php

namespace Redaxo\Core\Security;

/** Policy for the lifetime of a backend session, configured by the project. */
final readonly class SessionPolicy
{
    /**
     * @param int $duration Seconds of inactivity after which the session is closed
     * @param int $keepAlive Seconds an open browser window extends the session for, `0` to disable
     * @param int $maxOverallDuration Seconds a session can last at most, no matter how actively it is used
     * @param int $warningTime Seconds before the session expires, at which the user is warned
     */
    public function __construct(
        public int $duration = 7200,
        public int $keepAlive = 21600,
        public int $maxOverallDuration = 2_419_200,
        public int $warningTime = 300,
    ) {}
}

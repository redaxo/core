<?php

namespace Redaxo\Core;

/**
 * The mode an instance runs in, defined by the env var `REX_MODE` (usually in the `.env` file).
 */
enum Mode: string
{
    /**
     * Local development (or an otherwise non-public instance): verbose error output for everyone and strict
     * error handling (warnings/notices are thrown as exceptions).
     */
    case Dev = 'dev';

    /**
     * Publicly reachable instances (production and staging): no error details for visitors, but logged-in
     * admins still get detailed error pages, and the backend keeps all management features.
     */
    case Live = 'live';

    /**
     * Like `Live`, but additionally hardens the backend against compromised admin accounts: even admins get no
     * error details, and features that could alter the instance (addon management, backup import, safe mode,
     * setup restart) are disabled in the backend.
     */
    case Hardened = 'hardened';
}

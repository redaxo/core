<?php

namespace Redaxo\Core\Mailer;

/**
 * Which sent mails are written to the mail log, configured by the env var `REX_MAILER_LOG`.
 *
 * @internal
 */
enum MailLogMode: string
{
    case None = 'none';
    case Errors = 'errors';
    case All = 'all';
}

<?php

namespace Redaxo\Core\Log;

use IntlDateFormatter;
use LimitIterator;
use Redaxo\Core\Core;
use Redaxo\Core\Env;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Formatter;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

use function sprintf;

use const FILTER_VALIDATE_INT;

/**
 * Mails the recent errors of the system log to the address defined by the env var `REX_ERROR_EMAIL`.
 *
 * @internal
 */
final class ErrorReport
{
    private function __construct() {}

    public static function send(): void
    {
        $recipient = Env::get('REX_ERROR_EMAIL');
        if (null === $recipient) {
            return;
        }

        $logFile = Logger::getPath();
        $lastSendTime = (int) Core::getConfig('error_report_last_send_time', 0);
        $lastErrors = (string) Core::getConfig('error_report_last_errors', '');
        $currentErrors = '';

        if (!filesize($logFile)) {
            return;
        }

        $file = LogFile::factory($logFile);
        $logevent = false;

        $mailBody = '<h2>Error protocol for: ' . Core::getProject()->instanceName . '</h2>';
        $mailBody .= '<style> .errorbg {background: #F6C4AF; } .eventbg {background: #E1E1E1; } td, th {padding: 5px;} table {width: 100%; border: 1px solid #ccc; } th {background: #b00; color: #fff;} td { border: 0; border-bottom: 1px solid #b00;} </style> ';
        $mailBody .= '<table>';
        $mailBody .= '    <thead>';
        $mailBody .= '        <tr>';
        $mailBody .= '            <th>' . I18n::msg('syslog_timestamp') . '</th>';
        $mailBody .= '            <th>' . I18n::msg('syslog_type') . '</th>';
        $mailBody .= '            <th>' . I18n::msg('syslog_message') . '</th>';
        $mailBody .= '            <th>' . I18n::msg('syslog_file') . '</th>';
        $mailBody .= '            <th>' . I18n::msg('syslog_line') . '</th>';
        $mailBody .= '            <th>' . I18n::msg('syslog_url') . '</th>';
        $mailBody .= '        </tr>';
        $mailBody .= '    </thead>';
        $mailBody .= '    <tbody>';

        $errorCount = 0;
        $maxErrors = 30;

        /** @var LogEntry $entry */
        foreach (new LimitIterator($file, 0, $maxErrors) as $entry) {
            $data = $entry->getData();
            $time = Formatter::intlDateTime($entry->getTimestamp(), [IntlDateFormatter::SHORT, IntlDateFormatter::MEDIUM]);
            $type = $data[0];
            $message = $data[1];
            $file = $data[2] ?? '';
            $line = $data[3] ?? '';
            $url = $data[4] ?? '';

            $style = '';
            if (false !== stripos($type, 'error') || false !== stripos($type, 'exception') || 'logevent' === $type) {
                $style = ' class="' . (('logevent' === $type) ? 'eventbg' : 'errorbg') . '"';
                $logevent = true;
                $currentErrors .= $entry->getTimestamp() . $type . $message;
                ++$errorCount;
            }

            $mailBody .= '        <tr' . $style . '>';
            $mailBody .= '            <td>' . $time . '</td>';
            $mailBody .= '            <td>' . $type . '</td>';
            $mailBody .= '            <td>' . substr($message, 0, 128) . '</td>';
            $mailBody .= '            <td>' . $file . '</td>';
            $mailBody .= '            <td>' . $line . '</td>';
            $mailBody .= '            <td>' . $url . '</td>';
            $mailBody .= '        </tr>';

            if ($errorCount >= $maxErrors) {
                break;
            }
        }

        $mailBody .= '    </tbody>';
        $mailBody .= '</table>';

        if (!$logevent) {
            return;
        }

        $currentErrorsHash = md5($currentErrors);

        $timeSinceLastSend = time() - $lastSendTime;
        if ($timeSinceLastSend < self::getInterval() && $currentErrorsHash === $lastErrors) {
            return;
        }

        $mailer = Core::getMailer();

        $email = new Email()
            ->to($recipient)
            ->subject(Core::getProject()->instanceName . ' - Error Report')
            ->html($mailBody)
            ->text(strip_tags($mailBody));

        if (null !== $mailer->defaultFrom) {
            $email->from(new Address($mailer->defaultFrom->getAddress(), 'REDAXO Error Report'));
        }

        try {
            $mailer->send($email);
        } catch (TransportExceptionInterface) {
            // already logged by the mailer, the report is tried again on the next request
            return;
        }

        Core::setConfig('error_report_last_errors', $currentErrorsHash);
        Core::setConfig('error_report_last_send_time', time());
    }

    /**
     * Returns the minimum number of seconds between two error reports with the same errors, defined by the env var
     * `REX_ERROR_EMAIL_INTERVAL` (default: one hour).
     */
    public static function getInterval(): int
    {
        $interval = Env::get('REX_ERROR_EMAIL_INTERVAL');
        if (null === $interval) {
            return 3600;
        }

        $seconds = filter_var($interval, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if (false === $seconds) {
            throw new InvalidArgumentException(sprintf('The env var "REX_ERROR_EMAIL_INTERVAL" must be a number of seconds, "%s" given.', $interval));
        }

        return $seconds;
    }
}

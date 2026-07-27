<?php

namespace Redaxo\Core\Mailer;

use Exception;
use IntlDateFormatter;
use LimitIterator;
use PHPMailer\PHPMailer\PHPMailer;
use Redaxo\Core\Core;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Finder;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Log\LogEntry;
use Redaxo\Core\Log\LogFile;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\Util\Timer;

use function array_slice;
use function count;

use const FILTER_VALIDATE_EMAIL;
use const ICONV_MIME_DECODE_CONTINUE_ON_ERROR;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_UNICODE;
use const PATHINFO_EXTENSION;

class Mailer extends PHPMailer
{
    public const LOG_ERRORS = 1;
    public const LOG_ALL = 2;

    public string $graphClientId;
    public string $graphClientSecret;
    public string $graphTenantId;

    private bool $archive;

    /** used to store information if detour mode is enabled. */
    private array $xHeader = [];

    public function __construct($exceptions = false)
    {
        self::setLanguage(I18n::getLanguage(), Path::core('vendor/phpmailer/phpmailer/language/'));

        $this->Timeout = 10;
        $this->XMailer = 'REXMailer';
        $this->From = Core::getConfig('phpmailer_from');
        $this->FromName = Core::getConfig('phpmailer_fromname');
        $this->ConfirmReadingTo = Core::getConfig('phpmailer_confirmto');
        $this->Sender = Core::getConfig('phpmailer_returnto');
        $this->Mailer = Core::getConfig('phpmailer_mailer');
        $this->Host = Core::getConfig('phpmailer_host');
        $this->Port = Core::getConfig('phpmailer_port');
        $this->CharSet = Core::getConfig('phpmailer_charset');
        $this->WordWrap = Core::getConfig('phpmailer_wordwrap');
        $this->Encoding = Core::getConfig('phpmailer_encoding');
        if (0 == Core::getConfig('phpmailer_priority')) {
            $this->Priority = null;
        } else {
            $this->Priority = Core::getConfig('phpmailer_priority');
        }
        $this->SMTPDebug = Core::getConfig('phpmailer_smtp_debug');
        $this->SMTPSecure = Core::getConfig('phpmailer_smtpsecure');
        $this->SMTPAuth = Core::getConfig('phpmailer_smtpauth');
        $this->SMTPAutoTLS = Core::getConfig('phpmailer_security_mode');
        $this->Username = Core::getConfig('phpmailer_username');
        $this->Password = Core::getConfig('phpmailer_password');

        $this->graphClientId = Core::getConfig('phpmailer_msgraph_client_id') ?? '';
        $this->graphClientSecret = Core::getConfig('phpmailer_msgraph_client_secret') ?? '';
        $this->graphTenantId = Core::getConfig('phpmailer_msgraph_tenant_id') ?? '';

        if ($bcc = Core::getConfig('phpmailer_bcc')) {
            $this->addBCC($bcc);
        }
        $this->archive = Core::getConfig('phpmailer_archive');
        parent::__construct($exceptions);

        Extension::dispatch(new ExtensionPoint('PHPMAILER_CONFIG', $this));
    }

    protected function addOrEnqueueAnAddress($kind, $address, $name)
    {
        if (Core::getConfig('phpmailer_detour_mode') && '' != Core::getConfig('phpmailer_test_address')) {
            if ('to' == $kind) {
                $detourAddress = Core::getConfig('phpmailer_test_address');

                // store the address so we can use it in the subject later

                // if there has already been a call to addOrEnqueueAnAddress and detour mode is on
                // xHeader['to'] should have already been set
                // therefore we add the address to xHeader['to'] for the subject later
                // and parent::addOrEnqueueAnAddress doesnt need to be called since it would be the test address again

                if (isset($this->xHeader['to'])) {
                    $this->xHeader['to'] .= ', ' . $address;
                    return true;
                }

                $this->xHeader['to'] = $address;

                // Set $address to the detour address
                $address = $detourAddress;
            } else {
                if (isset($this->xHeader[$kind])) {
                    $this->xHeader[$kind] .= ', ' . $address;
                } else {
                    $this->xHeader[$kind] = $address;
                }

                return true;
            }
        }

        return parent::addOrEnqueueAnAddress($kind, $address, $name);
    }

    public function send(): bool
    {
        return Timer::measure(__METHOD__, function () {
            $logging = (int) Core::getConfig('phpmailer_logging');
            $detourModeActive = Core::getConfig('phpmailer_detour_mode') && '' !== Core::getConfig('phpmailer_test_address');

            Extension::dispatch(new ExtensionPoint('PHPMAILER_PRE_SEND', $this));

            if ($detourModeActive && isset($this->xHeader['to'])) {
                $this->prepareDetourMode();
            }

            if (!parent::send()) {
                if ($logging) {
                    $this->log('ERROR');
                }
                if ($this->archive) {
                    $this->archive($this->getSentMIMEMessage(), 'not_sent_');
                }
                return false;
            }

            if ($this->archive) {
                $this->archive($this->getSentMIMEMessage());
            }

            if (self::LOG_ALL === $logging) {
                $this->log('OK');
            }

            Extension::dispatch(new ExtensionPoint('PHPMAILER_POST_SEND', $this));

            return true;
        });
    }

    private function prepareDetourMode(): void
    {
        $this->clearCCs();
        $this->clearBCCs();

        foreach (['to', 'cc', 'bcc', 'ReplyTo'] as $kind) {
            if (isset($this->xHeader[$kind])) {
                $this->addCustomHeader('x-' . $kind, $this->xHeader[$kind]);
            }
        }

        $this->Subject = I18n::msg('phpmailer_detour_subject', $this->Subject, $this->xHeader['to']);
        $this->xHeader = []; // Bereinigung für die nächste Verwendung
    }

    public function clearQueuedAddresses($kind): void
    {
        parent::clearQueuedAddresses($kind);

        unset($this->xHeader[$kind]);
    }

    public function clearAllRecipients(): void
    {
        parent::clearAllRecipients();

        $this->xHeader = [];
    }

    private function log(string $success): void
    {
        $replytos = '';
        if (count($this->getReplyToAddresses()) > 0) {
            $replytos = implode(', ', array_column($this->getReplyToAddresses(), 0));
        }

        $log = LogFile::factory(self::logFile(), 2_000_000);
        $data = [
            $success,
            $this->From . ($replytos ? '; reply-to: ' . $replytos : ''),
            implode(', ', array_column($this->getToAddresses(), 0)),
            $this->Subject,
            trim(str_replace('https://github.com/PHPMailer/PHPMailer/wiki/Troubleshooting', '', strip_tags($this->ErrorInfo))),
        ];
        $log->add($data);
    }

    /**
     * Enable/disable the mail archive.
     *
     * It overwrites the global `archive` configuration for the current mailer object.
     */
    public function setArchive(bool $status): void
    {
        $this->archive = $status;
    }

    private function archive(string $archivedata = '', string $status = ''): void
    {
        $dir = self::logFolder() . '/' . date('Y') . '/' . date('m');
        $count = 1;
        $archiveFile = $dir . '/' . $status . date('Y-m-d_H_i_s') . '.eml';
        while (is_file($archiveFile)) {
            $archiveFile = $dir . '/' . $status . date('Y-m-d_H_i_s') . '_' . (++$count) . '.eml';
        }

        File::put($archiveFile, $archivedata);
    }

    /** Path to mail archive folder. */
    public static function logFolder(): string
    {
        return Path::coreData('phpmailer/mail_log');
    }

    /** Path to log file. */
    public static function logFile(): string
    {
        return Path::log('mail.log');
    }

    /** @internal */
    public static function errorMail(): void
    {
        $logFile = Path::log('system.log');
        $lastSendTime = (int) Core::getConfig('phpmailer_last_log_file_send_time', 0);
        $lastErrors = (string) Core::getConfig('phpmailer_last_errors', '');
        $currentErrors = '';

        // Check if the log file has content
        if (!filesize($logFile)) {
            return;
        }

        $file = LogFile::factory($logFile);
        $logevent = false;

        // Start - generate mail body
        $mailBody = '<h2>Error protocol for: ' . Core::getServerName() . '</h2>';
        $mailBody .= '<style nonce="' . Response::getNonce() . '"> .errorbg {background: #F6C4AF; } .eventbg {background: #E1E1E1; } td, th {padding: 5px;} table {width: 100%; border: 1px solid #ccc; } th {background: #b00; color: #fff;} td { border: 0; border-bottom: 1px solid #b00;} </style> ';
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
        $maxErrors = 30; // Maximum number of errors to process

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

        // If no errors were found, terminate
        if (!$logevent) {
            return;
        }

        // Create hash of current errors
        $currentErrorsHash = md5($currentErrors);

        // Combine time-based and content-based checks
        $timeSinceLastSend = time() - $lastSendTime;
        $errorMailInterval = (int) Core::getConfig('phpmailer_errormail');

        if ($timeSinceLastSend < $errorMailInterval && $currentErrorsHash === $lastErrors) {
            return;
        }

        // Send email
        $mail = new self();
        $mail->Subject = Core::getServerName() . ' - Error Report';
        $mail->Body = $mailBody;
        $mail->AltBody = strip_tags($mailBody);
        $mail->FromName = 'REDAXO Error Report';
        $mail->addAddress(Core::getErrorEmail());

        // Set X-Mailer header for ErrorMails
        $mail->XMailer = 'REDAXO/' . Core::getVersion() . ' ErrorMailer';

        if ($mail->Send()) {
            // Update configuration only if email was sent successfully
            Core::setConfig('phpmailer_last_errors', $currentErrorsHash);
            Core::setConfig('phpmailer_last_log_file_send_time', time());
        }
    }

    protected function microsoft365Send(): bool
    {
        $transformAddress = static function (array $addr) {
            return ['emailAddress' => ['address' => $addr[0], 'name' => $addr[1] ?? '']];
        };

        $from = '' === $this->Sender ? $this->From : $this->Sender;
        $to = array_map($transformAddress, $this->getToAddresses());
        $subject = $this->Subject;

        // Korrektes Mapping: contentType klein schreiben!
        // Body-Type für Graph-API anhand von bereits gesetztem contentType bestimmen
        if (static::CONTENT_TYPE_PLAINTEXT !== $this->ContentType) {
            $body = ['contentType' => 'html', 'content' => $this->Body];
        } else {
            $body = ['contentType' => 'text', 'content' => $this->Body];
        }

        // CC/BCC für Graph API aufbereiten
        $cc = array_map($transformAddress, $this->getCcAddresses() ?: []);
        $bcc = array_map($transformAddress, $this->getBccAddresses() ?: []);

        // Reply-To-Adressen für Graph API aufbereiten (nur gültige, nicht-leere Adressen, KEIN leeres Array setzen)
        $replyToAddresses = array_filter($this->getReplyToAddresses(), static function ($addr) {
            return !empty($addr[0]) && filter_var($addr[0], FILTER_VALIDATE_EMAIL);
        });
        $replyTo = [];
        /** @var array{0: string, 1?: string} $addr */
        foreach ($replyToAddresses as $addr) {
            $entry = ['emailAddress' => ['address' => $addr[0]]];
            if (isset($addr[1]) && '' !== trim($addr[1])) {
                $entry['emailAddress']['name'] = $addr[1];
            }
            $replyTo[] = $entry;
        }

        $customHeaders = [];
        /** @var array{string, string} $header */
        foreach ($this->getCustomHeaders() as $header) {
            $customHeaders[] = [
                'name' => $header[0],
                'value' => $this->encodeHeader(trim($header[1])),
            ];
        }

        // Attachments für Graph API aufbereiten
        $attachments = [];
        /** @var array{string, string, string, string, string, bool} $att */
        foreach ($this->getAttachments() as $att) {
            $file = $att[0];
            $name = $att[2] ?: Path::basename($file);
            $type = $att[4] ?: 'application/octet-stream';
            $isString = $att[5] ?? false;
            $content = $isString ? $file : File::get($file);
            if (null !== $content) {
                $attachments[] = [
                    '@odata.type' => '#microsoft.graph.fileAttachment',
                    'name' => $name,
                    'contentType' => $type,
                    'contentBytes' => base64_encode($content),
                ];
            }
        }

        // ensure valid access token
        /** @var array{access_token: string, expires: int, expires_in?: int}|null $token */
        $token = Core::getConfig('phpmailer_msgraph_token');
        if (!isset($token['access_token']) || $token['expires'] - 300 < time()) {
            // Token abgelaufen oder nicht vorhanden, neues Token holen
            $tokenUrl = "https://login.microsoftonline.com/$this->graphTenantId/oauth2/v2.0/token";

            try {
                $tokenResponse = Core::getHttpClient()->request('POST', $tokenUrl, [
                    'body' => [
                        'client_id' => $this->graphClientId,
                        'scope' => 'https://graph.microsoft.com/.default',
                        'client_secret' => $this->graphClientSecret,
                        'grant_type' => 'client_credentials',
                    ],
                ]);
                /** @var array{expires_in?: int, access_token?: string} $token */
                $token = $tokenResponse->toArray();
                $token['expires'] = time() + ($token['expires_in'] ?? 3600);

                if (!isset($token['access_token'])) {
                    throw new Exception(I18n::msg('phpmailer_msgraph_no_token'));
                }
                Core::setConfig('phpmailer_msgraph_token', $token);
            } catch (Exception $e) {
                $this->setError(I18n::msg('phpmailer_msgraph_auth_error') . $e->getMessage());
                Core::removeConfig('phpmailer_msgraph_token');
                return false;
            }
        }

        // Mail senden via Microsoft Graph
        $mailUrl = "https://graph.microsoft.com/v1.0/users/$from/sendMail";
        $mailData = [
            'message' => [
                'subject' => $subject,
                'body' => $body,
                'toRecipients' => $to,
                'from' => ['emailAddress' => ['address' => $from]],
            ],
            'saveToSentItems' => true,
        ];
        if (!empty($cc)) {
            $mailData['message']['ccRecipients'] = $cc;
        }
        if (!empty($bcc)) {
            $mailData['message']['bccRecipients'] = $bcc;
        }
        if (count($replyTo) > 0) {
            $mailData['message']['replyTo'] = $replyTo;
        }
        if (!empty($attachments)) {
            $mailData['message']['attachments'] = $attachments;
        }
        if (!empty($customHeaders)) {
            $mailData['message']['internetMessageHeaders'] = $customHeaders;
        }
        if ('' !== $this->ConfirmReadingTo) {
            // MS Graph API unterstützt keine Read-Receipts an beliebige Empfänger
            $mailData['message']['isReadReceiptRequested'] = true;
        }

        if (Core::isDevMode()) {
            // Debug: JSON-Body loggen ins REDAXO-Addon-Data-Verzeichnis
            $debugPath = Path::coreData('phpmailer/graph_mail_debug.json');
            File::put($debugPath, json_encode($mailData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        try {
            $mailResponse = Core::getHttpClient()->request('POST', $mailUrl, [
                'auth_bearer' => $token['access_token'],
                'json' => $mailData,
            ]);
            $statusCode = $mailResponse->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                // `getContent(false)` prevents an exception from being thrown on error status codes
                $this->setError(I18n::msg('phpmailer_msgraph_api_error') . $mailResponse->getContent(false));
                return false;
            }
        } catch (Exception $e) {
            $this->setError(I18n::msg('phpmailer_msgraph_send_error') . $e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * Get archive statistics (size and file count).
     *
     * @return array{size: int, fileCount: int}
     */
    public static function getArchiveStats(): array
    {
        $archiveFolder = self::logFolder();

        if (!is_dir($archiveFolder)) {
            return ['size' => 0, 'fileCount' => 0];
        }

        $archiveSize = 0;
        $fileCount = 0;

        // Use Finder to count all .eml files in subdirectories
        $finder = Finder::factory($archiveFolder)->recursive()->filesOnly();

        foreach ($finder as $file) {
            $archiveSize += $file->getSize();
            if ('eml' === pathinfo($file->getFilename(), PATHINFO_EXTENSION)) {
                ++$fileCount;
            }
        }

        return ['size' => $archiveSize, 'fileCount' => $fileCount];
    }

    /**
     * Get recent archived email files.
     *
     * @param int $limit Maximum number of files to return
     * @return array<string> Array of file paths
     */
    public static function getRecentArchivedFiles(int $limit = 10): array
    {
        $archiveFolder = self::logFolder();

        if (!is_dir($archiveFolder)) {
            return [];
        }

        // Get recent .eml files recursively using Finder
        $finder = Finder::factory($archiveFolder)->recursive()->filesOnly();
        $files = [];

        foreach ($finder as $path => $file) {
            if ('eml' === pathinfo($file->getFilename(), PATHINFO_EXTENSION)) {
                $files[] = $path;
            }
        }

        if (empty($files)) {
            return [];
        }

        // Sort by modification time, newest first
        usort($files, static function (string $a, string $b): int {
            $timeA = filemtime($a);
            $timeB = filemtime($b);
            if (false === $timeA || false === $timeB) {
                return 0;
            }
            return $timeB - $timeA;
        });

        // Return only the requested number of files
        return array_slice($files, 0, $limit);
    }

    /**
     * Parse email headers from .eml file.
     *
     * @param string $filePath Path to .eml file
     * @return array{subject: string, recipient: string, size: int, mtime: int}
     */
    public static function parseEmailHeaders(string $filePath): array
    {
        $subject = I18n::msg('phpmailer_archive_no_subject');
        $recipient = I18n::msg('phpmailer_archive_no_recipient');
        $filesize = filesize($filePath);
        $filemtime = filemtime($filePath);

        // Use File::get() to read file content
        $content = File::get($filePath);
        if ($content) {
            // Split content into lines and process only headers
            $lines = explode("\n", $content);
            $headerLines = 0;

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) {
                    break; // End of headers
                }

                if ($headerLines >= 50) {
                    break; // Limit header processing
                }

                if (0 === stripos($line, 'Subject:')) {
                    $subject = substr($line, 8);
                    // Decode MIME encoded subjects
                    $decodedSubject = iconv_mime_decode($subject, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
                    if (false !== $decodedSubject) {
                        $subject = $decodedSubject;
                    }
                    $subject = trim($subject);
                    $subject = mb_strlen($subject) > 50 ? mb_substr($subject, 0, 50) . '...' : $subject;
                }

                if (0 === stripos($line, 'To:')) {
                    $recipient = trim(substr($line, 3));
                    $recipient = mb_strlen($recipient) > 30 ? mb_substr($recipient, 0, 30) . '...' : $recipient;
                }

                ++$headerLines;
            }
        }

        return [
            'subject' => $subject,
            'recipient' => $recipient,
            'size' => false !== $filesize ? $filesize : 0,
            'mtime' => false !== $filemtime ? $filemtime : 0,
        ];
    }
}

<?php

/**
 * PHPMailer Addon.
 *
 * @author markus[dot]staab[at]redaxo[dot]de Markus Staab
 *
 * @package redaxo\phpmailer
 */

use PHPMailer\PHPMailer\PHPMailer;

class rex_mailer extends PHPMailer
{
    public const LOG_ERRORS = 1;
    public const LOG_ALL = 2;

    public string $graphClientId;
    public string $graphClientSecret;
    public string $graphTenantId;

    /** @var bool */
    private $archive;

    /**
     * used to store information if detour mode is enabled.
     */
    private array $xHeader = [];

    public function __construct($exceptions = false)
    {
        $addon = rex_addon::get('phpmailer');

        self::setLanguage(rex_i18n::getLanguage(), $addon->getPath('vendor/phpmailer/phpmailer/language/'));

        $this->Timeout = 10;
        $this->XMailer = 'REXMailer';
        $this->From = $addon->getConfig('from');
        $this->FromName = $addon->getConfig('fromname');
        $this->ConfirmReadingTo = $addon->getConfig('confirmto');
        $this->Sender = $addon->getConfig('returnto');
        $this->Mailer = $addon->getConfig('mailer');
        $this->Host = $addon->getConfig('host');
        $this->Port = $addon->getConfig('port');
        $this->CharSet = $addon->getConfig('charset');
        $this->WordWrap = $addon->getConfig('wordwrap');
        $this->Encoding = $addon->getConfig('encoding');
        if (0 == $addon->getConfig('priority')) {
            $this->Priority = null;
        } else {
            $this->Priority = $addon->getConfig('priority');
        }
        $this->SMTPDebug = $addon->getConfig('smtp_debug');
        $this->SMTPSecure = $addon->getConfig('smtpsecure');
        $this->SMTPAuth = $addon->getConfig('smtpauth');
        $this->SMTPAutoTLS = $addon->getConfig('security_mode');
        $this->Username = $addon->getConfig('username');
        $this->Password = $addon->getConfig('password');

        $this->graphClientId = $addon->getConfig('msgraph_client_id') ?? '';
        $this->graphClientSecret = $addon->getConfig('msgraph_client_secret') ?? '';
        $this->graphTenantId = $addon->getConfig('msgraph_tenant_id') ?? '';

        if ($bcc = $addon->getConfig('bcc')) {
            $this->addBCC($bcc);
        }
        $this->archive = $addon->getConfig('archive');
        parent::__construct($exceptions);

        rex_extension::registerPoint(new rex_extension_point('PHPMAILER_CONFIG', $this));
    }

    protected function addOrEnqueueAnAddress($kind, $address, $name)
    {
        $addon = rex_addon::get('phpmailer');

        if ($addon->getConfig('detour_mode') && '' != $addon->getConfig('test_address')) {
            if ('to' == $kind) {
                $detourAddress = $addon->getConfig('test_address');

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

    /**
     * @return bool
     */
    public function send()
    {
        return rex_timer::measure(__METHOD__, function () {
            $addon = rex_addon::get('phpmailer');
            $logging = (int) $addon->getConfig('logging');
            $detourModeActive = $addon->getConfig('detour_mode') && '' !== $addon->getConfig('test_address');

            rex_extension::registerPoint(new rex_extension_point('PHPMAILER_PRE_SEND', $this));

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

            rex_extension::registerPoint(new rex_extension_point('PHPMAILER_POST_SEND', $this));

            return true;
        });
    }

    private function prepareDetourMode(): void
    {
        $addon = rex_addon::get('phpmailer');
        $this->clearCCs();
        $this->clearBCCs();

        foreach (['to', 'cc', 'bcc', 'ReplyTo'] as $kind) {
            if (isset($this->xHeader[$kind])) {
                $this->addCustomHeader('x-' . $kind, $this->xHeader[$kind]);
            }
        }

        $this->Subject = $addon->i18n('detour_subject', $this->Subject, $this->xHeader['to']);
        $this->xHeader = []; // Bereinigung für die nächste Verwendung
    }

    /**
     * @return void
     */
    public function clearQueuedAddresses($kind)
    {
        parent::clearQueuedAddresses($kind);

        unset($this->xHeader[$kind]);
    }

    /**
     * @return void
     */
    public function clearAllRecipients()
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

        $log = rex_log_file::factory(self::logFile(), 2_000_000);
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
     * @param bool $status
     *
     * @deprecated use `setArchive` instead
     * @return void
     */
    public function setLog($status)
    {
        $this->setArchive($status);
    }

    /**
     * Enable/disable the mail archive.
     *
     * It overwrites the global `archive` configuration for the current mailer object.
     * @return void
     */
    public function setArchive(bool $status)
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

        rex_file::put($archiveFile, $archivedata);
    }

    /**
     * Path to mail archive folder.
     */
    public static function logFolder(): string
    {
        return rex_path::addonData('phpmailer', 'mail_log');
    }

    /**
     * Path to log file.
     */
    public static function logFile(): string
    {
        return rex_path::log('mail.log');
    }

    /**
     * @internal
     */
    public static function errorMail(): void
    {
        $addon = rex_addon::get('phpmailer');
        $logFile = rex_path::log('system.log');
        $lastSendTime = (int) $addon->getConfig('last_log_file_send_time', 0);
        $lastErrors = (string) $addon->getConfig('last_errors', '');
        $currentErrors = '';

        // Check if the log file has content
        if (!filesize($logFile)) {
            return;
        }

        $file = rex_log_file::factory($logFile);
        $logevent = false;

        // Start - generate mail body
        $mailBody = '<h2>Error protocol for: ' . rex::getServerName() . '</h2>';
        $mailBody .= '<style nonce="' . rex_response::getNonce() . '"> .errorbg {background: #F6C4AF; } .eventbg {background: #E1E1E1; } td, th {padding: 5px;} table {width: 100%; border: 1px solid #ccc; } th {background: #b00; color: #fff;} td { border: 0; border-bottom: 1px solid #b00;} </style> ';
        $mailBody .= '<table>';
        $mailBody .= '    <thead>';
        $mailBody .= '        <tr>';
        $mailBody .= '            <th>' . rex_i18n::msg('syslog_timestamp') . '</th>';
        $mailBody .= '            <th>' . rex_i18n::msg('syslog_type') . '</th>';
        $mailBody .= '            <th>' . rex_i18n::msg('syslog_message') . '</th>';
        $mailBody .= '            <th>' . rex_i18n::msg('syslog_file') . '</th>';
        $mailBody .= '            <th>' . rex_i18n::msg('syslog_line') . '</th>';
        $mailBody .= '            <th>' . rex_i18n::msg('syslog_url') . '</th>';
        $mailBody .= '        </tr>';
        $mailBody .= '    </thead>';
        $mailBody .= '    <tbody>';

        $errorCount = 0;
        $maxErrors = 30; // Maximum number of errors to process

        /** @var rex_log_entry $entry */
        foreach (new LimitIterator($file, 0, $maxErrors) as $entry) {
            $data = $entry->getData();
            $time = rex_formatter::intlDateTime($entry->getTimestamp(), [IntlDateFormatter::SHORT, IntlDateFormatter::MEDIUM]);
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
        $errorMailInterval = (int) $addon->getConfig('errormail');

        if ($timeSinceLastSend < $errorMailInterval && $currentErrorsHash === $lastErrors) {
            return;
        }

        // Send email
        $mail = new self();
        $mail->Subject = rex::getServerName() . ' - Error Report';
        $mail->Body = $mailBody;
        $mail->AltBody = strip_tags($mailBody);
        $mail->FromName = 'REDAXO Error Report';
        $mail->addAddress(rex::getErrorEmail());

        // Set X-Mailer header for ErrorMails
        $mail->XMailer = 'REDAXO/' . rex::getVersion() . ' ErrorMailer';

        if ($mail->Send()) {
            // Update configuration only if email was sent successfully
            $addon->setConfig('last_errors', $currentErrorsHash);
            $addon->setConfig('last_log_file_send_time', time());
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
            $name = $att[2] ?: rex_path::basename($file);
            $type = $att[4] ?: 'application/octet-stream';
            $isString = $att[5] ?? false;
            $content = $isString ? $file : rex_file::get($file);
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
        $token = rex_config::get('phpmailer', 'msgraph_token');
        if (!isset($token['access_token']) || $token['expires'] - 300 < time()) {
            // Token abgelaufen oder nicht vorhanden, neues Token holen
            $tokenUrl = "https://login.microsoftonline.com/$this->graphTenantId/oauth2/v2.0/token";
            $tokenSocket = rex_socket::factoryUrl($tokenUrl);
            $tokenSocket->addHeader('Content-Type', 'application/x-www-form-urlencoded');
            $tokenData = [
                'client_id' => $this->graphClientId,
                'scope' => 'https://graph.microsoft.com/.default',
                'client_secret' => $this->graphClientSecret,
                'grant_type' => 'client_credentials',
            ];

            try {
                $tokenResponse = $tokenSocket->doPost($tokenData);
                /** @var array{expires_in?: int, access_token?: string} $token */
                $token = json_decode($tokenResponse->getBody(), true);
                $token['expires'] = time() + ($token['expires_in'] ?? 3600);

                if (!isset($token['access_token'])) {
                    throw new Exception(rex_i18n::msg('phpmailer_msgraph_no_token'));
                }
                rex_config::set('phpmailer', 'msgraph_token', $token);
            } catch (Exception $e) {
                $this->setError(rex_i18n::msg('phpmailer_msgraph_auth_error') . $e->getMessage());
                rex_config::remove('phpmailer', 'msgraph_token');
                return false;
            }
        }

        // Mail senden via rex_socket
        $mailUrl = "https://graph.microsoft.com/v1.0/users/$from/sendMail";
        $mailSocket = rex_socket::factoryUrl($mailUrl);
        $mailSocket->addHeader('Authorization', 'Bearer ' . $token['access_token']);
        $mailSocket->addHeader('Content-Type', 'application/json');
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

        if (rex::isDebugMode()) {
            // Debug: JSON-Body loggen ins REDAXO-Addon-Data-Verzeichnis
            $debugPath = rex_path::addonData('phpmailer', 'graph_mail_debug.json');
            rex_file::put($debugPath, json_encode($mailData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        try {
            $mailResponse = $mailSocket->doPost(json_encode($mailData));
            if (!$mailResponse->isSuccessful()) {
                $this->setError(rex_i18n::msg('phpmailer_msgraph_api_error') . $mailResponse->getBody());
                return false;
            }
        } catch (Exception $e) {
            $this->setError(rex_i18n::msg('phpmailer_msgraph_send_error') . $e->getMessage());
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

        // Use rex_finder to count all .eml files in subdirectories
        $finder = rex_finder::factory($archiveFolder)->recursive()->filesOnly();

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

        // Get recent .eml files recursively using rex_finder
        $finder = rex_finder::factory($archiveFolder)->recursive()->filesOnly();
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
        $addon = rex_addon::get('phpmailer');
        $subject = $addon->i18n('archive_no_subject');
        $recipient = $addon->i18n('archive_no_recipient');
        $filesize = filesize($filePath);
        $filemtime = filemtime($filePath);

        // Use rex_file::get() to read file content
        $content = rex_file::get($filePath);
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

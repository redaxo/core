<?php

namespace Redaxo\Core\Mailer;

use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Finder;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Translation\I18n;

use function array_slice;

use const ICONV_MIME_DECODE_CONTINUE_ON_ERROR;
use const PATHINFO_EXTENSION;

/**
 * Archive of the sent mails as `.eml` files, enabled by the env var `REX_MAILER_ARCHIVE`.
 *
 * @internal
 */
final class MailArchive
{
    private function __construct() {}

    public static function folder(): string
    {
        return Path::coreData('mailer/archive');
    }

    public static function add(string $content, bool $sent = true): void
    {
        $dir = self::folder() . '/' . date('Y') . '/' . date('m');
        $prefix = $sent ? '' : 'not_sent_';
        $count = 1;
        $file = $dir . '/' . $prefix . date('Y-m-d_H_i_s') . '.eml';
        while (is_file($file)) {
            $file = $dir . '/' . $prefix . date('Y-m-d_H_i_s') . '_' . (++$count) . '.eml';
        }

        File::put($file, $content);
    }

    /**
     * Returns the size and the number of mails of the archive.
     *
     * @return array{size: int, fileCount: int}
     */
    public static function getStats(): array
    {
        $folder = self::folder();

        if (!is_dir($folder)) {
            return ['size' => 0, 'fileCount' => 0];
        }

        $size = 0;
        $fileCount = 0;

        foreach (Finder::factory($folder)->recursive()->filesOnly() as $file) {
            $size += $file->getSize();
            if ('eml' === pathinfo($file->getFilename(), PATHINFO_EXTENSION)) {
                ++$fileCount;
            }
        }

        return ['size' => $size, 'fileCount' => $fileCount];
    }

    /**
     * Returns the paths of the most recently archived mails, newest first.
     *
     * @return list<string>
     */
    public static function getRecentFiles(int $limit = 10): array
    {
        $folder = self::folder();

        if (!is_dir($folder)) {
            return [];
        }

        $files = [];
        foreach (Finder::factory($folder)->recursive()->filesOnly() as $path => $file) {
            if ('eml' === pathinfo($file->getFilename(), PATHINFO_EXTENSION)) {
                $files[] = $path;
            }
        }

        usort($files, static fn (string $a, string $b): int => (int) filemtime($b) <=> (int) filemtime($a));

        return array_slice($files, 0, $limit);
    }

    /**
     * Parses the subject and the recipient from the headers of the archived mail.
     *
     * @return array{subject: string, recipient: string, size: int, mtime: int}
     */
    public static function parseHeaders(string $file): array
    {
        $subject = I18n::msg('mailer_archive_no_subject');
        $recipient = I18n::msg('mailer_archive_no_recipient');

        $content = File::get($file);
        if ($content) {
            foreach (array_slice(explode("\n", $content), 0, 50) as $line) {
                $line = trim($line);
                if ('' === $line) {
                    break; // end of headers
                }

                if (0 === stripos($line, 'Subject:')) {
                    $subject = substr($line, 8);
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
            }
        }

        return [
            'subject' => $subject,
            'recipient' => $recipient,
            'size' => (int) filesize($file),
            'mtime' => (int) filemtime($file),
        ];
    }
}

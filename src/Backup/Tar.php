<?php

namespace Redaxo\Core\Backup;

use splitbrain\PHPArchive\Archive;
use splitbrain\PHPArchive\Tar as BaseTar;

use const E_DEPRECATED;

/**
 * REDAXO Tar Klasse.
 *
 * Diese Subklasse fixed ein paar Bugs gegenüber der
 * original Implementierung und erhoeht die Performanz
 *
 * @internal
 */
final readonly class Tar
{
    private BaseTar $tar;

    // constructor to omit warnings
    public function __construct()
    {
        $this->tar = new BaseTar();
    }

    /**
     * Open a TAR file.
     *
     * @param string $filename
     */
    public function openTAR($filename): bool
    {
        // If the tar file doesn't exist...
        if (!is_file($filename)) {
            return false;
        }

        $this->tar->open($filename);

        return true;
    }

    /** @param string $archivePath */
    public function create($archivePath): void
    {
        $this->tar->create($archivePath);
        $this->tar->setCompression(9, Archive::COMPRESS_GZIP);
    }

    /**
     * Add a file to the tar archive.
     *
     * @param string $filename
     */
    public function addFile($filename): bool
    {
        // Make sure the file we are adding exists!
        if (!is_file($filename)) {
            return false;
        }

        $this->tar->addFile($filename);

        return true;
    }

    public function close(): void
    {
        $this->tar->close();
    }

    /**
     * Extract an existing TAR archive.
     *
     * @param string $outdir the target directory for extracting
     */
    public function extractTar($outdir): bool
    {
        // when extracting tars generated with our previous tar class
        // some E_DEPRECATED messages are triggered by `octdec()`:
        // "Invalid characters passed for attempted conversion, these have been ignored"
        $errorReporting = error_reporting(error_reporting() ^ E_DEPRECATED);

        try {
            $this->tar->extract($outdir);
        } finally {
            error_reporting($errorReporting);
        }

        return true;
    }
}

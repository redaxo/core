<?php

/**
 * @package redaxo\install
 *
 * @internal
 */
abstract class rex_install_package_download
{
    /** @var non-empty-string */
    protected $addonkey;

    /** @var int */
    protected $fileId;

    /** @var array{version: string, description: string, path: string, checksum: string, counter: int, created: string, updated: string} */
    protected $file;

    /** @var string */
    protected $archive;

    /** @param non-empty-string $addonkey */
    public function run(string $addonkey, int $fileId): string
    {
        $this->addonkey = rex_path::basename($addonkey); // the addonkey is used in file paths
        $this->fileId = $fileId;

        $packages = $this->getPackages();
        if (!isset($packages[$this->addonkey]['files'][$this->fileId])) {
            throw new rex_functional_exception('The requested addon version can not be loaded, maybe it is already installed.');
        }
        $this->file = $packages[$this->addonkey]['files'][$this->fileId];
        $this->checkPreConditions();

        $archivefile = rex_install_webservice::getArchive($this->file['path']);

        $message = '';
        $this->archive = $archivefile;

        try {
            if ($this->file['checksum'] != md5_file($archivefile)) {
                $message = rex_i18n::msg('install_warning_zip_wrong_checksum');
            } elseif (!$this->isCorrectFormat($archivefile)) {
                $message = rex_i18n::msg('install_warning_zip_wrong_format');
            } elseif (is_string($msg = $this->doAction())) {
                $message = $msg;
            }
        } finally {
            rex_file::delete($archivefile);
        }

        return $message;
    }

    /**
     * @param string $dir
     * @return string|true
     */
    protected function extractArchiveTo($dir)
    {
        if (!rex_install_archive::extract($this->archive, $dir, $this->addonkey)) {
            rex_dir::delete($dir);
            return rex_i18n::msg('install_warning_addon_zip_not_extracted');
        }
        return true;
    }

    /** @return array<string, array{name: string, author: string, shortdescription: string, description: string, website: string, counter: int, created: string, updated: string, files: array<int, array{version: string, description: string, path: string, checksum: string, counter: int, created: string, updated: string}>}> */
    abstract protected function getPackages();

    /** @return void */
    abstract protected function checkPreConditions();

    /** @return string|null */
    abstract protected function doAction();

    private function isCorrectFormat(string $file): bool
    {
        if (class_exists(ZipArchive::class)) {
            $success = false;
            $zip = new ZipArchive();
            if (true === $zip->open($file)) {
                for ($i = 0; $i < $zip->numFiles; ++$i) {
                    $filename = $zip->getNameIndex($i);
                    if (!str_starts_with($filename, $this->addonkey . '/')) {
                        $zip->deleteIndex($i);
                    } else {
                        $success = true;
                    }
                }
                $zip->close();
            }
            return $success;
        }

        return is_dir("phar://$file" . DIRECTORY_SEPARATOR . $this->addonkey);
    }
}

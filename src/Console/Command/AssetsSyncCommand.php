<?php

namespace Redaxo\Core\Console\Command;

use Override;
use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Filesystem\Dir;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Finder;
use Redaxo\Core\Filesystem\Path;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function sprintf;

/**
 * @internal
 */
#[AsCommand(name: 'assets:sync', description: 'Sync assets within the assets-dir with the sources-dir')]
class AssetsSyncCommand extends AbstractCommand
{
    #[Override]
    protected function configure(): void
    {
        $this
            ->setHelp(sprintf(
                'Sync folders and files of /%s with source assets folders',
                rtrim(Path::relative(Path::assets()), '/'),
            ))
        ;
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $created = $updated = $errored = 0;
        $io = $this->getStyle($input, $output);

        foreach (Addon::getInstalledAddons() as $package) {
            $assetsPublicPath = $package->getAssetsPath();
            $assetsSrcPath = $package->getPath('assets/');

            if (!is_dir($assetsPublicPath) && !is_dir($assetsSrcPath)) {
                continue;
            }
            if (!is_dir($assetsPublicPath)) {
                Dir::create($assetsPublicPath);
            }
            if (!is_dir($assetsSrcPath)) {
                Dir::create($assetsSrcPath);
            }

            // sync 1st way, copies ...
            // - existing in FE but not "src"
            // - newer in FE than "src"
            [$ctd, $upd, $err] = $this->sync($io, $assetsPublicPath, $assetsSrcPath);
            $created += $ctd;
            $updated += $upd;
            $errored += $err;

            // sync 2nd way, copies ...
            // - existing in "src" but not FE
            // - newer in "src" than FE
            [$ctd, $upd, $err] = $this->sync($io, $assetsSrcPath, $assetsPublicPath);
            $created += $ctd;
            $updated += $upd;
            $errored += $err;
        }

        $assetsPublicPath = Path::coreAssets();
        $assetsSrcPath = Path::core('assets/');
        if (!is_dir($assetsPublicPath)) {
            Dir::create($assetsPublicPath);
        }
        if (!is_dir($assetsSrcPath)) {
            Dir::create($assetsSrcPath);
        }

        [$ctd, $upd, $err] = $this->sync($io, $assetsPublicPath, $assetsSrcPath);
        $created += $ctd;
        $updated += $upd;
        $errored += $err;

        [$ctd, $upd, $err] = $this->sync($io, $assetsSrcPath, $assetsPublicPath);
        $created += $ctd;
        $updated += $upd;
        $errored += $err;

        if (0 === $errored) {
            $io->success(sprintf('Created %s and updated %s file(s).', $created, $updated));
            return Command::SUCCESS;
        }

        $io->error(sprintf('Created %s, updated %s file(s) while running into %s errors.', $created, $updated, $errored));
        return Command::FAILURE;
    }

    /** @return list{int, int, int} */
    private function sync(SymfonyStyle $io, string $folder1, string $folder2): array
    {
        $created = $updated = $errored = 0;

        // normalize paths
        $folder1 = realpath($folder1);
        $folder2 = realpath($folder2);

        $finder = Finder::factory($folder1)
            ->recursive()
            ->ignoreFiles('.redaxo')
            ->filesOnly();

        foreach ($finder as $f1Fileinfo) {
            $f1File = (string) $f1Fileinfo;
            $relativePath = str_replace($folder1, '', $f1File);
            $f2File = $folder2 . $relativePath;

            $f1FileShort = Path::relative($f1File);
            $f2FileShort = Path::relative($f2File);

            $hasError = false;

            if (!is_readable($f1File)) {
                ++$errored;
                $hasError = true;
                $io->text("<error>Not readable:</error> <comment>$f1FileShort</comment>");
            }
            if (is_file($f2File) && !is_writable($f2File)) {
                ++$errored;
                $hasError = true;
                $io->text("<error>Not writable:</error> <comment>$f2FileShort</comment>");
            }

            if ($hasError) {
                continue;
            }

            if (!is_file($f2File)) {
                File::copy($f1File, $f2File);
                ++$created;
                if ($io->isVerbose()) {
                    $io->text("Created <comment>$f2FileShort</comment>");
                }

                continue;
            }

            if ($f1Fileinfo->getMtime() > filemtime($f2File) && md5_file($f1File) !== md5_file($f2File)) {
                File::copy($f1File, $f2File);
                ++$updated;
                if ($io->isVerbose()) {
                    $io->text("Updated <comment>$f2FileShort</comment>");
                }
            }
            // else: $f2 is equal or newer, so no sync in this direction
        }

        return [$created, $updated, $errored];
    }
}

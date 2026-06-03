<?php

namespace Redaxo\Core\Cronjob;

use Redaxo\Core\Core;
use Redaxo\Core\Cronjob\Type\AbstractType;
use Redaxo\Core\Cronjob\Type\ArticleStatusType;
use Redaxo\Core\Cronjob\Type\ClearArticleHistoryType;
use Redaxo\Core\Cronjob\Type\ExportType;
use Redaxo\Core\Cronjob\Type\OptimizeTableType;
use Redaxo\Core\Cronjob\Type\PurgeMailerArchiveType;
use Redaxo\Core\Cronjob\Type\UrlRequestType;
use Redaxo\Core\Environment;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Log\LogFile;
use Redaxo\Core\Util\Str;
use Throwable;

use function defined;
use function Redaxo\Core\View\escape;

/** @internal */
final class CronjobExecutor
{
    /** @var list<class-string<AbstractType>>|null */
    private static ?array $types = null;

    public string $message = '';

    /** @var AbstractType|class-string<AbstractType>|null */
    private AbstractType|string|null $cronjob = null;

    private ?string $name = null;
    private ?int $id = null;

    public static function factory(): self
    {
        return new self();
    }

    /** @param AbstractType|class-string<AbstractType> $cronjob */
    public function setCronjob(AbstractType|string $cronjob): void
    {
        $this->cronjob = $cronjob;
    }

    /** @param AbstractType|class-string<AbstractType> $cronjob */
    public function tryExecute(AbstractType|string $cronjob, string $name = '', array $params = [], bool $log = true, ?int $id = null): bool
    {
        if (!$cronjob instanceof AbstractType) {
            $success = false;
            $message = 'Class "' . $cronjob . '" not found';
        } else {
            $this->name = $name;
            $this->id = $id;
            $this->cronjob = $cronjob;
            $type = Str::normalize($cronjob::class);
            foreach ($params as $key => $value) {
                $cronjob->setParam(str_replace($type . '_', '', $key), $value);
            }

            try {
                $success = $cronjob->execute();
                $message = $cronjob->message;
            } catch (Throwable $t) {
                $success = false;
                $message = $t->getMessage();
            }

            if ('' == $message && !$success) {
                $message = 'Unknown error';
            }
        }

        if ($log) {
            $this->log($success, $message);
        }

        $this->message = escape($message);
        $this->cronjob = null;
        $this->id = null;

        return $success;
    }

    public function log(bool $success, string $message): void
    {
        $name = $this->name;
        if (!$name) {
            if ($this->cronjob instanceof AbstractType) {
                $name = Core::isBackend() ? $this->cronjob->getTypeName() : $this->cronjob::class;
            } else {
                $name = '[no name]';
            }
        }

        if (Environment::Backend === Core::getEnvironment() && 'cronjob/cronjobs' == Request::get('page') && 'execute' == Request::get('func')) {
            $environment = 'backend_manual';
        } else {
            $environment = Core::getEnvironment()->value;
        }

        $log = LogFile::factory(Path::log('cronjob.log'), 2_000_000);
        $data = [
            $success ? 'SUCCESS' : 'ERROR',
            $this->id ?: '--',
            $name,
            strip_tags($message),
            $environment,
        ];
        $log->add($data);
    }

    /** @return list<class-string<AbstractType>> */
    public static function getTypes(): array
    {
        if (null === self::$types) {
            self::$types = [];

            self::$types[] = UrlRequestType::class;
            self::$types[] = ExportType::class;
            self::$types[] = OptimizeTableType::class;
            self::$types[] = ArticleStatusType::class;
            self::$types[] = ClearArticleHistoryType::class;
            self::$types[] = PurgeMailerArchiveType::class;
        }

        return self::$types;
    }

    /** @param class-string<AbstractType> $class */
    public static function registerType(string $class): void
    {
        $types = self::getTypes();
        $types[] = $class;
        self::$types = $types;
    }

    public static function getCurrentEnvironment(): string
    {
        if (defined('REX_CRONJOB_SCRIPT') && REX_CRONJOB_SCRIPT) {
            return 'script';
        }

        return Core::isBackend() ? Environment::Backend->value : Environment::Frontend->value;
    }
}

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
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Log\LogFile;
use Redaxo\Core\Util\Str;
use Throwable;

use function defined;
use function is_object;
use function Redaxo\Core\View\escape;

class CronjobExecutor
{
    /** @var list<class-string<AbstractType>>|null */
    private static ?array $types = null;

    /** @var string */
    private $message = '';
    /** @var AbstractType|class-string<AbstractType>|null */
    private $cronjob;
    /** @var string|null */
    private $name;
    /** @var int|null */
    private $id;

    /** @return self */
    public static function factory()
    {
        return new self();
    }

    /**
     * @param string $message
     * @return void
     */
    public function setMessage($message)
    {
        $this->message = $message;
    }

    /** @return string */
    public function getMessage()
    {
        return $this->message;
    }

    /** @return bool */
    public function hasMessage()
    {
        return !empty($this->message);
    }

    /**
     * @api
     * @param AbstractType|class-string<AbstractType> $cronjob
     * @return void
     */
    public function setCronjob($cronjob)
    {
        $this->cronjob = $cronjob;
    }

    /**
     * @param AbstractType|class-string<AbstractType> $cronjob
     * @param string $name
     * @param array $params
     * @param bool $log
     * @param int|null $id
     * @return bool
     */
    public function tryExecute($cronjob, $name = '', $params = [], $log = true, $id = null)
    {
        if (!$cronjob instanceof AbstractType) {
            $success = false;
            if (is_object($cronjob)) {
                $message = 'Invalid cronjob class "' . $cronjob::class . '"';
            } else {
                $message = 'Class "' . $cronjob . '" not found';
            }
        } else {
            $this->name = $name;
            $this->id = $id;
            $this->cronjob = $cronjob;
            $type = Str::normalize($cronjob->getType());
            foreach ($params as $key => $value) {
                $cronjob->setParam(str_replace($type . '_', '', $key), $value);
            }

            try {
                $success = $cronjob->execute();
                $message = $cronjob->getMessage();
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

        $this->setMessage(escape($message));
        $this->cronjob = null;
        $this->id = null;

        return $success;
    }

    /**
     * @param bool $success
     * @param string $message
     * @return void
     */
    public function log($success, $message)
    {
        $name = $this->name;
        if (!$name) {
            if ($this->cronjob instanceof AbstractType) {
                $name = Core::isBackend() ? $this->cronjob->getTypeName() : $this->cronjob->getType();
            } else {
                $name = '[no name]';
            }
        }

        if ('backend' === Core::getEnvironment() && 'cronjob/cronjobs' == Request::get('page') && 'execute' == Request::get('func')) {
            $environment = 'backend_manual';
        } else {
            $environment = Core::getEnvironment();
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
    public static function getTypes()
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

    /**
     * @param class-string<AbstractType> $class
     * @return void
     */
    public static function registerType($class)
    {
        $types = self::getTypes();
        $types[] = $class;
        self::$types = $types;
    }

    /** @return string */
    public static function getCurrentEnvironment()
    {
        if (defined('REX_CRONJOB_SCRIPT') && REX_CRONJOB_SCRIPT) {
            return 'script';
        }

        return Core::isBackend() ? 'backend' : 'frontend';
    }
}

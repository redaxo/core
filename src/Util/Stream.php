<?php

namespace Redaxo\Core\Util;

use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;

use function extension_loaded;
use function ini_get;
use function is_string;
use function strlen;

use const SEEK_CUR;
use const SEEK_END;
use const SEEK_SET;

/**
 * Stream wrapper to include variables like files (php code will be evaluated).
 *
 * Example:
 * <code>
 * <?php
 *   include Stream::factory('myContent', '<?php echo "Hello World"; ?>');
 * ?>
 * </code>
 *
 * @see https://www.php.net/manual/en/class.streamwrapper.php
 */
class Stream
{
    private static ?bool $useRealFiles = null;
    private static bool $registered = false;

    /** @var array<string, string> */
    private static array $nextContent = [];

    private int $position = 0;
    private string $content = '';

    /**
     * @var resource|null
     * @see https://www.php.net/manual/en/class.streamwrapper.php#streamwrapper.props.context
     */
    public $context;

    /**
     * Prepares a new stream.
     *
     * @param string $path Virtual path which should describe the content (e.g. "template/1"), only relevant for error messages
     * @param string $content Content which will be included
     * @return string Full path with protocol (e.g. "rex:///template/1")
     *
     * @psalm-taint-specialize
     */
    public static function factory(string $path, string $content): string
    {
        if (empty($path)) {
            throw new InvalidArgumentException('Expecting $path to be a string and not empty.');
        }

        if (null === self::$useRealFiles) {
            self::$useRealFiles = extension_loaded('suhosin')
                && !preg_match('/(?:^|,)rex(?::|,|$)/', ini_get('suhosin.executor.include.whitelist'));
        }

        if (self::$useRealFiles) {
            $hash = substr(sha1($content), 0, 7);
            $path = Path::coreCache('stream/' . $path . '/' . $hash);

            if (!is_file($path)) {
                File::put($path, $content);
            }

            return $path;
        }

        if (!self::$registered) {
            stream_wrapper_register('rex', self::class);
            self::$registered = true;
        }

        // 3 slashes needed to sidestep some server url include protections
        // example: https://www.strato.de/faq/article/622/Warum-erhalte-ich-über-PHP-die-Fehlermeldung-%22Warning:-main()-…:-include(….).html
        $path = 'rex:///' . $path;
        self::$nextContent[$path] = $content;

        return $path;
    }

    /** @see https://www.php.net/manual/en/streamwrapper.stream-open.php */
    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        if (!isset(self::$nextContent[$path]) || !is_string(self::$nextContent[$path])) {
            return false;
        }

        $this->position = 0;
        $this->content = self::$nextContent[$path];
        // unset(self::$nextContent[$path]);

        return true;
    }

    /** @see https://www.php.net/manual/en/streamwrapper.stream-read.php */
    public function stream_read(int $count): string
    {
        $ret = substr($this->content, $this->position, $count);
        $this->position += strlen($ret);
        return $ret;
    }

    /** @see https://www.php.net/manual/en/streamwrapper.stream-eof.php */
    public function stream_eof(): bool
    {
        return $this->position >= strlen($this->content);
    }

    /** @see https://www.php.net/manual/en/streamwrapper.stream-seek.php */
    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        switch ($whence) {
            case SEEK_SET:
                $this->position = $offset;
                return true;
            case SEEK_CUR:
                $this->position += $offset;
                return true;
            case SEEK_END:
                $this->position = strlen($this->content) - 1 + $offset;
                return true;
            default:
                return false;
        }
    }

    /** @see https://www.php.net/manual/en/streamwrapper.stream-set-option.php */
    public function stream_set_option(): bool
    {
        return false;
    }

    /** @see https://www.php.net/manual/en/streamwrapper.stream-tell.php */
    public function stream_tell(): int
    {
        return $this->position;
    }

    /** @see https://www.php.net/manual/en/streamwrapper.stream-flush.php */
    public function stream_flush(): bool
    {
        return true;
    }

    /**
     * @see https://www.php.net/manual/en/streamwrapper.stream-stat.php
     * @return null
     */
    public function stream_stat()
    {
        return null;
    }

    /**
     * @see https://www.php.net/manual/en/streamwrapper.url-stat.php
     * @return null
     */
    public function url_stat()
    {
        return null;
    }
}

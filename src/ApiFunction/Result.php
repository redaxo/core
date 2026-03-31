<?php

namespace Redaxo\Core\ApiFunction;

use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Util\Type;
use Redaxo\Core\View\Message;

use function is_array;

/**
 * Class representing the result of an api function call.
 *
 * @see ApiFunction
 */
class Result
{
    /**
     * Flag indicating whether the result of this api call needs to be rendered in a new sub-request.
     * This is required in rare situations, when some low-level data was changed by the api-function.
     *
     * @var bool
     */
    private $requiresReboot;

    /**
     * @param bool $succeeded flag indicating if the api function was executed successfully
     * @param string|null $message optional message which will be visible to the end-user
     */
    public function __construct(
        private $succeeded,
        private $message = null,
    ) {}

    /**
     * @param bool $requiresReboot
     * @return void
     */
    public function setRequiresReboot($requiresReboot)
    {
        $this->requiresReboot = $requiresReboot;
    }

    /** @return bool */
    public function requiresReboot()
    {
        return $this->requiresReboot;
    }

    /** @return string|null */
    public function getFormattedMessage()
    {
        if (null === $this->message) {
            return null;
        }

        if ($this->isSuccessfull()) {
            return Message::success($this->message);
        }
        return Message::error($this->message);
    }

    /**
     * Returns end-user friendly statusmessage.
     *
     * @return string|null a statusmessage
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Returns whether the api function was executed successfully.
     *
     * @return bool true on success, false on error
     */
    public function isSuccessfull()
    {
        return $this->succeeded;
    }

    /** @return string */
    public function toJSON()
    {
        return Type::string(json_encode([
            'succeeded' => $this->succeeded,
            'message' => $this->message,
        ]));
    }

    /**
     * @param string $json
     * @return self
     */
    public static function fromJSON($json)
    {
        $json = json_decode($json, true);

        if (!is_array($json)) {
            throw new InvalidArgumentException('Unable to decode json into an array.');
        }

        return new self(
            Type::bool($json['succeeded'] ?? null),
            Type::nullOrString($json['message'] ?? null),
        );
    }
}

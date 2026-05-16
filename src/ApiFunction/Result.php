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
final readonly class Result
{
    /**
     * @param bool $succeeded flag indicating if the api function was executed successfully
     * @param string|null $message optional message which will be visible to the end-user
     * @param bool $requiresReboot flag indicating whether the result of this api call needs to be rendered in a new sub-request.
     *     This is required in rare situations, when some low-level data was changed by the api-function.
     *
     * @psalm-taint-sink html $message
     */
    public function __construct(
        public bool $succeeded,
        public ?string $message = null,
        public bool $requiresReboot = false,
    ) {}

    public function getFormattedMessage(): ?string
    {
        if (null === $this->message) {
            return null;
        }

        if ($this->succeeded) {
            return Message::success($this->message);
        }
        return Message::error($this->message);
    }

    public function toJson(): string
    {
        return Type::string(json_encode([
            'succeeded' => $this->succeeded,
            'message' => $this->message,
        ]));
    }

    public static function fromJson(string $json): self
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

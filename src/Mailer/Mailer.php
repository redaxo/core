<?php

namespace Redaxo\Core\Mailer;

use Redaxo\Core\Core;
use Redaxo\Core\Env;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Log\LogFile;
use Redaxo\Core\Util\Timer;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;

use function sprintf;

/**
 * Sends mails via the transport defined by the env var `MAILER_DSN`, available via `Core::getMailer()`.
 *
 * Mails are built with `Symfony\Component\Mime\Email`. Mails without a `From` header get the address from
 * `REX_MAILER_FROM`. If `REX_MAILER_RECIPIENTS` is set, all mails are delivered to these addresses instead of the
 * actual recipients.
 */
final readonly class Mailer implements MailerInterface
{
    /**
     * @param list<Address> $detourRecipients
     * @internal
     */
    public function __construct(
        /** @internal */
        public TransportInterface $transport,
        /** @internal */
        public ?Address $defaultFrom = null,
        /** @internal */
        public array $detourRecipients = [],
        /** @internal */
        public MailLogMode $logMode = MailLogMode::Errors,
        /** @internal */
        public bool $archive = false,
    ) {}

    /** @internal */
    public static function fromEnv(): self
    {
        $transport = Transport::fromDsn(Env::require('MAILER_DSN'), client: Core::getHttpClient());

        $from = Env::get('REX_MAILER_FROM');
        $recipients = Env::get('REX_MAILER_RECIPIENTS');

        $logMode = Env::get('REX_MAILER_LOG');
        if (null === $logMode) {
            $logMode = MailLogMode::Errors;
        } else {
            $logMode = MailLogMode::tryFrom($logMode) ?? throw new InvalidArgumentException(sprintf('The env var "REX_MAILER_LOG" must be one of "none", "errors" or "all", "%s" given.', $logMode));
        }

        return new self(
            $transport,
            defaultFrom: null === $from ? null : Address::create($from),
            detourRecipients: null === $recipients ? [] : array_map(static fn (string $address) => Address::create(trim($address)), explode(',', $recipients)),
            logMode: $logMode,
            archive: Env::getBool('REX_MAILER_ARCHIVE'),
        );
    }

    /**
     * Sends the message, a `TransportExceptionInterface` is thrown if it could not be sent.
     *
     * The extension points `MAILER_PRE_SEND` (the message can still be modified there), `MAILER_SENT` and
     * `MAILER_FAILED` are dispatched while sending.
     *
     * @param bool|null $archive Overrides the `REX_MAILER_ARCHIVE` setting for this message
     */
    public function send(RawMessage $message, ?Envelope $envelope = null, ?bool $archive = null): void
    {
        Timer::measure(__METHOD__, function () use ($message, $envelope, $archive): void {
            $archive ??= $this->archive;

            if ($message instanceof Message && null !== $this->defaultFrom && !$message->getHeaders()->has('From')) {
                $message->getHeaders()->addMailboxListHeader('From', [$this->defaultFrom]);
            }

            Extension::dispatch(new ExtensionPoint('MAILER_PRE_SEND', $message, ['envelope' => $envelope], true));

            // the detour is applied after the extension point, so that no listener can bypass it
            $messageToSend = $message;
            if ($this->detourRecipients) {
                [$messageToSend, $envelope] = $this->detour($message, $envelope);
            }

            try {
                $sentMessage = $this->transport->send($messageToSend, $envelope);
            } catch (TransportExceptionInterface $exception) {
                if (MailLogMode::None !== $this->logMode) {
                    $this->log('ERROR', $message, $envelope, $exception->getMessage());
                }
                if ($archive) {
                    MailArchive::add($messageToSend->toString(), sent: false);
                }

                Extension::dispatch(new ExtensionPoint('MAILER_FAILED', $message, ['envelope' => $envelope, 'exception' => $exception], true));

                throw $exception;
            }

            if ($archive) {
                MailArchive::add($sentMessage?->toString() ?? $messageToSend->toString());
            }
            if (MailLogMode::All === $this->logMode) {
                $this->log('OK', $message, $envelope);
            }

            if ($sentMessage instanceof SentMessage) {
                Extension::dispatch(new ExtensionPoint('MAILER_SENT', $sentMessage, [], true));
            }
        });
    }

    /**
     * Delivers the message to the detour recipients only.
     *
     * Besides the envelope, the `Cc` and `Bcc` headers are removed as well (kept as `X-Original-*` headers), because
     * the API transports of the mailer bridges read them from the message instead of the envelope.
     *
     * @return array{RawMessage, Envelope}
     */
    private function detour(RawMessage $message, ?Envelope $envelope): array
    {
        if ($message instanceof Message) {
            $message = clone $message;
            $headers = $message->getHeaders();
            foreach (['Cc', 'Bcc'] as $name) {
                $header = $headers->get($name);
                if (null !== $header) {
                    $headers->addTextHeader('X-Original-' . $name, $header->getBodyAsString());
                    $headers->remove($name);
                }
            }
        }

        $envelope = null === $envelope ? Envelope::create($message) : clone $envelope;
        $envelope->setRecipients($this->detourRecipients);

        return [$message, $envelope];
    }

    /**
     * Path to the log file.
     *
     * @internal
     */
    public static function logFile(): string
    {
        return Path::log('mail.log');
    }

    private function log(string $status, RawMessage $message, ?Envelope $envelope, string $error = ''): void
    {
        $addresses = static fn (array $addresses): string => implode(', ', array_map(static fn (Address $address) => $address->getAddress(), $addresses));

        $from = $to = $subject = '';
        if ($message instanceof Email) {
            $from = $addresses($message->getFrom());
            if ($replyTo = $message->getReplyTo()) {
                $from .= '; reply-to: ' . $addresses($replyTo);
            }
            $to = $addresses($message->getTo());
            $subject = $message->getSubject() ?? '';
        } elseif (null !== $envelope) {
            $from = $envelope->getSender()->getAddress();
            $to = $addresses($envelope->getRecipients());
        }

        if ($this->detourRecipients) {
            $to .= ' → ' . $addresses($this->detourRecipients);
        }

        LogFile::factory(self::logFile(), 2_000_000)->add([$status, $from, $to, $subject, $error]);
    }
}

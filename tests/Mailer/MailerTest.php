<?php

namespace Redaxo\Core\Tests\Mailer;

use Override;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Mailer\Mailer;
use Redaxo\Core\Mailer\MailLogMode;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/** @internal */
final class MailerTest extends TestCase
{
    private const array ENV_VARS = ['MAILER_DSN', 'REX_MAILER_FROM', 'REX_MAILER_RECIPIENTS', 'REX_MAILER_LOG', 'REX_MAILER_ARCHIVE'];

    /** @var array<mixed> */
    private array $origServer = [];

    /** @var array<mixed> */
    private array $origEnv = [];

    #[Override]
    protected function setUp(): void
    {
        $this->origServer = $_SERVER;
        $this->origEnv = $_ENV;

        foreach (self::ENV_VARS as $name) {
            unset($_SERVER[$name], $_ENV[$name]);
        }
    }

    #[Override]
    protected function tearDown(): void
    {
        $_SERVER = $this->origServer;
        $_ENV = $this->origEnv;
    }

    public function testFromEnv(): void
    {
        $_SERVER['MAILER_DSN'] = 'null://null';
        $_SERVER['REX_MAILER_FROM'] = 'REDAXO <from@example.org>';
        $_SERVER['REX_MAILER_RECIPIENTS'] = 'test1@example.org, Test <test2@example.org>';
        $_SERVER['REX_MAILER_LOG'] = 'all';
        $_SERVER['REX_MAILER_ARCHIVE'] = '1';

        $mailer = Mailer::fromEnv();

        self::assertSame('null://', (string) $mailer->transport);
        self::assertEquals(new Address('from@example.org', 'REDAXO'), $mailer->defaultFrom);
        self::assertEquals([new Address('test1@example.org'), new Address('test2@example.org', 'Test')], $mailer->detourRecipients);
        self::assertSame(MailLogMode::All, $mailer->logMode);
        self::assertTrue($mailer->archive);
    }

    public function testFromEnvDefaults(): void
    {
        $_SERVER['MAILER_DSN'] = 'null://null';

        $mailer = Mailer::fromEnv();

        self::assertNull($mailer->defaultFrom);
        self::assertSame([], $mailer->detourRecipients);
        self::assertSame(MailLogMode::Errors, $mailer->logMode);
        self::assertFalse($mailer->archive);
    }

    public function testFromEnvWithoutDsn(): void
    {
        $this->expectExceptionMessage('The env var "MAILER_DSN" is missing');

        Mailer::fromEnv();
    }

    public function testFromEnvWithInvalidLogMode(): void
    {
        $_SERVER['MAILER_DSN'] = 'null://null';
        $_SERVER['REX_MAILER_LOG'] = 'errors_only';

        $this->expectException(InvalidArgumentException::class);

        Mailer::fromEnv();
    }

    public function testSendAddsDefaultFrom(): void
    {
        $transport = new RecordingTransport();
        $mailer = new Mailer($transport, defaultFrom: new Address('from@example.org'), logMode: MailLogMode::None);

        $mailer->send(self::createEmail());
        self::assertEquals([new Address('from@example.org')], self::getSentEmail($transport)->getFrom());

        $mailer->send(self::createEmail()->from('custom@example.org'));
        self::assertEquals([new Address('custom@example.org')], self::getSentEmail($transport)->getFrom());
    }

    public function testSendWithoutDetour(): void
    {
        $transport = new RecordingTransport();
        $mailer = new Mailer($transport, logMode: MailLogMode::None);

        $mailer->send(self::createEmail()->from('from@example.org')->cc('cc@example.org'));

        self::assertEquals([new Address('to@example.org'), new Address('cc@example.org')], $transport->sent?->getEnvelope()->getRecipients());
    }

    public function testSendWithDetour(): void
    {
        $transport = new RecordingTransport();
        $detour = [new Address('test@example.org')];
        $mailer = new Mailer($transport, defaultFrom: new Address('from@example.org'), detourRecipients: $detour, logMode: MailLogMode::None);

        $email = self::createEmail()->cc('cc@example.org')->bcc('bcc@example.org');
        $mailer->send($email);

        self::assertEquals($detour, $transport->sent?->getEnvelope()->getRecipients());

        $sentEmail = self::getSentEmail($transport);
        self::assertEquals([new Address('to@example.org')], $sentEmail->getTo());
        self::assertSame([], $sentEmail->getCc());
        self::assertSame([], $sentEmail->getBcc());
        self::assertSame('cc@example.org', $sentEmail->getHeaders()->get('X-Original-Cc')?->getBodyAsString());
        self::assertSame('bcc@example.org', $sentEmail->getHeaders()->get('X-Original-Bcc')?->getBodyAsString());

        // the passed message itself is left unchanged
        self::assertEquals([new Address('cc@example.org')], $email->getCc());
    }

    public function testSendRethrowsTransportException(): void
    {
        $transport = new RecordingTransport(fail: true);
        $mailer = new Mailer($transport, defaultFrom: new Address('from@example.org'), logMode: MailLogMode::None);

        $this->expectException(TransportException::class);

        $mailer->send(self::createEmail());
    }

    private static function createEmail(): Email
    {
        return new Email()->to('to@example.org')->subject('Test')->text('Test');
    }

    private static function getSentEmail(RecordingTransport $transport): Email
    {
        $message = $transport->sent?->getOriginalMessage();
        self::assertInstanceOf(Email::class, $message);

        return $message;
    }
}

/** @internal */
final class RecordingTransport extends AbstractTransport
{
    public ?SentMessage $sent = null;

    public function __construct(
        private readonly bool $fail = false,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function doSend(SentMessage $message): void
    {
        if ($this->fail) {
            throw new TransportException('Sending failed');
        }

        $this->sent = $message;
    }

    #[Override]
    public function __toString(): string
    {
        return 'recording://';
    }
}

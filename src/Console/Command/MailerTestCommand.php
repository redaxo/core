<?php

namespace Redaxo\Core\Console\Command;

use Redaxo\Core\Core;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Email;

use function sprintf;

/**
 * @internal
 */
#[AsCommand(name: 'mailer:test', description: 'Sends a test mail via the configured mailer')]
final class MailerTestCommand extends AbstractCommand
{
    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Recipient of the test mail')] string $recipient,
    ): int {
        $mailer = Core::getMailer();

        $email = new Email()
            ->to($recipient)
            ->subject('Test mail | ' . Core::getProject()->instanceName . ' | ' . date('Y-m-d H:i:s'))
            ->text('This is a test mail of the REDAXO installation "' . Core::getProject()->instanceName . '".');

        try {
            $mailer->send($email, archive: false);
        } catch (TransportExceptionInterface $exception) {
            $io->error(sprintf('The test mail could not be sent: %s', $exception->getMessage()));
            if ($io->isVerbose() && '' !== $exception->getDebug()) {
                $io->block($exception->getDebug());
            }

            return Command::FAILURE;
        }

        $io->success(sprintf('The test mail was sent to "%s" via "%s".', $recipient, $mailer->transport));
        if ($mailer->detourRecipients) {
            $io->note(sprintf('The mail was delivered to the detour recipients: %s', implode(', ', array_map(static fn ($address) => $address->toString(), $mailer->detourRecipients))));
        }

        return Command::SUCCESS;
    }
}

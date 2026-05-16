<?php

namespace Redaxo\Core\Security;

use DateInterval;
use DateTimeImmutable;
use Redaxo\Core\Base\FactoryTrait;
use Redaxo\Core\Core;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Type;
use SensitiveParameter;

use function count;

class BackendPasswordPolicy extends PasswordPolicy
{
    use FactoryTrait;

    /**
     * Forbid to reuse the last X previous passwords.
     *
     * @internal
     */
    public readonly ?int $noReuseOfLast;

    /**
     * Forbid to reuse the previous passwords used in the given interval.
     *
     * @internal
     */
    public readonly ?DateInterval $noReuseWithin;

    /**
     * Force to renew the password after the given interval.
     *
     * @internal
     */
    public readonly ?DateInterval $forceRenewAfter;

    /**
     * Block account if the password wasn't changed in the given interval.
     *
     * @internal
     */
    public readonly ?DateInterval $blockAccountAfter;

    final private function __construct()
    {
        /** @var array{no_reuse_of_last?: int, no_reuse_within?: string, force_renew_after?: string, block_account_after?: string} $options */
        $options = Core::getProperty('password_policy', []);

        $this->noReuseOfLast = $options['no_reuse_of_last'] ?? null;
        unset($options['no_reuse_of_last']);

        $this->noReuseWithin = isset($options['no_reuse_within']) ? new DateInterval($options['no_reuse_within']) : null;
        unset($options['no_reuse_within']);

        $this->forceRenewAfter = isset($options['force_renew_after']) ? new DateInterval($options['force_renew_after']) : null;
        unset($options['force_renew_after']);

        $this->blockAccountAfter = isset($options['block_account_after']) ? new DateInterval($options['block_account_after']) : null;
        unset($options['block_account_after']);

        parent::__construct($options);
    }

    public static function factory(): static
    {
        $class = static::getFactoryClass();

        return new $class();
    }

    public function check(#[SensitiveParameter] string $password, ?int $id = null): true|string
    {
        if (true !== $msg = parent::check($password, $id)) {
            return $msg;
        }

        if (null === $id || !isset($this->noReuseOfLast) && !isset($this->noReuseWithin)) {
            return true;
        }

        $user = User::require($id);
        $previousPasswords = $user->getValue('previous_passwords');

        if (!$previousPasswords) {
            return true;
        }

        $previousPasswords = Type::array(json_decode((string) $previousPasswords, true));
        $previousPasswords = $this->cleanUpPreviousPasswords($previousPasswords);

        foreach ($previousPasswords as $previousPassword) {
            if (BackendLogin::passwordVerify($password, $previousPassword[0])) {
                return I18n::msg('password_already_used');
            }
        }

        return true;
    }

    /**
     * @internal
     *
     * @return list<array{string, int}>
     */
    public function updatePreviousPasswords(?User $user, #[SensitiveParameter] string $password): array
    {
        if (!isset($this->noReuseOfLast) && !isset($this->noReuseWithin)) {
            return [];
        }

        if ($user) {
            $previousPasswords = $user->getValue('previous_passwords');
            $previousPasswords = $previousPasswords ? json_decode((string) $previousPasswords, true) : [];
        } else {
            $previousPasswords = [];
        }
        $previousPasswords[] = [$password, time()];

        return $this->cleanUpPreviousPasswords($previousPasswords);
    }

    /**
     * @param list<array{string, int}> $previousPasswords
     * @return list<array{string, int}>
     */
    private function cleanUpPreviousPasswords(#[SensitiveParameter] array $previousPasswords): array
    {
        if (!isset($this->noReuseOfLast) && !isset($this->noReuseWithin)) {
            return [];
        }

        $minI = count($previousPasswords) - ($this->noReuseOfLast ?? 0);

        if (isset($this->noReuseWithin)) {
            $minTimestamp = new DateTimeImmutable()->sub($this->noReuseWithin)->getTimestamp();
        } else {
            $minTimestamp = time() + 1;
        }

        $return = [];

        $i = 0;
        foreach ($previousPasswords as $previousPassword) {
            if ($i >= $minI || $previousPassword[1] >= $minTimestamp) {
                $return[] = $previousPassword;
            }
            ++$i;
        }

        return $return;
    }
}

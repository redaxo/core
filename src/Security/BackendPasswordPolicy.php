<?php

namespace Redaxo\Core\Security;

use DateInterval;
use DateTimeImmutable;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Type;
use SensitiveParameter;

use function count;

class BackendPasswordPolicy extends PasswordPolicy
{
    /**
     * @param array<string, array{min?: int, max?: int}> $rules Rules for the password itself, see `PasswordPolicy`
     * @param int|null $noReuseOfLast Forbid to reuse the last X previous passwords
     * @param DateInterval|null $noReuseWithin Forbid to reuse the previous passwords used in the given interval
     * @param DateInterval|null $forceRenewAfter Force to renew the password after the given interval
     * @param DateInterval|null $blockAccountAfter Block account if the password wasn't changed in the given interval
     */
    public function __construct(
        array $rules = ['length' => ['min' => 8, 'max' => 4096]],
        public readonly ?int $noReuseOfLast = null,
        public readonly ?DateInterval $noReuseWithin = null,
        public readonly ?DateInterval $forceRenewAfter = null,
        public readonly ?DateInterval $blockAccountAfter = null,
    ) {
        parent::__construct($rules);
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

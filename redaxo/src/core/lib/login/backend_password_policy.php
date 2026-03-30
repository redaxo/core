<?php

/**
 * @author gharlan
 *
 * @package redaxo\core\login
 */
class rex_backend_password_policy extends rex_password_policy
{
    use rex_factory_trait;

    /**
     * Forbid to reuse the last X previous passwords.
     *
     * @var int|null
     */
    private $noReuseOfLast;

    /**
     * Forbid to reuse the previous passwords used in the given interval.
     *
     * @var DateInterval|null
     */
    private $noReuseWithin;

    /**
     * Force to renew the password after the given interval.
     *
     * @var DateInterval|null
     */
    private $forceRenewAfter;

    /**
     * Block account if the password wasn't changed in the given interval.
     *
     * @var DateInterval|null
     */
    private $blockAccountAfter;

    /** @deprecated use `factory()` instead */
    public function __construct()
    {
        /** @var array{no_reuse_of_last?: int, no_reuse_within?: string, force_renew_after?: string, block_account_after?: string} $options */
        $options = rex::getProperty('password_policy', []);

        if (isset($options['no_reuse_of_last'])) {
            $this->noReuseOfLast = $options['no_reuse_of_last'];
            unset($options['no_reuse_of_last']);
        }
        if (isset($options['no_reuse_within'])) {
            $this->noReuseWithin = new DateInterval($options['no_reuse_within']);
            unset($options['no_reuse_within']);
        }
        if (isset($options['force_renew_after'])) {
            $this->forceRenewAfter = new DateInterval($options['force_renew_after']);
            unset($options['force_renew_after']);
        }
        if (isset($options['block_account_after'])) {
            $this->blockAccountAfter = new DateInterval($options['block_account_after']);
            unset($options['block_account_after']);
        }

        /** @psalm-suppress InvalidArgument */
        parent::__construct($options);
    }

    /** @return static */
    public static function factory()
    {
        $class = static::getFactoryClass();

        return new $class(); // @phpstan-ignore method.deprecated
    }

    public function check(#[SensitiveParameter] $password, $id = null)
    {
        if (true !== $msg = parent::check($password, $id)) {
            return $msg;
        }

        if (null === $id || !isset($this->noReuseOfLast) && !isset($this->noReuseWithin)) {
            return true;
        }

        $user = rex_user::require($id);
        $previousPasswords = $user->getValue('previous_passwords');

        if (!$previousPasswords) {
            return true;
        }

        $password = sha1($password);
        $previousPasswords = rex_type::array(json_decode($previousPasswords, true));
        $previousPasswords = $this->cleanUpPreviousPasswords($previousPasswords);

        foreach ($previousPasswords as $previousPassword) {
            if (rex_backend_login::passwordVerify($password, $previousPassword[0], true)) {
                return rex_i18n::msg('password_already_used');
            }
        }

        return true;
    }

    public function getForceRenewAfter(): ?DateInterval
    {
        return $this->forceRenewAfter;
    }

    public function getBlockAccountAfter(): ?DateInterval
    {
        return $this->blockAccountAfter;
    }

    /**
     * @internal
     *
     * @return list<array{string, int}>
     */
    public function updatePreviousPasswords(?rex_user $user, #[SensitiveParameter] string $password): array
    {
        if (!isset($this->noReuseOfLast) && !isset($this->noReuseWithin)) {
            return [];
        }

        if ($user) {
            $previousPasswords = $user->getValue('previous_passwords');
            $previousPasswords = $previousPasswords ? json_decode($previousPasswords, true) : [];
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
            $minTimestamp = (new DateTimeImmutable())->sub($this->noReuseWithin)->getTimestamp();
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

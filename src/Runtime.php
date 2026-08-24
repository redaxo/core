<?php

namespace Redaxo\Core;

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Runtime\SymfonyRuntime;

/**
 * The runtime for REDAXO projects, wiring symfony/runtime and dotenv to REDAXO's env vars: the mode is read from
 * `REX_MODE` (instead of `APP_ENV`), `live`/`hardened` are treated as production modes (`APP_DEBUG` is derived
 * accordingly), and the fail-safe fallback for an undefined mode is `live` (instead of Symfony's `dev`).
 *
 * Referenced in the project's composer.json via `extra.runtime.class`.
 */
class Runtime extends SymfonyRuntime
{
    /**
     * @param array{
     *     env_var_name?: string,
     *     debug_var_name?: string,
     *     prod_envs?: list<string>,
     *     test_envs?: list<string>,
     *     disable_dotenv?: bool,
     *     dotenv_path?: string,
     *     dotenv_overload?: bool,
     *     use_putenv?: bool,
     *     project_dir?: string,
     * } $options
     */
    public function __construct(array $options = [])
    {
        $envKey = $options['env_var_name'] ??= 'REX_MODE';
        $debugKey = $options['debug_var_name'] ??= 'APP_DEBUG';
        $prodEnvs = $options['prod_envs'] ??= [Mode::Live->value, Mode::Hardened->value];

        // Symfony falls back to the dev env when the env var is defined nowhere. For REDAXO the fail-safe default
        // must be the live mode instead, so boot dotenv ourselves with that default before the parent runs.
        if (
            !($options['disable_dotenv'] ?? false) && isset($options['project_dir'])
            && null === Env::get($envKey)
            && class_exists(Dotenv::class)
        ) {
            new Dotenv($envKey, $debugKey)
                ->setProdEnvs($prodEnvs)
                ->usePutenv((bool) ($options['use_putenv'] ?? false))
                ->bootEnv($options['project_dir'] . '/' . ($options['dotenv_path'] ?? '.env'), Mode::Live->value, $options['test_envs'] ?? ['test'], (bool) ($options['dotenv_overload'] ?? false));
        }

        parent::__construct($options);
    }
}

<?php

namespace Redaxo\Core\Tests;

use PHPUnit\Framework\TestCase;
use Redaxo\Core\AbstractProject;
use Redaxo\Core\Env;
use Redaxo\Core\Environment;

/** @internal */
final class AbstractProjectTest extends TestCase
{
    public function testInstanceName(): void
    {
        $origServer = Env::get('REX_INSTANCE_NAME');
        $origEnv = $_ENV['REX_INSTANCE_NAME'] ?? null;

        try {
            $project = new class(Environment::Frontend) extends AbstractProject {};

            $_SERVER['REX_INSTANCE_NAME'] = 'My Site';
            self::assertSame('My Site', $project->instanceName);

            unset($_SERVER['REX_INSTANCE_NAME'], $_ENV['REX_INSTANCE_NAME']);
            self::assertSame('REDAXO', $project->instanceName);

            $project = new class(Environment::Frontend) extends AbstractProject {
                public string $instanceName = 'Configured';
            };
            $_SERVER['REX_INSTANCE_NAME'] = 'My Site';
            self::assertSame('Configured', $project->instanceName);
        } finally {
            if (null === $origServer) {
                unset($_SERVER['REX_INSTANCE_NAME']);
            } else {
                $_SERVER['REX_INSTANCE_NAME'] = $origServer;
            }
            if (null !== $origEnv) {
                $_ENV['REX_INSTANCE_NAME'] = $origEnv;
            }
        }
    }
}

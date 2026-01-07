<?php

namespace Project;

use Redaxo\Core\AbstractProject;

final class Project extends AbstractProject
{
    /** @var non-empty-string */
    public string $projectPath = __DIR__ . '/..';

    /** @var non-empty-string */
    public string $corePath = __DIR__ . '/../../..';
}

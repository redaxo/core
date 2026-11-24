<?php

use Redaxo\Core\Exception\RuntimeException;

echo 'partial output';

throw new RuntimeException('Fragment failed');

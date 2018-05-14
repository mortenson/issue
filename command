#!/usr/bin/env php
<?php

/**
 * @file
 * Provides CLI commands for contributing to Drupal.
 */

use Symfony\Component\Console\Application;
use DrupalIssue\Command\PatchCommand;
use DrupalIssue\Command\CreatePatchCommand;

if (PHP_SAPI !== 'cli') {
  return;
}

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/src/ExtensionDiscovery.php';
require_once __DIR__ . '/src/Command/PatchCommandBase.php';
require_once __DIR__ . '/src/Command/PatchCommand.php';
require_once __DIR__ . '/src/Command/CreatePatchCommand.php';

$application = new Application('drupal-issue', 'FUN.0');

$application->add(new PatchCommand());
$application->add(new CreatePatchCommand());

$application->run();

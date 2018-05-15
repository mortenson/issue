#!/usr/bin/env php
<?php

/**
 * @file
 * Provides CLI commands for contributing to Drupal.
 */

use Symfony\Component\Console\Application;
use DrupalIssue\Command\PatchCommand;
use DrupalIssue\Command\CreatePatchCommand;
use DrupalIssue\Command\ReviewCommand;
use DrupalIssue\Command\TestCommand;

if (PHP_SAPI !== 'cli') {
  return;
}

/** @var \Composer\Autoload\ClassLoader $loader */
$loader = require_once __DIR__ . '/../autoload.php';
$loader->addPsr4('DrupalIssue\\', __DIR__ . '/src');

$application = new Application('drupal-issue', 'FUN.0');

$application->add(new PatchCommand());
$application->add(new CreatePatchCommand());
$application->add(new ReviewCommand());
$application->add(new TestCommand());

$application->run();

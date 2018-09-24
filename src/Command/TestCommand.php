<?php

namespace DrupalIssue\Command;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Shortcut for running tests that have been added or changed.
 */
class TestCommand extends CommandBase {

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this->setName('test')
      ->setDescription('Tests changes in the context of a given project.')
      ->addArgument('project', InputArgument::OPTIONAL, 'A project name.')
      ->addOption('url', NULL, InputOption::VALUE_REQUIRED, 'The URL of your Drupal site.')
      ->addOption('filter', NULL, InputOption::VALUE_OPTIONAL, 'A filter to pass to PHP Unit.');

    parent::configure();
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $io = new SymfonyStyle($input, $output);
    $default_cache = $this->getCacheDirectory() . '/last_project';

    $project_name = $input->getArgument('project');
    if (!$project_name) {
      $default = 'drupal';
      if (file_exists($default_cache)) {
        $default = file_get_contents($default_cache);
      }
      $project_name = $io->ask('What project are you working on?', $default);
    }

    file_put_contents($default_cache, $project_name);

    if (!empty($input->getOption('url'))) {
      putenv('SIMPLETEST_BASE_URL=' . $input->getOption('url'));
    }
    else if (!getenv('SIMPLETEST_BASE_URL')) {
      $io->error('You must provide a SIMPLETEST_BASE_URL environment variable or use the "--url" option to run tests.');
      return 1;
    }
    if (!getenv('DRUPAL_TEST_BASE_URL')) {
      putenv('DRUPAL_TEST_BASE_URL=' . getenv('SIMPLETEST_BASE_URL'));
    }
    if (!getenv('SIMPLETEST_DB')) {
      putenv('SIMPLETEST_DB=sqlite://localhost/sites/default/files/.simpletest.sqlite');
    }

    if ($project_name !== 'drupal') {
      $project_path = $this->getExtensionPath($project_name);
      if (!$project_path) {
        $io->error("Unable to find the a locally installed project for this issue.");
        return 1;
      }
      $add_command = 'git add .';
    }
    else {
      $project_path = '.';
      // @todo Probably too specific to my use case.
      $add_command = 'git add core';
    }

    exec('cd ' . escapeshellarg($project_path) . ' && ' . $add_command . ' && git diff --cached --name-only', $return_output, $return_var);
    $tests = array_values(preg_grep('/(tests\/src|src\/Tests|Nightwatch|core\/tests)(?!\/Commands).*\.(php|js)/i', $return_output));

    if (empty($tests)) {
      $io->writeln('You have not changed or added any tests.');
      return 0;
    }

    $test = count($tests) === 1 ? reset($tests) : $io->choice('What test would you like to run?', $tests);

    $test = "$project_path/$test";

    $suffix = '';
    if ($filter = $input->getOption('filter')) {
      $suffix .= ' --filter=' . escapeshellarg($filter);
    }

    if (strpos($test, 'tests/src/FunctionalJavascript') !== FALSE) {
      passthru('pkill phantomjs');
      passthru('pkill chromedriver');
      passthru('chromedriver --port=4444 > /dev/null 2>&1 &');
      passthru('phantomjs --ssl-protocol=any --ignore-ssl-errors=true vendor/jcalderonzumba/gastonjs/src/Client/main.js 8510 1024 768 > /dev/null 2>&1 &', $return_var);
      if ($return_var != 0) {
        $io->error('Error running the "phantomjs" command. Is PhantomJS installed?');
        return 1;
      }
      passthru('./vendor/bin/phpunit -c core ' . escapeshellarg($test) . $suffix);
      passthru('pkill phantomjs');
      passthru('pkill chromedriver');
    }
    else if (strpos($test, 'Nightwatch') !== FALSE) {
      passthru('cd core && yarn install && yarn test:nightwatch ../' . escapeshellarg($test));
    }
    else if (strpos($test, 'tests/src') !== FALSE || strpos($test, 'core/tests/Drupal/Tests') !== FALSE || strpos($test, 'core/tests/Drupal/KernelTests') !== FALSE) {
      passthru('./vendor/bin/phpunit -c core ' . escapeshellarg($test) . $suffix);
    }
    else if (strpos($test, 'src/Tests') !== FALSE) {
      passthru('php ./core/scripts/run-tests.sh --file ' . escapeshellarg($test));
    }
    else {
      $io->error("Cannot determine what kind of test \"$test\" is.");
      return 1;
    }

    return 0;
  }

}

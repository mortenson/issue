<?php

namespace DrupalIssue\Command;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Does some common smoke testing for reviewing changes.
 */
class ReviewCommand extends CommandBase {

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this->setName('review')
      ->setDescription('Reviews changes in the context of a given project.')
      ->addArgument('project', InputArgument::OPTIONAL, 'A project name.');

    parent::configure();
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $io = new SymfonyStyle($input, $output);

    $project_name = $input->getArgument('project');
    if (!$project_name) {
      $project_name = $io->ask('What project are you working on?');
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

    $io->writeln("Starting auto review for $project_name");
    $io->writeln('Running PHP Code Sniffer');

    exec('cd ' . escapeshellarg($project_path) . ' && ' . $add_command . ' && git diff --cached --name-only | ./vendor/bin/phpcs --standard=Drupal --runtime-set installed_paths vendor/drupal/coder/coder_sniffer', $return_output, $return_var);
    if ($return_var != 0) {
      $io->error('Please address code standard violations above.');
      return 1;
    }
    $io->success('No code standard violations found.');

    exec('cd ' . escapeshellarg($project_path) . ' && git diff --cached --name-only', $return_output, $return_var);
    $code_changes = preg_grep('/\.(module|php|inc|install|js)$/', $return_output);
    $test_changes = preg_grep('/test/i', $return_output);
    if ($code_changes && !$test_changes) {
      $io->error('Code changes have been made, but no tests were changed or added.');
      return 1;
    }
    if ($test_changes) {
      $io->success('Test coverage was changed or added for this issue.');
    }

    // @todo What else would be useful?

    return 0;
  }

}

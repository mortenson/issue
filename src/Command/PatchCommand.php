<?php

namespace DrupalIssue\Command;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Downloads and applies a patch starting from a Drupal.org issue number.
 */
class PatchCommand extends CommandBase {

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this->setName('patch')
      ->setDescription('Downloads and applies a patch given an issue number.')
      ->addArgument('issue-number', InputArgument::OPTIONAL, 'A Drupal.org issue number, which can be found at the end of an issue\'s URL.');

    parent::configure();
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $io = new SymfonyStyle($input, $output);

    $issue = $this->getIssue($input, $io);
    if (!$issue) {
      return 1;
    }

    $project_name = $issue['field_project']['machine_name'];

    if ($project_name !== 'drupal') {
      $project = $this->request($issue['field_project']['uri'] . '.json');
      $project_path = $this->getExtensionPath($project);
      if (!$project_path) {
        $minor_verison = preg_replace('/[^-]+\-([^-.]+)\.([^-.]+)\-[^-]+/', '$1', $issue['field_issue_version']);
        $io->writeln("Installing the development release of {$project['title']}");
        exec('composer require drupal/' . escapeshellarg($project_name) . ':' . escapeshellarg($minor_verison) . '.x-dev', $return_output, $return_var);
        $project_path = $this->getExtensionPath($project);
        if ($return_var != 0 || !$project_path) {
          $io->error('Unable to install project. See output above for details.');
          return 1;
        }
      }
    }
    else {
      $project_path = '.';
    }

    $file = $this->choosePatch('What patch would you like to apply?', $issue, $io);
    $filename = $this->request($file['url'], TRUE, TRUE);
    $basename = basename($file['url']);

    // @todo We could prompt the user to see if they want the patch copied to
    // the root dir - this is how I do patch workflows but maybe it should be
    // optional.
    if (!file_exists($basename)) {
      copy($filename, $basename);
      $io->writeln("Downloaded $basename");
    }

    exec('cd ' . escapeshellarg($project_path) . ' && git apply ' . escapeshellarg(getcwd() . '/' . $basename), $return_output, $return_var);
    if ($return_var != 0) {
      $io->error('Patch failed to apply. See output above for details.');
      return 1;
    }

    $io->success("Patched $project_name with $basename");

    return 0;
  }

}

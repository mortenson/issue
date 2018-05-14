<?php

namespace DrupalIssue\Command;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Creates a new patch for a given issue.
 */
class CreatePatchCommand extends PatchCommandBase {

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this->setName('create-patch')
      ->setDescription('Creates a new patch for a given issue.')
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
      $type_map = [
        'project_distribution' => 'profile',
        'project_module' => 'module',
        'project_theme' => 'theme',
      ];
      $project_path = $this->getExtensionPath($type_map[$project['type']], $project_name);
      if (!$project_path) {
        $io->error("{$project['title']} is not installed, so you probably don't have local changes.");
      }
      $add_command = 'git add .';
    }
    else {
      $project_path = '.';
      // @todo Probably too specific to my use case.
      $add_command = 'git add core';
    }

    $next_comment = count($issue['comments']) + 1;
    $patch = "{$issue['nid']}-$next_comment.patch";

    exec('cd ' . escapeshellarg($project_path) . ' && ' . $add_command . ' && git diff HEAD --binary . > ' . escapeshellarg(getcwd() . "/$patch"), $output, $return_var);

    if ($return_var != 0) {
      $io->error('Failed to create patch. See output above for details.');
      return 1;
    }

    $io->writeLn("Created $patch");

    $file = $this->choosePatch('What patch do you want to create an interdiff from?', $issue, $io, 'Do not create interdiff');
    if ($file) {
      $filename = $this->request($file['url'], TRUE, TRUE);
      $comment_number = NULL;
      foreach ($issue['comments'] as $i => $comment) {
        if ($comment['id'] == $file['cid']) {
          $comment_number = $i + 1;
          break;
        }
      }
      $interdiff = "interdiff-{$issue['nid']}-$comment_number-$next_comment.txt";
      exec("interdiff $filename $patch > $interdiff", $output, $return_var);

      if ($return_var != 0) {
        $io->error('Failed to create interdiff. See output above for details.');
        return 1;
      }

      $io->writeLn("Created $interdiff");
    }

    return 0;
  }

}

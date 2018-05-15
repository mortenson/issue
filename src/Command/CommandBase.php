<?php

namespace DrupalIssue\Command;

use DrupalIssue\ExtensionDiscovery;
use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;

/**
 * Base class containing utility methods for commands.
 */
class CommandBase extends Command {

  /**
   * Gets data from a given URL, and caches locally.
   *
   * @param string $url
   *   A URL you want to fetch.
   * @param bool $cache
   *   TRUE if the request should pull from cache. Defaults to TRUE.
   * @param bool $return_cache_filename
   *   TRUE if the cached filename should be returned. Defaults to FALSE.
   * @return mixed|string
   *   The parsed response, or a string if the cached filename was requested.
   */
  protected function request($url, $cache = TRUE, $return_cache_filename = FALSE) {
    $client = new Client();

    $cache_dir = __DIR__ . '/../../.cache/';
    if (!is_dir($cache_dir)) mkdir($cache_dir);

    $cache_filename = $cache_dir . md5($url);
    if ($cache && file_exists($cache_filename)) {
      $contents = file_get_contents($cache_filename);
    }
    else {
      $contents = $client->get($url)->getBody();
    }

    if (pathinfo($url, PATHINFO_EXTENSION) === 'json') {
      $return = json_decode($contents, TRUE);
    }
    else {
      $return = $contents;
    }

    if ($cache && !file_exists($cache_filename)) {
      file_put_contents($cache_filename, $contents);
    }

    if ($return_cache_filename) {
      return $cache_filename;
    }

    return $return;
  }

  /**
   * Scans the filesystem for extensions of a given type.
   *
   * @param string|array $project
   *   The project array, or the name of the project.
   *
   * @return bool|string
   *   The path to the extension, or FALSE if it was not found.
   */
  protected function getExtensionPath($project) {
    if (is_array($project)) {
      $type_map = [
        'project_distribution' => 'profile',
        'project_module' => 'module',
        'project_theme' => 'theme',
      ];
      $name = $project['field_project_machine_name'];
      $types = [$type_map[$project['type']]];
    }
    else {
      $name = $project;
      $types = ['module', 'theme', 'profile'];
    }
    foreach ($types as $type) {
      $discovery = new ExtensionDiscovery(getcwd(), FALSE, [], 'sites/default');
      $discovery->clearCache();
      $extensions = $discovery->scan($type, FALSE);
      if (isset($extensions[$name])) {
        return $extensions[$name]->getPath();
      }
    }
    return FALSE;
  }

  /**
   * Gets the current Drupal.org issue from user input.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *   The Symfony input object.
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The Symfony input/output decorator.
   * @return bool|array
   *   An array representing the issue, or FALSE if there was an array.
   */
  protected function getIssue($input, $io) {
    $issue_number = $input->getArgument('issue-number');
    if (!$issue_number) {
      $issue_number = $io->ask('What issue are you working on?');
    }

    if (!is_numeric($issue_number)) {
      $io->error('The provided issue number is invalid.');
      return 1;
    }

    $issue = $this->request("https://www.drupal.org/api-d7/node/$issue_number.json", FALSE);

    if (!isset($issue['type']) || $issue['type'] !== 'project_issue') {
      $io->error('The given ID is not for an issue');
      return FALSE;
    }

    if (strpos($issue['field_issue_version'], '8') !== 0) {
      $io->error('Only Drupal 8 projects are supported at this time.');
      return FALSE;
    }
    return $issue;
  }

  /**
   * Prompts the user to select a patch for a given issue.
   *
   * @param string $question
   *   A message to prompt the user with.
   * @param array $issue
   *   The issue array.
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The Symfony input/output decorator.
   * @param string $empty_message
   *   An empty message to display to the user, if empty selection is allowed.
   *
   * @return array
   */
  protected function choosePatch($question, $issue, $io, $empty_message = NULL) {
    $files = $this->getPatches($issue);
    if (count($files) === 1 && !$empty_message) {
      return reset($files);
    }
    $choices = [];
    foreach ($files as $file) {
      $choices[$file['name']] = $file;
    }
    if ($empty_message) {
      $choices[$empty_message] = FALSE;
    }
    $choice = $io->choice($question, array_keys($choices));
    return $choices[$choice];
  }

  /**
   * Gets displayed patches for a given issue.
   *
   * @param array $issue
   *   An issue array.
   * @return array
   *   An array of file information.
   */
  protected function getPatches($issue) {
    $files = [];
    foreach ($issue['field_issue_files'] as $fileinfo) {
      if ($fileinfo['display']) {
        $file = $this->request("{$fileinfo['file']['uri']}.json");
        if (pathinfo($file['url'], PATHINFO_EXTENSION) === 'patch') {
          $file['cid'] = $fileinfo['file']['cid'];
          $files[] = $file;
        }
      }
    }
    return $files;
  }

}

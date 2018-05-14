<?php

namespace DrupalIssue\Command;

use DrupalIssue\ExtensionDiscovery;
use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * Downloads and applies a patch starting from a Drupal.org issue number.
 */
class PatchCommand extends Command {

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
      return 1;
    }

    if (strpos($issue['field_issue_version'], '8') !== 0) {
      $io->error('Only Drupal 8 projects are supported at this time.');
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
        $minor_verison = preg_replace('/[^-]+\-([^-.]+)\.([^-.]+)\-[^-]+/', '$1', $issue['field_issue_version']);
        $io->writeln("Installing the development release of {$project['title']}");
        exec('composer require drupal/' . escapeshellarg($project_name) . ':' . escapeshellarg($minor_verison) . '.x-dev', $output, $return_var);
        $project_path = $this->getExtensionPath($type_map[$project['type']], $project_name);
        if ($return_var != 0 || !$project_path) {
          $io->error('Unable to install project. See output above for details.');
          return 1;
        }
      }
    }
    else {
      $project_path = '.';
    }

    $files = [];

    foreach ($issue['field_issue_files'] as $fileinfo) {
      if ($fileinfo['display']) {
        $file = $this->request("{$fileinfo['file']['uri']}.json");
        if (pathinfo($file['url'], PATHINFO_EXTENSION) === 'patch') {
          $files[$file['name']] = $file['url'];
        }
      }
    }

    if (count($files) > 1) {
      $key = $io->choice('What patch would you like to apply?', array_keys($files));
    }
    else {
      $key = key($files);
    }
    $file_url = $files[$key];

    $filename = $this->request($file_url, TRUE, TRUE);
    $basename = basename($file_url);

    // @todo We could prompt the user to see if they want the patch copied to
    // the root dir - this is how I do patch workflows but maybe it should be
    // optional.
    if (!file_exists($basename)) {
      copy($filename, $basename);
      $io->writeln("Downloaded $basename");
    }

    exec('cd ' . escapeshellarg($project_path) . ' && git apply ' . escapeshellarg(getcwd() . '/' . $basename), $output, $return_var);
    if ($return_var != 0) {
      $io->error('Patch failed to apply. See output above for details.');
      return 1;
    }

    $io->writeln("Successfully patched $project_name with $basename");

    return 0;
  }

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
   * @param string $type
   *   The extension type to search for. One of 'profile', 'module', 'theme', or
   *   'theme_engine'.
   * @param string $name
   *   The name of the extension.
   *
   * @return bool|string
   *   The path to the extension, or FALSE if it was not found.
   */
  protected function getExtensionPath($type, $name) {
    $discovery = new ExtensionDiscovery(getcwd(), FALSE, [], 'sites/default');
    $discovery->clearCache();
    $extensions = $discovery->scan($type, FALSE);
    return isset($extensions[$name]) ? $extensions[$name]->getPath() : FALSE;
  }

}

<?php

// @codingStandardsIgnoreStart

use Robo\Robo;
use Symfony\Component\Yaml\Yaml;

require '../../RoboFile.php';

/**
 * Defines the search service command.
 */
class SearchServiceCommand extends RoboFile {

  /**
   * Setup elastic search.
   *
   * @command recipes:init:service:search
   */
  public function initServiceSearch() {
    $config = Robo::config();
    $this->say('init:recipe-search');
    $landoFileConfig = Yaml::parse(file_get_contents($this->getDocroot() . '/.lando.yml', 128));
    $this->say('Checking if there is search service is setup.');
    if (!array_key_exists('search', $landoFileConfig['services'])) {
      $landoFileConfig['services']['search'] = [
        'type' => 'elasticsearch:7',
        'portforward' => TRUE,
        'mem' => '1025m',
        'environment' => [
          'cluster.name=' . $config->get('project.machine_name'),
        ],
      ];
      file_put_contents($this->getDocroot() . '/.lando.yml', Yaml::dump($landoFileConfig, 5, 2));
      $this->io()->note('Lando configurations are updated with search service.\n');

      // Get the elasticsearch_connector module from Github,
      // since the Drupal module is not Drupal 9 compatible yet.
      $this->io()->section('Adding the Elasticsearch connector package via composer. \n');
      $this->taskComposerRequire()->dependency('drupal/elasticsearch_connector', '^7.0@alpha')->ansi()->noInteraction()->run();
    }
    else {
      $this->io()->note('Search service exists in the lando configuration. Skipping...');
    }
  }

}

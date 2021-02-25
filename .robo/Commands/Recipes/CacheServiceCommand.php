<?php

// @codingStandardsIgnoreStart

use Symfony\Component\Yaml\Yaml;
require '../../RoboFile.php';

/**
 * Defines the cache service command.
 */
class CacheServiceCommand extends RoboFile {

  /**
   * Setup redis.
   *
   * @command init:service:cache
   */
  public function initServiceCache() {
    $this->say('init:recipe-redis');
    $landoFileConfig = Yaml::parse(file_get_contents($this->getDocroot() . '/.lando.yml', 128));
    $this->say('Checking if there is cache service is setup.');
    if (!array_key_exists('cache', $landoFileConfig['services'])) {
      $landoFileConfig['services']['cache'] = [
        'type' => 'redis:4.0',
        'portforward' => TRUE,
        'persist' => TRUE,
      ];
      $landoFileConfig['tooling']['redis-cli'] = [
        'service' => 'cache',
      ];

      file_put_contents($this->getDocroot() . '/.lando.yml', Yaml::dump($landoFileConfig, 5, 2));
      $this->io()->note('Lando configurations are updated with cache service.\n');

      $this->io()->section('Adding the Drupal Redis module via composer. \n');
      $this->taskComposerRequire()->dependency('drupal/redis', '^1.4')->ansi()->noInteraction()->run();
      $this->taskWriteToFile($this->getDocroot() . '/docroot/sites/default/settings.local.php')
        ->append()
        ->line('# Redis Configuration.')
        ->line('$conf[\'redis_client_host\'] = \'cache\';')
        ->line('$settings[\'container_yamls\'][] = \'modules/redis/example.services.yml\';')
        ->line('$conf[\'redis_client_interface\'] = \'PhpRedis\';')
        ->line('$settings[\'redis.connection\'][\'host\'] = \'cache\';')
        ->line('$settings[\'redis.connection\'][\'port\'] = \'6379\';')
        ->line('$settings[\'cache\'][\'default\'] = \'cache.backend.redis\';')
        ->line('$settings[\'cache_prefix\'][\'default\'] = \'specbee-redis\';')
        ->line('$settings[\'cache\'][\'bins\'][\'form\'] = \'cache.backend.database\';')
        ->run();
    }
    else {
      $this->io()->note('> Cache service exists in the lando configuration. Skipping...');
    }
  }

}

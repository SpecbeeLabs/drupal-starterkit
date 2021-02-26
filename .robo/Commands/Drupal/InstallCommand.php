<?php

// @codingStandardsIgnoreStart

use Robo\Robo;
require '../../RoboFile.php';

/**
 * Define the Drupal install command.
 */
class InstallCommand extends RoboFile {

  /**
   * The local database URL.
   *
   * @var string
   */
  const DB_URL = 'mysql://drupal:drupal@database/drupal';

  /**
   * The local database URL.
   *
   * @var string
   */
  const DB_URL_CI = 'mysql://root@127.0.0.1/drupal8';

  /**
   * Setup a fresh Drupal site from existing config if present.
   */
  public function setup($env = 'local', $opts = ['no-interaction|n' => false]) {
    $this->say('drupal:install');
    $config = Robo::config();
    $this->installDependencies()->run();
    $task = $this->drush()
      ->args('site-install')
      ->arg($config->get('project.config.profile'))
      ->arg('--ansi');

    if ($opts['no-interaction']) {
      $task->arg('--no-interaction');
    }

    if ($env === 'ci') {
      $task->option('db-url', static::DB_URL_CI, '=');
    }
    else {
      $task->option('db-url', static::DB_URL, '=');
    }
    $task->option('site-name', $config->get('project.human_name'), '=')
      ->option('site-mail', $config->get('project.mail'), '=')
      ->option('account-name', 'admin')
      ->option('account-mail', $config->get('project.mail'), '=');
    // Check if config directory exists.
    if (file_exists($this->getDocroot() . '/config/sync/core.extension.yml')) {
      $task->option('existing-config');
    }

    return $task;
  }

}

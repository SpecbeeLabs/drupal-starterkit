<?php

// @codingStandardsIgnoreStart

/**
 * @file
 */

use DrupalFinder\DrupalFinder;
use Robo\Tasks;
use Symfony\Component\Yaml\Yaml;

/**
 * This is project's console commands configuration for Robo task runner.
 */
class RoboFile extends Tasks {

  /**
   * The database URL.
   *
   * @var string
   */
  const DB_URL = 'mysql://drupal:drupal@database/drupal';

  /**
   * Initializes the project repo and performs initial commit.
   */
  public function initRepo() {
    $this->say('> init:repo');
    $collection = $this->collectionBuilder();
    $collection->addTask($this->copyDefaultDrushAlias());
    $collection->addTask($this->setupDrushAlias());
    $collection->addTask($this->setupLando());
    $collection->addTask($this->setupGrumphp());
    $collection->addTask($this->setupGit());
    
    return $collection->run();
  }

  /**
   * Initialize git and make an empty initial commit.
   */
  public function setupGit() {
    $this->say('> setup:git');
    $config = $this->getConfig();
    $task = $this->taskGitStack()
      ->dir((getcwd()) . '/../..')
      ->stopOnFail()
      ->exec('git init')
      ->exec('git remote add origin ' . $config['project']['repo'])
      ->add('-A')
      ->commit($config['project']['prefix'] . '-000: Created project from Specbee boilerplate.');

    return $task;

  }

  /**
   * Copy the default.sites.yml to project.site.yml.
   */
  public function copyDefaultDrushAlias() {
    $this->say('> copy:default-drush-alias');
    $config = $this->getConfig();
    $drushPath = $this->getDocroot() . '/drush/sites';
    $task = $this->taskFilesystemStack()
      ->rename($drushPath . "/default.site.yml", $drushPath . '/' . $config['project']['machine_name'] . '.site.yml', TRUE);
    
    return $task;
  }

  /**
   * Setup the Drupal aliases.
   */
  public function setupDrushAlias() {
    $this->say('> setup:drupal-alias');
    $config = $this->getConfig();
    $drushFile = $this->getDocroot() . '/drush/sites/' . $config['project']['machine_name'] . '.site.yml';
    $task = $this->taskReplaceInFile($drushFile)
      ->from(['${REMOTE_DEV_HOST}', '${REMOTE_DEV_USER}', '${REMOTE_DEV_ROOT}', '${REMOTE_DEV_URI}', '${REMOTE_STAGE_HOST}', '${REMOTE_STAGE_USER}', '${REMOTE_STAGE_ROOT}', '${REMOTE_STAGE_URI}'])
      ->to([$config['drush']['dev']['host'], $config['drush']['dev']['user'], $config['drush']['dev']['root'], $config['drush']['dev']['uri'], $config['drush']['stage']['host'], $config['drush']['stage']['user'], $config['drush']['stage']['root'], $config['drush']['stage']['uri']]);

    return $task;
  }

  /**
   * Setup lando.yml for local environment.
   */
  public function setupLando() {
    $this->say('> setup:lando');
    $config = $this->getConfig();
    $landoFile = $this->getDocroot() . '/.lando.yml';
    $task = $this->taskReplaceInFile($landoFile)
      ->from('${PROJECT_NAME}')
      ->to($config['project']['machine_name']);

    return $task;
  }

  /**
   * Setup Grumphp file.
   */
  public function setupGrumphp() {
    $this->say('> setup:grumphp');
    $config = $this->getConfig();
    $file = $this->getDocroot() . '/grumphp.yml';
    $task = $this->taskReplaceInFile($file)
      ->from('${PROJECT_PREFIX}')
      ->to($config['project']['prefix']);

    return $task;
  }

  /**
   * Setup Drupal site.
   */
  public function drupalInstall() {
    $this->say('> drupal:install');
    $config = $this->getConfig();
    $task = $this->drush()
      ->args("site-install")
      ->arg('lightning')
      ->option('db-url', static::DB_URL, '=')
      ->option('site-name', $config['project']['human_name'], '=')
      ->option('site-mail', $config['project']['mail'], '=')
      ->option('account-name', $config['project']['human_name'] . " Admin", '=')
      ->option('account-mail', $config['project']['mail'], '=');
    // Check if config directory exists.
    if (file_exists($this->getDocroot() . '/config/sync/core.extension.yml')) {
      $task->option('existing-config');
    }
    $result = $task->run();
    
    return $result;
  }

  /**
   * Sync database from remote server.
   */
  public function syncDb() {
    $this->say('> sync:db');
    $config = $this->getConfig();
    $remote_alias = '@' . $config['project']['machine_name'] . '.' . $config['sync']['remote'];
    $local_alias = '@self';
    $collection = $this->collectionBuilder();
    $collection->addTask(
      $this->drush()
        ->args('sql-sync')
        ->arg($remote_alias)
        ->arg($local_alias)
        ->option('--target-dump', sys_get_temp_dir() . '/tmp.target.sql.gz')
        ->option('structure-tables-key', 'lightweight')
        ->option('create-db')
    );
    if ($config['sync']['sanitize'] === TRUE) {
      $collection->addTask(
        $this->drush()
          ->args('sql-sanitize')
      );
    }
    $result = $collection->run();

    return $result;
  }

  /**
   * Sync files from remote server.
   */
  public function syncFiles() {
    $this->say('> sync:files');
    $config = $this->getConfig();
    $remote_alias = '@' . $config['project']['machine_name'] . '.' . $config['drush']['sync'];
    $local_alias = '@self';
    $task = $this->drush()
      ->args('core-rsync')
      ->arg($remote_alias . ':%files')
      ->arg($local_alias . ':%files');
    $result = $task->run();

    return $result;
  }

  /**
   * Import pending configurations.
   */
  public function importConfig() {
    $this->say('> import:config');
    $task = $this->drush()
      ->args('config:import')
      ->run();

    return $task;
  }

  /**
   * Return drush with default arguments.
   *
   * @return \Robo\Task\Base\Exec
   *   A drush exec command.
   */
  protected function drush() {
    // Drush needs an absolute path to the docroot.
    $docroot = $this->getDocroot() . '/docroot';
    return $this->taskExec('../../vendor/bin/drush')
      ->option('root', $docroot, '=');
  }

  /**
   * Get the absolute path to the docroot.
   *
   * @return string
   */
  protected function getDocroot() {
    $drupalFinder = new DrupalFinder();
    $drupalFinder->locateRoot(getcwd());
    $docroot = $drupalFinder->getComposerRoot();
    return $docroot;
  }

  /**
   * Get the project configurations.
   */
  public static function getConfig() {
    $drupalFinder = new DrupalFinder();
    $drupalFinder->locateRoot(getcwd());
    $root = $drupalFinder->getComposerRoot();
    $config = Yaml::parse(file_get_contents($root . "/config.yml"));
    return $config;
  }

}

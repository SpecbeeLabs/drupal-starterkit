<?php

/**
 * Base tasks for project's console commands configuration for Robo task runner.
 *
 * @class RoboFile
 * @codeCoverageIgnore
 */

use DrupalFinder\DrupalFinder;
use Robo\Tasks;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 *
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
      ->from(['#REMOTE_DEV_HOST', '#REMOTE_DEV_USER', '#REMOTE_DEV_ROOT', '#REMOTE_DEV_URI', '#REMOTE_STAGE_HOST', '#REMOTE_STAGE_USER', '#REMOTE_STAGE_ROOT', '#REMOTE_STAGE_URI'])
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
      ->from('#PROJECT_NAME')
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
   * Update Drupal & sync pending configurations.
   *
   * @command drupal:update
   */
  public function drupalUpdate() {
    $this->say('> drupal:update');
    $this->drush()->arg('cache-rebuild');
    $this->updateDatabase();
    $this->importConfig();
  }

  /**
   * Clear the cache.
   *
   * @command drupal:udpate:db
   */
  public function updateDatabase() {
    $this->say('> drupal:update:db');
    $result = $this->drush()
      ->arg('updb')
      ->arg('--no-interaction')
      ->arg('--ansi')
      ->run();
    if (!$result->wasSuccessful()) {
      $this->say($result->getMessage());
      throw new Exception("Failed to execute database updates!");
    }

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

    $this->drush()
      ->arg('config:set')
      ->arg('system.site')
      ->arg('uuid')
      ->arg($this->getExportedSiteUuid())
      ->arg('--no-interaction')
      ->arg('--ansi')
      ->run();

    $task = $this->drush()
      ->arg('config:import')
      ->arg('--no-interaction')
      ->arg('--ansi')
      ->run();

    if (!$task->wasSuccessful()) {
      $this->say($task->getMessage());
      throw new Exception("Failed to import configuration updates!");
    }

    return $task;
  }

  /**
   * Setup elastic search.
   *
   * @command init:service:search
   */
  public function initServiceSearch() {
    $config = $this->getConfig();
    $this->say('> init:recipe-search');
    $landoFileConfig = Yaml::parse(file_get_contents($this->getDocroot() . '/.lando.yml', 128));
    $this->say('> Checking if there is search service is setup.');
    if (!array_key_exists('search', $landoFileConfig['services'])) {
      $landoFileConfig['services']['search'] = [
        'type' => 'elasticsearch:7',
        'portforward' => TRUE,
        'mem' => '1025m',
        'environment' => [
          'cluster.name=' . $config['project']['machine_name'],
        ],
      ];
      file_put_contents($this->getDocroot() . '/.lando.yml', Yaml::dump($landoFileConfig, 5, 2));
      $this->say('> Lando configurations are updated with search service.\n');

      // Get the elasticsearch_connector module from Github,
      // since the Drupal module is not Drupal 9 compatible yet.
      $this->say('> Adding the Elasticsearch connector package via composer. \n');
      chdir('../..');
      $this->_exec(
        "composer config repositories.elasticsearch_connector '{\"type\": \"package\", \"package\": {
          \"name\": \"malabya/elasticsearch_connector\",
          \"type\": \"drupal-module\",
          \"require\": {
              \"nodespark/des-connector\": \"7.x-dev\",
              \"makinacorpus/php-lucene\": \"^1.0.2\"
          },
          \"version\": \"7.0-dev\",
          \"source\": {
              \"type\": \"git\",
              \"url\": \"https://github.com/malabya/elasticsearch_connector.git\",
              \"reference\": \"8.x-7.x\"
          }
      }}' --no-interaction --ansi --verbose"
      );

      $this->taskComposerRequire()->dependency('malabya/elasticsearch_connector', '7.0-dev')->ansi()->run();
    }
    else {
      $this->say('> Search service exists in the lando configuration. Skipping...');
    }
  }

  /**
   * Setup redis.
   *
   * @command init:service:cache
   */
  public function initServiceCache() {
    $this->say('> init:recipe-redis');
    $landoFileConfig = Yaml::parse(file_get_contents($this->getDocroot() . '/.lando.yml', 128));
    $this->say('> Checking if there is cache service is setup.');
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
      $this->say('> Lando configurations are updated with cache service.\n');

      $this->say('> Adding the Drupal Redis module via composer. \n');
      chdir('../..');
      $this->taskComposerRequire()->dependency('drupal/redis', '^1.4')->ansi()->run();
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
      $this->say('> Cache service exists in the lando configuration. Skipping...');
    }
  }

  /**
   * Validate Composer.
   *
   * @command validate:composer
   */
  public function validateComposer() {
    $this->say("Validating composer.json and composer.lock...");
    $result = $this->taskExecStack()
      ->dir($this->getDocroot())
      ->exec('composer validate --no-check-all --ansi')
      ->exec('composer normalize --dry-run')
      ->run();
    if (!$result->wasSuccessful()) {
      $this->say($result->getMessage());
      $this->logger->error("composer.lock is invalid.");
      $this->say("If this is simply a matter of the lock file being out of date, you may attempt to use `composer update --lock` to quickly generate a new hash in your lock file.");
      $this->say("Otherwise, `composer update` is likely necessary.");
      throw new Exception("composer.lock is invalid!");
    }
  }

  /**
   * Validate PHP Code sniffer.
   *
   * @command validate:phpcs:sniff
   */
  public function runPhpcs() {
    $this->say("Validating Drupal coding standards...");
    $fs = new Filesystem();
    if ($fs->exists($this->getDocroot() . '/docroot/modules/custom')) {
      $this->taskExecStack()
        ->stopOnFail()
        ->exec('phpcs -s --standard=Drupal --extensions=php,module,inc,install,profile,theme,yml docroot/modules/custom')
        ->exec('phpcs -s --standard=DrupalPractice --extensions=php,module,inc,install,profile,theme,yml docroot/modules/custom')
        ->run();
    }
    else {
      $this->say("No custom modules found. Skipping...");
    }

    if ($fs->exists($this->getDocroot() . '/docroot/themes/custom')) {
      $this->taskExecStack()
        ->stopOnFail()
        ->exec('phpcs -s --standard=Drupal --extensions=inc,theme,yml docroot/themes/custom')
        ->exec('phpcs -s --standard=DrupalPractice --extensions=inc,theme,yml docroot/themes/custom')
        ->run();
    }
    else {
      $this->say("No custom themes found. Skipping...");
    }
  }

  /**
   * Lint frontend source.
   *
   * @command validate:frontend:src
   */
  public function lintFrontendSrc() {
    $this->say("Validating Frontend source files...");
    $config = $this->getConfig();
    $fs = new Filesystem();
    if ($fs->exists($this->getDocroot() . '/docroot/themes/custom')) {
      chdir($this->getDocroot() . '/docroot/themes/custom/' . $config['project']['machine_name'] . '_theme');
      $this->taskExecStack()
        ->stopOnFail()
        ->exec('yarn install')
        ->exec('yarn lint')
        ->run();
    }
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

  /**
   * Returns the site UUID stored in exported configuration.
   *
   * @param string $cm_core_key
   *   Cm core key.
   *
   * @return null
   *   Mixed.
   */
  protected function getExportedSiteUuid() {
    $site_config_file = $this->getDocroot() . '/config/sync/system.site.yml';
    if (file_exists($site_config_file)) {
      $site_config = Yaml::parseFile($site_config_file);
      $site_uuid = $site_config['uuid'];

      return $site_uuid;
    }

    return NULL;
  }

}

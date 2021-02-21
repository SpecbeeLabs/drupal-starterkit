<?php

// @codingStandardsIgnoreStart

/**
 * Base tasks for project's console commands configuration for Robo task runner.
 *
 * @class RoboFile
 * @codeCoverageIgnore
 */

use DrupalFinder\DrupalFinder;
use Robo\Robo;
use Robo\Tasks;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class RoboFile extends Tasks {

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


  // +++++++++++++++++++++++++ Repository initialization ++++++++++++++++++++++++++++++++++ //

  /**
   * Initializes the project repo and performs initial commit.
   */
  public function initRepo() {
    $this->say('init:repo');
    $collection = $this->collectionBuilder();
    $collection->addTask($this->copyDefaultDrushAlias());
    $collection->addTask($this->initDrushAlias());
    $collection->addTask($this->initLando());
    $collection->addTask($this->initGrumphp());

    return $collection->run();
  }

  /**
   * Copy the default.sites.yml to project.site.yml.
   */
  public function copyDefaultDrushAlias() {
    $this->say('copy:default-drush-alias');
    $config = Robo::config();
    $drushPath = $this->getDocroot() . '/drush/sites';
    $aliasPath = $drushPath . '/' . $config->get('project.machine_name') . '.site.yml';

    // Skip if alias file is already generated.
    if (!file_exists($aliasPath)) {
      $task = $this->taskFilesystemStack()
        ->rename($drushPath . "/default.site.yml", $aliasPath, FALSE);
    }
    else {
      $this->say("Drush alias file exists. Skipping");
      $task = $this->taskFilesystemStack()
        ->rename($aliasPath, $aliasPath, TRUE);
    }

    return $task;
  }

  /**
   * Setup the Drupal aliases.
   */
  public function initDrushAlias() {
    $this->say('setup:drupal-alias');
    $config = Robo::config();
    $drushFile = $this->getDocroot() . '/drush/sites/' . $config->get('project.machine_name') . '.site.yml';
    if (empty($config->get('remote.dev.host')) ||
    empty($config->get('remote.dev.user')) ||
    empty($config->get('remote.dev.root')) ||
    empty($config->get('remote.dev.uri')) ||
    empty($config->get('remote.stage.host')) ||
    empty($config->get('remote.stage.user')) ||
    empty($config->get('remote.stage.root')) ||
    empty($config->get('remote.stage.uri'))) {
      echo 'Drush aliases were not properly configured. Please configure the information about remote server and run "robo setup:drush-alias to setup the Drush aliases."';
      echo "\n";
    }
    $task = $this->taskReplaceInFile($drushFile)
      ->from(['#REMOTE_DEV_HOST', '#REMOTE_DEV_USER', '#REMOTE_DEV_ROOT', '#REMOTE_DEV_URI', '#REMOTE_STAGE_HOST', '#REMOTE_STAGE_USER', '#REMOTE_STAGE_ROOT', '#REMOTE_STAGE_URI'])
      ->to([$config->get('remote.dev.host'), $config->get('remote.dev.user'), $config->get('remote.dev.root'), $config->get('remote.dev.uri'), $config->get('remote.stage.host'), $config->get('remote.stage.user'), $config->get('remote.stage.root'), $config->get('remote.stage.uri')]);

    return $task;
  }

  /**
   * Setup lando.yml for local environment.
   */
  public function initLando() {
    $this->say('setup:lando');
    $config = Robo::config();
    $landoFile = $this->getDocroot() . '/.lando.yml';
    $task = $this->taskReplaceInFile($landoFile)
      ->from('#PROJECT_NAME')
      ->to($config->get('project.machine_name'));

    return $task;
  }

  /**
   * Setup Grumphp file.
   */
  public function initGrumphp() {
    $this->say('setup:grumphp');
    $config = Robo::config();
    $file = $this->getDocroot() . '/grumphp.yml';
    $task = $this->taskReplaceInFile($file)
      ->from('${PROJECT_PREFIX}')
      ->to($config->get('project.prefix'));

    return $task;
  }

  /**
   * Initialize git and make an empty initial commit.
   */
  public function initGit() {
    $this->say('setup:git');
    $config = Robo::config();
    $task = $this->taskGitStack()
      ->stopOnFail()
      ->exec('git init')
      ->exec('git remote add origin ' . $config->get('project.repo'))
      ->add('-A')
      ->commit($config->get('project.prefix') . '-000: Created project from Specbee boilerplate.');

    return $task;
  }

  // +++++++++++++++++++++++++++++ Drupal Commands ++++++++++++++++++++++++++++++++++++++++ //

  /**
   * Setup a fresh Drupal site from existing config if present.
   */
  public function setup($env = 'local') {
    $this->say('Setting up local environment...');
    $collection = $this->collectionBuilder();
    $collection->addTask($this->installDependencies());
    $collection->addTask($this->taskExec('composer setup-local'));
    $collection->addTask($this->drupalInstall($env));
    return $collection->run();
  }

  /**
   * Setup Drupal site.
   */
  public function drupalInstall($env = 'local') {
    $this->say('drupal:install');
    $config = Robo::config();
    $task = $this->drush()
      ->args("site-install")
      ->arg('lightning');
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

  /**
   * Update Drupal & sync pending configurations.
   *
   * @command drupal:update
   */
  public function drupalUpdate() {
    $this->say('drupal:update');
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
    $this->say('drupal:update:db');
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
    $this->say('sync:db');
    $config = Robo::config();
    $remote_alias = '@' . $config->get('project.machine_name') . '.' . $config->get('sync.remote');
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
    if ($config->get('sync.sanitize') === TRUE) {
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
    $this->say('sync:files');
    $config = Robo::config();
    $remote_alias = '@' . $config->get('project.machine_name') . '.' . $config->get('sync.remote');
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
    $this->say('import:config');

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

  // ++++++++++++++++++++++++++++++ Frontend tools ++++++++++++++++++++++++++++++++++++++++//

  /**
   * Build theme dependencies.
   *
   * @command build:frontend:reqs
   */
  public function buildFrontendReqs() {
    $this->say('build:frontend:reqs');
    $config = Robo::config();
    if (is_dir($this->getDocroot() . '/docroot/themes/custom/' . $config->get('project.machine_name') . '_theme')) {
      $task = $this->taskExecStack()
      ->dir($this->getDocroot() . '/docroot/themes/custom/' . $config->get('project.machine_name') . '_theme')
      ->exec('yarn install')
      ->exec('yarn build')
      ->run();

      return $task;
    }
    else {
      $this->say("No theme found.");
    }
  }

  // ++++++++++++++++++++++++++++ Service initialization +++++++++++++++++++++++++++++++++++//

  /**
   * Setup elastic search.
   *
   * @command init:service:search
   */
  public function initServiceSearch() {
    $config = Robo::config();
    $this->say('init:recipe-search');
    $landoFileConfig = Yaml::parse(file_get_contents($this->getDocroot() . '/.lando.yml', 128));
    $this->say('> Checking if there is search service is setup.');
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
      $this->say('> Lando configurations are updated with search service.\n');

      // Get the elasticsearch_connector module from Github,
      // since the Drupal module is not Drupal 9 compatible yet.
      $this->say('> Adding the Elasticsearch connector package via composer. \n');
      $this->taskComposerRequire()->dependency('drupal/elasticsearch_connector', '^7.0@alpha')->ansi()->run();
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
    $this->say('init:recipe-redis');
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

  // ++++++++++++++++++++++++++++ Validate Code ++++++++++++++++++++++++++++++++++++++++++++//

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
    $tasks = [];
    $this->say("Validating Drupal coding standards...");
    $tasks[] = $this->taskExecStack()
        ->exec('vendor/bin/phpcs --config-set installed_paths vendor/drupal/coder/coder_sniffer');
    $tasks[] = $this->taskFilesystemStack()
        ->mkdir('artifacts/phpcs');
    $fs = new Filesystem();
    if ($fs->exists($this->getDocroot() . '/docroot/modules/custom')) {
    $tasks[] = $this->taskExecStack()
        ->stopOnFail()
        ->exec('phpcs -s --standard=Drupal --extensions=php,module,inc,install,profile,theme,yml docroot/modules/custom')
        ->exec('phpcs -s --standard=DrupalPractice --extensions=php,module,inc,install,profile,theme,yml docroot/modules/custom');
    }
    else {
      $this->say("No custom modules found. Skipping...");
    }

    if ($fs->exists($this->getDocroot() . '/docroot/themes/custom')) {
    $tasks[] = $this->taskExecStack()
        ->stopOnFail()
        ->exec('phpcs -s --standard=Drupal --extensions=inc,theme,yml docroot/themes/custom')
        ->exec('phpcs -s --standard=DrupalPractice --extensions=inc,theme,yml docroot/themes/custom');
    }
    else {
      $this->say("No custom themes found. Skipping...");
    }

    $collection = $this->collectionBuilder();
    $collection->addTaskList($tasks);
    return $collection->run();
  }

  /**
   * Lint frontend source.
   *
   * @command validate:frontend:src
   */
  public function lintFrontendSrc() {
    $this->say("Validating Frontend source files...");
    $config = Robo::config();
    $fs = new Filesystem();
    if ($fs->exists($this->getDocroot() . '/docroot/themes/custom')) {
      chdir($this->getDocroot() . '/docroot/themes/custom/' . $config->get('project.machine_name') . '_theme');
      $task = $this->taskExecStack()
        ->stopOnFail()
        ->exec('yarn lint')
        ->run();

      return $task;
    }
    else {
      $this->say("No theme found. Skipping...");
    }
  }

  // +++++++++++++++++++++++++++++++ Testing +++++++++++++++++++++++++++++++++++++++++++++++//

  /**
   * Run behat tests.
   *
   * @command test:run:behat
   */
  public function runBehatTests() {
    $this->say("Running Behat tests...");
    chdir($this->getDocroot() . '/tests/behat');
    return $this->taskExec('behat');
  }

  /**
   * Run PHPUnit tests.
   *
   * @command test:run:phpunit
   */
  public function runPhpUnitTests() {
    $this->say("Running PHPUnit tests...");
    return $this->taskExec('simple-phpunit --config ' . $this->getDocroot() . '/tests/phpunit/phpunit.xml ' . $this->getDocroot() . '/tests/phpunit/');
  }

  // +++++++++++++++++++++++++++ Build artifact ++++++++++++++++++++++++++++++++++++++++++++//

  /**
   * Build the application artifact.
   *
   * @command build:artifact
   */
  public function buildArtifact() {
    $this->say('build:artifact');
    $this->installDependencies();
    $this->drupalUpdate();
    $this->buildFrontendReqs();
    $this->drush()->arg('cache-rebuild');
  }


  // +++++++++++++++++++++++++++ Helpers +++++++++++++++++++++++++++++++++++++++++++++++++++//

  /**
   * Return drush with default arguments.
   *
   * @return \Robo\Task\Base\Exec
   *   A drush exec command.
   */
  protected function drush() {
    // Drush needs an absolute path to the docroot.
    $docroot = $this->getDocroot() . '/docroot';
    return $this->taskExec('vendor/bin/drush')
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

  /**
   * Installs composer dependencies.
   *
   * @return \Robo\Contract\TaskInterface
   *   A task instance.
   */
  protected function installDependencies() {
    chdir($this->getDocroot());
    return $this->taskComposerInstall();
  }

}


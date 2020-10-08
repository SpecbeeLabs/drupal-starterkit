<?php

// @codingStandardsIgnoreStart

/**
 * Base tasks for setting up a module to test within a full Drupal environment.
 *
 * This file expects to be called from the root of a Drupal site.
 *
 * @class RoboFile
 * @codeCoverageIgnore
 */

use Robo\Tasks;

/**
 *
 */
class RoboFile extends Tasks {

  /**
   * The database URL.
   *
   * @var string
   */
  const DB_URL = 'mysql://root@127.0.0.1/drupal8';

  /**
   * Command to check for Drupal's Coding Standards.
   *
   * @return \Robo\Result
   *   The result of the collection of tasks.
   */
  public function jobCheckCodingStandards() {
    $collection = $this->collectionBuilder();
    $collection->addTask($this->installDependencies());
    $collection->addTaskList($this->runCodeSniffer());
    return $collection->run();
  }

  /**
   * Command to validate frontend code.
   *
   * @return \Robo\Result
   *   The result of the collection of tasks.
   */
  public function jobValidateFrontEnd() {
    $collection = $this->collectionBuilder();
    $collection->addTaskList($this->runYarnLint());
    return $collection->run();
  }

  /**
   * Command to run behat tests.
   *
   * @return \Robo\Result
   *   The result tof the collection of tasks.
   */
  public function jobRunBehatTests() {
    $collection = $this->collectionBuilder();
    $collection->addTask($this->installDependencies());
    $collection->addTask($this->waitForDatabase());
    $collection->addTask($this->installDrupal());
    $collection->addTaskList($this->runYarn());
    $collection->addTaskList($this->runBehatTests());
    return $collection->run();
  }

  /**
   * Runs Behat tests.
   *
   * @return \Robo\Task\Base\Exec[]
   *   An array of tasks.
   */
  protected function runBehatTests() {
    $force = TRUE;
    $tasks = [];
    $tasks[] = $this->taskExec('service apache2 start');
    $tasks[] = $this->taskFilesystemStack()
      ->copy('.circleci/config/behat.yml', 'behat.yml', $force);
    $tasks[] = $this->taskExec("vendor/bin/behat");
    return $tasks;
  }

  /**
   * Installs composer dependencies.
   *
   * @return \Robo\Contract\TaskInterface
   *   A task instance.
   */
  protected function installDependencies() {
    return $this->taskComposerInstall();
  }

  /**
   * Waits for the database service to be ready.
   *
   * @return \Robo\Contract\TaskInterface
   *   A task instance.
   */
  protected function waitForDatabase() {
    return $this->taskExec('dockerize -wait tcp://localhost:3306 -timeout 1m');
  }

  /**
   * Install Drupal.
   *
   * @return \Robo\Task\Base\Exec
   *   A task to install Drupal.
   */
  protected function installDrupal() {
    $task = $this->drush()
      ->args('site-install')
      ->args('sdnn')
      ->option('verbose')
      ->option('yes')
      ->option('db-url', static::DB_URL, '=');
    return $task;
  }

  /**
   * Build theme dependencies.
   *
   * @return \Robo\Task\Base\Exec[]
   */
  protected function runYarn() {
    $task = [];
    $task[] = $this->taskExecStack()
      ->dir('docroot/profiles/custom/sdnn/themes/sdnn_theme')
      ->exec('yarn install')
      ->exec('yarn build');
    return $task;
  }

  /**
   * Sets up and runs code sniffer.
   *
   * @return \Robo\Task\Base\Exec[]
   *   An array of tasks.
   */
  protected function runCodeSniffer() {
    $tasks = [];
    $tasks[] = $this->taskExecStack()
      ->exec('vendor/bin/phpcs -s --standard=Drupal --extensions=php,module,inc,install,profile,theme,yml docroot/modules/custom')
      ->exec('vendor/bin/phpcs -s --standard=DrupalPractice --extensions=php,module,inc,install,profile,theme,yml docroot/modules/custom')
      ->exec('vendor/bin/phpcs -s --standard=Drupal --extensions=php,module,inc,install,profile,theme,yml docroot/themes/custom')
      ->exec('vendor/bin/phpcs -s --standard=DrupalPractice --extensions=php,module,inc,install,profile,theme,yml docroot/themes/custom');
    return $tasks;
  }

  /**
   * Sets up and runs yarn lint for frontend linting.
   *
   * @return \Robo\Task\Base\Exec[]
   *   An array of tasks.
   */
  protected function runYarnLint() {
    $task = [];
    $task[] = $this->taskExecStack()
      ->dir('docroot/themes/custom' . $_ENV("project-name") . "_theme")
      ->exec('yarn install')
      ->exec('yarn lint');
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
    return $this->taskExec('vendor/bin/drush')
      ->option('root', $docroot, '=');
  }

  /**
   * Get the absolute path to the docroot.
   *
   * @return string
   */
  protected function getDocroot() {
    $docroot = (getcwd());
    return $docroot;
  }

}

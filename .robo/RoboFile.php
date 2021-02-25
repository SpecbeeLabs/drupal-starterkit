<?php

// @codingStandardsIgnoreStart

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */


use DrupalFinder\DrupalFinder;
use Robo\Robo;
use Robo\Tasks;
use Symfony\Component\Yaml\Yaml;

/**
 * Base RoboFile to define the generic methods.
 */
class RoboFile extends Tasks {

  /**
   * Construct a new Robofile to set the config file localtion.
   */
  public function __construct() {
    Robo::loadConfiguration([$this->getDocroot() . '/.robo/config.yml']);
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
    return $this->taskComposerInstall()->ansi()->noInteraction();
  }

}

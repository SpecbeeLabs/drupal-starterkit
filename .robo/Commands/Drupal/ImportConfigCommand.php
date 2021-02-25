<?php

// @codingStandardsIgnoreStart

require '../../RoboFile.php';

/**
 * Defind the config import commands.
 */
class ImportConfigCommand extends RoboFile {

  /**
   * Import pending configurations.
   */
  public function importConfig() {
    $this->say('import:config');
    $this->installDependencies()
      ->run();
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
      $this->io()->error($task->getMessage());
      throw new Exception("Failed to import configuration updates!");
    }

    return $task;
  }

}

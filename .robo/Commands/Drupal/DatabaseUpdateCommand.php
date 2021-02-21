<?php

// @codingStandardsIgnoreStart

require '../../RoboFile.php';

/**
 * Defind the update commands.
 */
class DatabaseUpdateCommand extends RoboFile {

  /**
   * Install composer dependencies & run database updates.
   *
   * @command drupal:update
   */
  public function drupalUpdate() {
    $this->say('drupal:update');
    $this->installDependencies();
    $this->drush()->arg('cache-rebuild');
    $this->updateDatabase();
  }

  /**
   * Update database.
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

}

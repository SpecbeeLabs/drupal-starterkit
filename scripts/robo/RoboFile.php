<?php

// @codingStandardsIgnoreStart

use DrupalProject\composer\ScriptHandler;

/**
 * This is project's console commands configuration for Robo task runner.
 */
class RoboFile extends \Robo\Tasks
{
    /**
   * Initializes the project repo and performs initial commit.
   */
  public function initRepo() {
    $config = ScriptHandler::getConfig();
    // The .git dir will already exist if blt-project was created using a
    // branch. Otherwise, it will not exist when using a tag.
    $result = $this->taskGitStack()
    ->stopOnFail()
    ->exec('git init')
    ->exec('git remote add origin ' . $config['project']['repo'])
    ->add('-A')
    ->commit($config['project']['prefix'] . '-000: Created project from Specbee boilerplate' )
    ->run();

    if (!$result->wasSuccessful()) {
        throw new Exception("Could not initialize new git repository.");
    }
  }
}

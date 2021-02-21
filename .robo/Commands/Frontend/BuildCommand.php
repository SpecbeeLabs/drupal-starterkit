<?php

// @codingStandardsIgnoreStart

use Robo\Robo;
require '../../RoboFile.php';

/**
 * Deinfes the Frontned build command.
 */
class BuildCommand extends RoboFile {

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

}

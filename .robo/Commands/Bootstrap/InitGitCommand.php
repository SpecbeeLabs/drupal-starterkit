<?php

// @codingStandardsIgnoreStart

use Robo\Robo;
require '../../RoboFile.php';

/**
 * Define the Git commands.
 */
class InitGitCommand extends RoboFile {

  /**
   * Initialize git and make an empty initial commit.
   */
  public function initGit() {
    $this->say('setup:git');
    chdir($this->getDocroot());
    $config = Robo::config();
    $this->taskGitStack()
      ->stopOnFail()
      ->exec("git init")
      ->commit('Initial commit.', '--allow-empty')
      ->add('-A')
      ->commit($config->get('project.prefix') . '-000: Created project from Specbee boilerplate.')
      ->interactive(FALSE)
      ->printOutput(FALSE)
      ->printMetadata(FALSE)
      ->run();
  }

}

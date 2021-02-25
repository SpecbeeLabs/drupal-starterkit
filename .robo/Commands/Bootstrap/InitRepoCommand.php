<?php

// @codingStandardsIgnoreStart

use Robo\Robo;
require '../../RoboFile.php';

/**
 * Defines the bootstrap commands.
 */
class InitRepoCommand extends RoboFile {

  /**
   * Initializes the project repo and performs initial commit.
   */
  public function initRepo() {
    $this->say('init:repo');
    $this->copyDefaultDrushAlias();
    $this->confDrushAlias();
    $this->confLando();
    $this->confGrumphp();
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
    if (file_exists($aliasPath)) {
      $this->io()->success('Drush alias file exists. Skipping');
      return;
    }
    else {
      $this->taskFilesystemStack()
        ->rename($drushPath . "/default.site.yml", $aliasPath, FALSE)
        ->run();
    }
  }

  /**
   * Setup the Drupal aliases.
   */
  public function confDrushAlias() {
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
      $this->io()->note('Drush aliases were not properly configured.');
      $this->io()->note('Please add the information about remote server and run the command again.');
      return;
    }
    $this->taskReplaceInFile($drushFile)
      ->from(['${REMOTE_DEV_HOST}', '${REMOTE_DEV_USER}', '${REMOTE_DEV_ROOT}', '${REMOTE_DEV_URI}', '${REMOTE_STAGE_HOST}', '${REMOTE_STAGE_USER}', '${REMOTE_STAGE_ROOT}', '${REMOTE_STAGE_URI}'])
      ->to([$config->get('remote.dev.host'), $config->get('remote.dev.user'), $config->get('remote.dev.root'), $config->get('remote.dev.uri'), $config->get('remote.stage.host'), $config->get('remote.stage.user'), $config->get('remote.stage.root'), $config->get('remote.stage.uri')])
      ->run();
  }

  /**
   * Setup lando.yml for local environment.
   */
  public function confLando() {
    $this->say('setup:lando');
    $config = Robo::config();
    $landoFile = $this->getDocroot() . '/.lando.yml';
    $task = $this->taskReplaceInFile($landoFile)
      ->from('${PROJECT_NAME}')
      ->to($config->get('project.machine_name'))
      ->run();
    if ($task->wasSuccessful()) {
      $this->io()->success("The .lando.yml was successfully initialiazed and configured.");
    }
    else {
      $this->io()->error($task->getMessage());
      throw new Exception("Failed to udpate .lando.yml file!");
    }
  }

  /**
   * Setup Grumphp file.
   */
  public function confGrumphp() {
    $this->say('setup:grumphp');
    $config = Robo::config();
    $file = $this->getDocroot() . '/grumphp.yml';
    $this->taskReplaceInFile($file)
      ->from('${PROJECT_PREFIX}')
      ->to($config->get('project.prefix'))
      ->run();
  }

}

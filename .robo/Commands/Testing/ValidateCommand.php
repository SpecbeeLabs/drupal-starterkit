<?php

// @codingStandardsIgnoreStart

use Robo\Robo;
use Symfony\Component\Filesystem\Filesystem;
require '../../RoboFile.php';

/**
 * Defines the validate commands.
 */
class ValidateCommand extends RoboFile {

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
      $this->io()->error($result->getMessage());
      $this->logger->error("composer.lock is invalid.");
      $this->io()->note("If this is simply a matter of the lock file being out of date, you may attempt to use `composer update --lock` to quickly generate a new hash in your lock file.");
      $this->io()->note("Otherwise, `composer update` is likely necessary.");
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
      $this->io()->note("No custom modules found. Skipping...");
    }

    if ($fs->exists($this->getDocroot() . '/docroot/themes/custom')) {
      $tasks[] = $this->taskExecStack()
        ->stopOnFail()
        ->exec('phpcs -s --standard=Drupal --extensions=inc,theme,yml docroot/themes/custom')
        ->exec('phpcs -s --standard=DrupalPractice --extensions=inc,theme,yml docroot/themes/custom');
    }
    else {
      $this->io()->note("No custom themes found. Skipping...");
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
      chdir($this->getDocroot() . '/docroot/themes/custom/' . $config->get('project.config.theme'));
      $task = $this->taskExecStack()
        ->stopOnFail()
        ->exec('yarn lint')
        ->run();

      return $task;
    }
    else {
      $this->io()->note("No theme found. Skipping...");
    }
  }

}

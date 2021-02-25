<?php

// @codingStandardsIgnoreStart

require '../../RoboFile.php';

/**
 * Defines the test commands.
 */
class TestCommand extends RoboFile {

  /**
   * Run behat tests.
   *
   * @command test:run:behat
   */
  public function runBehatTests() {
    $this->say("Running Behat tests...");
    chdir($this->getDocroot() . '/tests/behat');
    return $this->taskExec('behat')->run();
  }

  /**
   * Run PHPUnit tests.
   *
   * @command test:run:phpunit
   */
  public function runPhpUnitTests() {
    $this->say("Running PHPUnit tests...");
    return $this->taskExec('simple-phpunit --config ' . $this->getDocroot() . '/tests/phpunit/phpunit.xml ' . $this->getDocroot() . '/tests/phpunit/')->run();
  }

}

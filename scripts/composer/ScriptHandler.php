<?php

// @codingStandardsIgnoreStart

namespace DrupalProject\composer;

use Composer\Script\Event;
use Drupal\Core\Site\Settings;
use DrupalFinder\DrupalFinder;
use Symfony\Component\Filesystem\Filesystem;
use Webmozart\PathUtil\Path;

/**
 * Composer scripts for setup tasks and files.
 *
 * @codeCoverageIgnore
 */
class ScriptHandler {

  /**
   * Create the require files.
   *
   * @param \Composer\Script\Event $event
   *   An event object.
   */
  public static function createRequiredFiles(Event $event) {
    $fs = new Filesystem();
    $drupalFinder = new DrupalFinder();
    $drupalFinder->locateRoot(getcwd());
    $drupalRoot = $drupalFinder->getDrupalRoot();

    $dirs = [
      'modules',
      'profiles',
      'themes',
    ];

    // Required for unit testing.
    foreach ($dirs as $dir) {
      if (!$fs->exists($drupalRoot . '/' . $dir)) {
        $fs->mkdir($drupalRoot . '/' . $dir);
        $fs->touch($drupalRoot . '/' . $dir . '/.gitkeep');
      }
    }

    // Prepare the settings file for installation.
    if (!$fs->exists($drupalRoot . '/sites/default/settings.php') && $fs->exists($drupalRoot . '/sites/default/default.settings.php')) {
      $fs->copy($drupalRoot . '/sites/default/default.settings.php', $drupalRoot . '/sites/default/settings.php');
      require_once $drupalRoot . '/core/includes/bootstrap.inc';
      require_once $drupalRoot . '/core/includes/install.inc';
      new Settings([]);
      $fs->chmod($drupalRoot . '/sites/default/settings.php', 0666);
      $event->getIO()->write("Created a sites/default/settings.php file with chmod 0666");
    }
    else {
      $event->getIO()->write("<info>Settings files is already present. Moving on!!!...</info>");
    }

    // Create the files directory with chmod 0777.
    if (!$fs->exists($drupalRoot . '/sites/default/files')) {
      $oldmask = umask(0);
      $fs->mkdir($drupalRoot . '/sites/default/files', 0777);
      umask($oldmask);
      $event->getIO()->write("Created a sites/default/files directory with chmod 0777");
    }
    else {
      $event->getIO()->write("<info>The sites/default/files directory is already present.</info>");
    }
  }

  /**
   * Setup local settings.php file.
   */
  public static function createLocalSettingsFile(Event $event) {
    $fs = new Filesystem();
    $drupalFinder = new DrupalFinder();
    $drupalFinder->locateRoot(getcwd());
    $drupalRoot = $drupalFinder->getDrupalRoot();
    if (!$fs->exists($drupalRoot . '/sites/default/settings.local.php') && $fs->exists($drupalRoot . '/sites/example.settings.local.php')) {
      $fs->copy($drupalRoot . '/sites/example.settings.local.php', $drupalRoot . '/sites/default/settings.local.php');
      $fs->chmod($drupalRoot . '/sites/default/settings.local.php', 0666);
      $event->getIO()->write("Created a sites/default/settings.local.php file with chmod 0666 for local setup.");
    }
  }

  /**
   * Setup migration.settings.php file.
   */
  public static function createMigrationSettingsFile(Event $event) {
    $fs = new Filesystem();
    $drupalFinder = new DrupalFinder();
    $drupalFinder->locateRoot(getcwd());
    $drupalRoot = $drupalFinder->getDrupalRoot();
    if (!$fs->exists($drupalRoot . '/sites/default/migration.settings.php') && $fs->exists($drupalRoot . '/sites/default/migration.settings.php')) {
      $fs->copy($drupalRoot . '../vendor/specbee/robo-tooling/settings/migration.settings.php', $drupalRoot . '/sites/default/migration.settings.php');
      $fs->chmod($drupalRoot . '/sites/default/migration.settings.php', 0666);
      $event->getIO()->write("Created a sites/default/migration.settings.php file with chmod 0666 for migration connection settings.");
    }
  }

}

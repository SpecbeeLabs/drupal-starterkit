<?php

// @codingStandardsIgnoreStart

namespace DrupalProject\composer;

use Composer\Script\Event;
use Composer\Semver\Comparator;
use Drupal\Core\Site\Settings;
use DrupalFinder\DrupalFinder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;
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
      $settings['settings']['config_sync_directory'] = (object) [
        'value' => Path::makeRelative($drupalFinder->getComposerRoot() . '/config/sync', $drupalRoot),
        'required' => TRUE,
      ];
      drupal_rewrite_settings($settings, $drupalRoot . '/sites/default/settings.php');
      $fs->chmod($drupalRoot . '/sites/default/settings.php', 0666);
      $event->getIO()->write("Created a sites/default/settings.php file with chmod 0666");
    }
    else {
      $event->getIO()->write("<info>All required files are created. Good to go!!!...</info>");
    }

    // Create the files directory with chmod 0777.
    if (!$fs->exists($drupalRoot . '/sites/default/files')) {
      $oldmask = umask(0);
      $fs->mkdir($drupalRoot . '/sites/default/files', 0777);
      umask($oldmask);
      $event->getIO()->write("Created a sites/default/files directory with chmod 0777");
    }
    else {
      $event->getIO()->write("<info>The sites/default/files directory is already present!!!...</info>");
    }
  }

  /**
   * Checks if the installed version of Composer is compatible.
   *
   * Composer 1.0.0 and higher consider a `composer install` without having a
   * lock file present as equal to `composer update`. We do not ship with a lock
   * file to avoid merge conflicts downstream, meaning that if a project is
   * installed with an older version of Composer the scaffolding of Drupal will
   * not be triggered. We check this here instead of in drupal-scaffold to be
   * able to give immediate feedback to the end user, rather than failing the
   * installation after going through the lengthy process of compiling and
   * downloading the Composer dependencies.
   *
   * @see https://github.com/composer/composer/pull/5035
   */
  public static function checkComposerVersion(Event $event) {
    $composer = $event->getComposer();
    $io = $event->getIO();

    $version = $composer::VERSION;

    // The dev-channel of composer uses the git revision as version number,
    // try to the branch alias instead.
    if (preg_match('/^[0-9a-f]{40}$/i', $version)) {
      $version = $composer::BRANCH_ALIAS_VERSION;
    }

    // If Composer is installed through git we have no easy way to determine if
    // it is new enough, just display a warning.
    if ($version === '@package_version@' || $version === '@package_branch_alias_version@') {
      $io->writeError('<warning>You are running a development version of Composer. If you experience problems, please update Composer to the latest stable version.</warning>');
    }
    elseif (Comparator::lessThan($version, '1.0.0')) {
      $io->writeError('<error>Drupal-project requires Composer version 1.0.0 or higher. Please update your Composer before continuing</error>.');
      exit(1);
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
   * Setup Drush aliases.
   */
  public static function setupDrupalAlias(Event $event) {
    $config = self::getConfig();
    $fs = new Filesystem();
    $root = self::getRoot();

    if (!$fs->exists($root . '/drush/sites/' . $config['project']['machine_name'] . 'site.yml')) {
      $fs->rename($root . '/drush/sites/default.site.yml', $root . '/drush/sites/' . $config['project']['machine_name'] . '.site.yml');
      $drush_config = Yaml::parse(file_get_contents($root . '/drush/sites/' . $config['project']['machine_name'] . '.site.yml'));
      foreach ($config['drush'] as $env => $value) {
        $drush_config[$env]['host'] = $value['host'];
        $drush_config[$env]['user'] = $value['user'];
        $drush_config[$env]['root'] = $value['root'];
        $drush_config[$env]['uri'] = $value['uri'];
      }
      file_put_contents($root . '/drush/sites/' . $config['project']['machine_name'] . '.site.yml', Yaml::dump($drush_config, 5, 2));
      $event->getIO()->write("Aliases were written, type 'drush sa' to see them!");
    }
  }

  /**
   * Setup Lando.
   */
  public static function setupLando(Event $event) {
    $config = self::getConfig();
    $fs = new Filesystem();
    $root = self::getRoot();

    $lando_config = Yaml::parse(file_get_contents($root . "/.lando.yml"));
    $lando_config['name'] = $config['project']['machine_name'];
    $lando_config['services']['appserver']['environment']['DRUSH_OPTIONS_URI'] = $config['project']['machine_name'] . '.lndo.site';
    file_put_contents($root . "/.lando.yml", Yaml::dump($lando_config, 5, 2));
    $event->getIO()->write("Your app has been initialized!");
  }

  /**
   * Setup Grumphp.
   */
  public static function setupGrumphp(Event $event) {
    $config = self::getConfig();
    $fs = new Filesystem();
    $root = self::getRoot();
    $grumphp_config = Yaml::parse(file_get_contents($root . "/grumphp.yml"));
    $grumphp_config['parameters']['tasks']['git_commit_message']['matchers']['Must follow the pattern'] = '/(^' . $config['project']['prefix'] . '-[0-9]+(: )[^ ].{15,}\.)|(Merge branch (.)+)/';
    file_put_contents($root . "/grumphp.yml", Yaml::dump($grumphp_config, 5, 2));
    $event->getIO()->write("Grumphp is setup to watch the commits!");
  }

  /**
   * Get the project configurations.
   */
  public static function getConfig() {
    $drupalFinder = new DrupalFinder();
    $drupalFinder->locateRoot(getcwd());
    $root = $drupalFinder->getComposerRoot();
    $config = Yaml::parse(file_get_contents($root . "/config.yml"));
    return $config;
  }

  /**
   * Get the root folder.
   */
  public static function getRoot() {
    $drupalFinder = new DrupalFinder();
    $drupalFinder->locateRoot(getcwd());
    $root = $drupalFinder->getComposerRoot();
    return $root;
  }

}

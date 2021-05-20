# Specbee Drupal Startkit

A Composer based install method to set up Drupal projects using [Specbee's Robo tooling](https://github.com/SpecbeeLabs/robo-tooling).

## Requirements
- Lando
- Docker
- PHP >= 7.4
- Composer v2

## Usage
To create a new project, use the `composer-create` command to get the latest composer project.

```
composer create-project specbee/drupal-starterkit:9.x-dev project_name --no-interaction
```

This will scafold the Drupal repository and intialized Git in the directory with an initial commit.

Next step is to configure the project settings at `robo.yml`. Refer the file to make changes to the file as per the project requirements.

Once, done run `composer init-repo` which will:

- Setup Drush aliases
- Configure the Landofile
- Configure Grumphp for checking commits

To create a local.settings.php run `composer setup-local`

Run `lando start` to spin up the containers used to run the application.

## Setup a new site or existing site
Once the lando containers are running, run the lando command

```
lando robo setup -n
```

This will install a fresh Drupal site using the installation profile _drupal.profile_ mentioned in the `robo.yml`. After which if existing configurations are present those will be imported and theme will be build if present.

## Add new Robo commands
New commands can be added to the `RoboFile.php` available in the project root

```
<?php
/**
 * Example command
 *
 * @aliases example
 */
 public function exampleCommand()
 {
   $this->say("Hello world");
 }
?>
```

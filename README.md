# Specbee Drupal Startkit

## Intention
This repository will provide as the base starterkit for Drupal projects.

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

Next step is to configure the project settings at `.robo/config.yml`. Refer the file to make changes to the file as per the project requirements.

Once, done run `composer init-repo` which will:

- Setup Drush aliases
- Configure the Landofile
- Configure Grumphp for checking commits

Run `lando start` to spin up the containers used to run the application.

## Setup a new site or existing site
Once the lando containers are running, run the lando command

```
lando setup -n
```

This will install a fresh Drupal site using the installation profile _project.config.profile_ mentioned in the `.robo/config.yml`. After which if existing configurations are present those will be imported and theme will be build if present.

## Tooling
The package provides certain lando tooling commands for easy access and run general commands.

| Task                                            | Command                                         |
|-------------------------------------------------|-----------------------------------------------|
| Setup the site from scratch | ```lando setup``` |
| Installing a contrib project | ```lando composer require drupal/devel``` |
| Running Drush commands | ```lando drush <command>```|
| Running database updates and importing configurations| ```lando drupal:update```|
| Sync database and files from remote environment defines under _sync.remote_ in `.robo.config.yml` | ```lando sync:all```|
| Sync database from remote environment defines under _sync.remote_ in `.robo.config.yml` | ```lando sync:db```|
| Sync files from remote environment defines under _sync.remote_ in `.robo.config.yml` | ```lando sync:files```|
| Validate files - Check composer validation, Run PHPCS against modules and themes, Check SASS Lint in the theme | ```lando validate:all```|
| Initialize and setup Elasticsearch | ```lando init:service:search```|
| Initialize and setup Redis caching | ```lando init:service:cache```|
| Running Behat and PHPUnit test | ```lando test:run:all```|
| Running Behat test | ```lando test:run:behat```|
| Running PHPUnit test | ```lando test:run:phpunit```|

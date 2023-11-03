<?php

declare(strict_types=1);

use DrupalRector\Set\Drupal8SetList;
use DrupalRector\Set\Drupal9SetList;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
  // Adjust the set lists to be more granular to your Drupal requirements.
  // @todo find out how to only load the relevant rector rules.
  //   Should we try and load \Drupal::VERSION and check?
  $rectorConfig->sets([
    Drupal8SetList::DRUPAL_8,
    Drupal9SetList::DRUPAL_9,
  ]);

  $parameters = $rectorConfig->parameters();

  $rectorConfig->autoloadPaths([
    // $drupal_root is replaced with path to core when processed.
    $drupal_root . '/core',
    $drupal_root . '/modules',
    $drupal_root . '/profiles',
    $drupal_root . '/themes'
  ]);

  $rectorConfig->skip(['*/upgrade_status/tests/modules/*']);
  $rectorConfig->fileExtensions(['php', 'module', 'theme', 'install', 'profile', 'inc', 'engine']);
  $rectorConfig->importNames(true, false);
  $rectorConfig->importShortClasses(false);
  $parameters->set('drupal_rector_notices_as_comments', true);
};
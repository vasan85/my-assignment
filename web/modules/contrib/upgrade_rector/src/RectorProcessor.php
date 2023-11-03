<?php

namespace Drupal\upgrade_rector;

use Drupal\Core\Extension\Extension;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Psr\Log\LoggerInterface;

/**
 * Runs rector and processes rector results.
 */
class RectorProcessor {

   use StringTranslationTrait;

   /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * File system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Rector result storage.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $rectorResults;

  /**
   * Constructs a rector processor.
   *
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value_factory
   *   The key/value factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   File system service.
   */
  public function __construct(
    KeyValueFactoryInterface $key_value_factory,
    LoggerInterface $logger,
    FileSystemInterface $file_system
  ) {
    $this->rectorResults = $key_value_factory->get('upgrade_status_rector_results');
    $this->logger = $logger;
    $this->fileSystem = $file_system;
  }

  /**
   * Finds vendor location.
   *
   * @return string|null
   *   Vendor directory path if found, null otherwise.
   */
  protected function findVendorPath() {
    // Seamless Windows compatibility for the eventually generated rector.yml.
    $root = str_replace('\\', '/', DRUPAL_ROOT);
    // The vendor directory may be found inside the webroot (unlikely).
    if (file_exists($root . '/vendor/bin/rector')) {
      return $root . '/vendor';
    }
    // Most likely the vendor directory is found alongside the webroot.
    elseif (file_exists(dirname($root) . '/vendor/bin/rector')) {
      return dirname($root) . '/vendor';
    }
    return NULL;
  }

  /**
   * Run rector on a given extension.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   Extension to run rector on.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise. The results are saved into the
   *   result storage either way.
   */
  public function runRector(Extension $extension) {
    $vendor_path = $this->findVendorPath();
    if (empty($vendor_path)) {
      $this->logger->error('Rector executable not found. This would happen if the composer dependencies were not installed. Did you use composer to install the module?');
      return FALSE;
    }

    $system_temporary = $this->fileSystem->getTempDirectory();
    $temporary_directory = realpath($system_temporary) . '/upgrade_rector';
    $success = $this->fileSystem->prepareDirectory($temporary_directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    if (!$success) {
      $this->rectorResults->set($extension->getName(), sprintf('Unable to create temporary directory at %s.', $temporary_directory));
      $this->logger->error('Unable to create temporary directory at %directory.', ['%directory' => $temporary_directory]);
      return FALSE;
    }

    if (function_exists('drupal_get_path')) {
      // This is fallback code for versions prior to 10.0.0, specifically to
      // support 9.3.0 and earlier where the below service is not available yet.
      // The fallback code should be removed once support for Drupal 9 is
      // dropped, but it does not need "fixing".
      // @noRector
      // @phpstan-ignore-next-line
      $module_path = DRUPAL_ROOT . '/' . drupal_get_path('module', 'upgrade_rector');
    }
    else {
      $module_path = DRUPAL_ROOT . '/' . \Drupal::service('extension.list.module')->getPath('upgrade_rector');
    }
    $config = file_get_contents($module_path . '/rector-config-template.php');

    //$config = str_replace('$vendor_dir', "'" . $vendor_path . "'", $config);

    // Replace backslash for Windows compatibility.
    $config = str_replace('$drupal_root', "'" . str_replace('\\', '/', DRUPAL_ROOT) . "'", $config);
    $config_path = $temporary_directory . '/rector-config.php';

    $success = file_put_contents($config_path, $config);
    if (!$success) {
      $this->rectorResults->set($extension->getName(), sprintf('Unable to write rector configuration to %s.', $config_path));
      $this->logger->error('Unable to write rector configuration to %file.', ['%file' => $config_path]);
      return FALSE;
    }

    $output = [];
    $cmd = 'cd ' . dirname($vendor_path) . ' && ' . $vendor_path . '/bin/rector process ' . DRUPAL_ROOT . '/' . $extension->getPath() . ' --dry-run --config=' . $config_path . ' 2>&1';
    exec($cmd, $output);

    $output = join("\n", $output);
    $this->rectorResults->set($extension->getName(), $output);

    return strpos($output, '[OK] Rector is done!');
  }

 /**
   * Formats processed rector results as a render array.
   *
   * @param string $raw_rector_result
   *   Raw rector output string.
   * @param \Drupal\Core\Extension\Extension $extension
   *   Extension that was parsed.
   * @param string $category
   *   One of 'custom' or 'contrib'. Presenting messages may be different for each.
   *
   * @return string
   *   Render array with a textarea of the reformatted output as a diff if
   *   the rector output was a patch. The verbatim output if there were errors
   *   or a note about no patchability otherwise.
   */
  public function formatResults(string $raw_rector_result, Extension $extension, string $category) {
    $info = $extension->info;
    $label = $info['name'] . (!empty($info['version']) ? ' ' . $info['version'] : '');

    // The result was empty. Nothing to patch.
    if (empty($raw_rector_result)) {
      return [
        '#title' => $this->t('No results for @extension', ['@extension' => $label]),
        'results' => [
          '#type' => 'markup',
          '#markup' => $this->t('Rector not run yet on the project.'),
        ]
      ];
    }

    // We have results, process it for display.
    $processed_result = $this->processResults($raw_rector_result, $extension);

    $export_button = [
      '#type' => 'link',
      '#title' => $processed_result['state'] === 'fail' ? $this->t('Export errors') : $this->t('Export patch'),
      '#name' => 'export',
      '#url' => Url::fromRoute(
        'upgrade_rector.export',
        [
          'type' => $extension->getType(),
          'project_machine_name' => $extension->getName()
        ]
      ),
      '#attributes' => [
        'class' => [
          'button',
        ],
      ],
    ];

    // The result was successful without a patch. Nothing to patch.
    if ($processed_result['state'] === 'success' && empty($processed_result['patch'])) {
      return [
        '#title' => $this->t('Nothing to patch in @extension', ['@extension' => $label]),
        'results' => [
          '#type' => 'markup',
          '#markup' => $this->t('Nothing found to patch. This does not mean the project is entirely Drupal 9 compatible due to the limited number of transformations available. The maintainers of <a href=":url-drupal-rector">Drupal-rector welcome more contributed transformations</a>. Use <a href=":url-upgrade-status">Upgrade Status</a> or <a href=":url-drupal-check">drupal-check</a> to identify deprecated API use that rector may not have coverage for yet.', [':url-upgrade-status' => 'https://drupal.org/project/upgrade_status', ':url-drupal-check' => 'https://github.com/mglaman/drupal-check', ':url-drupal-rector' => 'https://github.com/palantirnet/drupal-rector-sandbox/blob/master/README.md#developing-with-drupal-rector']),
        ]
      ];
    }

    // The result entirely failed. Display the error log.
    elseif($processed_result['state'] === 'fail') {
      $count = count(explode("\n", $processed_result['log']));
      return [
        '#title' => $this->t('Fail while processing @extension', ['@extension' => $label]),
        'description' => [
          '#type' => 'markup',
          '#markup' => $this->t('Raw rector output shown below for debugging purposes. If you believe the errors are due to the tool used not the code processed, <a href=":url">look for an existing issue or submit a new one for Drupal-rector</a>.', [':url' => 'https://www.drupal.org/project/issues/rector']),
        ],
        'results' => [
          '#type' => 'textarea',
          '#rows' => min($count, 16),
          '#value' => $processed_result['log'],
        ],
        'export' => $export_button,
      ];
    }

    // The result contained a patch and rectors executed but it may also contain an error.
    else {
      $patch_line_count = count(explode("\n", $processed_result['patch']));
      $log_line_count = count(explode("\n", $processed_result['log']));
      $description = $this->t('Review the suggested changes as they may need some further updates manually.');
      if ($category == 'contrib') {
        $description .= ' ' . $this->t('Work with the maintainers of <a href=":project-url">@extension</a> following their Drupal 9 plan (if specified on the project page). Make sure to update to the latest (development) version locally. Remember that there may very well be <a href=":url-issues">issues opened</a> for some or all of these incompatibilities found.', [':project-url' => 'https://drupal.org/project/' . $extension->getName(), '@extension' => $label, ':url-issues' => 'https://drupal.org/project/issues/' . $extension->getName()]);
      }
      return [
        '#title' => $this->t('Patch generated for @extension', ['@extension' => $label]),
        'description' => [
          '#type' => 'markup',
          '#markup' => $description,
        ],
        'results' => [
          '#type' => 'textarea',
          '#rows' => min($patch_line_count, 16),
          '#value' => $processed_result['patch'],
        ],
        'export' => $export_button,
        'rectors' => [
          '#theme' => 'item_list',
          '#title' => $this->t('List of applied rectors'),
          '#list_type' => 'ul',
          '#items' => $processed_result['rectors']
        ],
        'log' => [
          '#type' => 'textarea',
          '#title' => $this->t('Log of errors encountered while running rector'),
          '#rows' => min($log_line_count, 16),
          '#value' => $processed_result['log'],
          '#access' => !empty($processed_result['log']),
        ],
      ];
    }
  }

 /**
   * Processes the rector output string for display.
   *
   * @param string $raw_rector_result
   *   Raw rector output string.
   * @param \Drupal\Core\Extension\Extension $extension
   *   Extension that was parsed.
   *
   * @return bool|array
   *   FALSE if the rector run did not succeed. TRUE if succeeded and found
   *   nothing to patch. Otherwise an array with two keys: 'patch' holding
   *   a string with a processed patch and 'rectors' with an array of rector
   *   names that were executed on the files processed.
   */
  public function processResults(string $raw_rector_result, Extension $extension) {
    $lines = explode("\n", $raw_rector_result);

    $processed_result = [
      'state' => strpos($raw_rector_result, '[OK] Rector is done!') ? 'success' : 'mixed',
      'patch' => '',
      'log' => '',
      'rectors' => [],
    ];

    if (!preg_match('!^\\d+ files? with changes$!m', $raw_rector_result) && !strpos($raw_rector_result, '[OK] Rector is done!')) {
      $processed_result['state'] = 'fail';
      $processed_result['log'] = $raw_rector_result;
      return $processed_result;
    }

    // If this was at least a partially successful run, reformat as patch. This rector
    // version does not have an output format option yet.
    $state = 'log';
    $file = '';
    $rectors = [];
    foreach ($lines as $num => &$line) {
      switch ($state) {
        case 'log':
          // Found a file that was patched.
          if (preg_match('!^\d+\) (.+)!', $line, $found)) {
            $file = str_replace(DRUPAL_ROOT . '/' . $extension->getPath() . '/', '', $found[1]);
            unset($lines[$num]);
            $state = 'seeking diff';
          }
          // Found a list of rectors applied.
          elseif (preg_match('!^ \* (.+)$!', $line, $found)) {
            $rectors[$found[1]] = TRUE;
            unset($lines[$num]);
          }
          elseif ($line == '===================' || $line == 'Applied rules:' || preg_match('!^\\d+ files? with changes$!', $line)) {
            unset($lines[$num]);
          }
          else {
            // Keep saving to the log until we find a patch portion.
            if ($processed_result['state'] == 'mixed') {
              // Avoid repeating empty lines, they make reading the log harder.
              if (empty($lines[$num])) {
                $processed_result['log'] = trim($processed_result['log']) . "\n\n";
              }
              else {
                $processed_result['log'] .= $lines[$num] . "\n";
              }
            }
            unset($lines[$num]);
          }
          break;

        // File is known, seeking until diff is found.
        case 'seeking diff':
          if ($line != "    ---------- begin diff ----------") {
            unset($lines[$num]);
          }
          else {
            $line = 'Index: ' . $file;
            $state = 'diff';
          }
          break;

          // Reformatting the diff part.
          case 'diff':
            if ($line == '--- Original') {
              $line = '--- a/' . $file;
            }
            elseif ($line == '+++ New') {
              $line = '+++ b/' . $file;
            }
            if ($line == '    ----------- end diff -----------') {
              $state = 'log';
              unset($lines[$num]);
            }
            break;
      }
    }
    if (count($lines)) {
      $processed_result['patch'] = join("\n", $lines);
      $processed_result['rectors'] = array_keys($rectors);
    }
    return $processed_result;
  }
}

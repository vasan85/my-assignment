<?php

namespace Drupal\upgrade_rector\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\upgrade_rector\ProjectCollector;
use Drupal\upgrade_rector\RectorProcessor;
use Symfony\Component\HttpFoundation\Response;

class RectorResultController extends ControllerBase {

  /**
   * The project collector service.
   *
   * @var \Drupal\upgrade_rector\ProjectCollector
   */
  protected $projectCollector;

    /**
   * Rector result storage.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $rectorResults;

  /**
   * Rector data processor.
   *
   * @var \Drupal\upgrade_rector\RectorProcessor
   */
  protected $rectorProcessor;

  /**
   * Constructs a \Drupal\upgrade_rector\Controller\RectorResultController.
   *
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value_factory
   *   The key/value factory.
   * @param \Drupal\upgrade_rector\RectorProcessor $rector_processor
   *   The rector processor.
   * @param \Drupal\upgrade_rector\ProjectCollector $project_collector
   *   The project collector service.
   */
  public function __construct(
    KeyValueFactoryInterface $key_value_factory,
    RectorProcessor $rector_processor,
    ProjectCollector $project_collector
  ) {
    $this->rectorResults = $key_value_factory->get('upgrade_status_rector_results');
    $this->rectorProcessor = $rector_processor;
    $this->projectCollector = $project_collector;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('keyvalue'),
      $container->get('upgrade_rector.rector_processor'),
      $container->get('upgrade_rector.project_collector')
    );
  }

  /**
   * Builds content for patch review page/popup.
   *
   * @param string $type
   *   Type of the extension, it can be either 'module' or 'theme' or 'profile'.
   * @param string $project_machine_name
   *   The machine name of the project.
   *
   * @return array
   *   Build array.
   */
  public function resultPage(string $type, string $project_machine_name) {
    $extension = $this->projectCollector->loadProject($type, $project_machine_name);
    // Extensions that don't have a project should be considered custom.
    // Extensions that have the 'drupal' project are custom extensions
    // that are running in a Drupal core git checkout, so also categorize
    // them as custom.
    $category = (empty($extension->info['project']) || $extension->info['project'] === 'drupal') ? 'custom' : 'contrib';
    $raw_rector_result = $this->rectorResults->get($project_machine_name);
    return $this->rectorProcessor->formatResults($raw_rector_result, $extension, $category);
  }

  /**
   * Generates single project export.
   *
   * @param string $type
   *   Type of the extension, it can be either 'module' or 'theme' or 'profile'.
   * @param string $project_machine_name
   *   The machine name of the project.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Response object.
   */
  public function resultExport(string $type, string $project_machine_name) {
    $extension = $this->projectCollector->loadProject($type, $project_machine_name);
    $raw_rector_result = $this->rectorResults->get($project_machine_name);
    $processed_result = $this->rectorProcessor->processResults($raw_rector_result, $extension);

    if ($processed_result['state'] === 'fail') {
      $extension = '-errors.txt';
      $content = $raw_rector_result;
    }
    elseif ($processed_result['state'] === 'success' && empty($processed_result['patch'])) {
      $extension = '-results.txt';
      $content = 'Nothing to patch in ' . $project_machine_name;
    }
    else {
      $extension = '-upgrade-rector.patch';
      $content = $processed_result['patch'];
    }
    $filename = $project_machine_name . $extension;
    $response = new Response($content);
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
    return $response;
  }

}
<?php

namespace Drupal\upgrade_rector\Form;

use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ProfileExtensionList;
use Drupal\Core\Extension\ThemeHandler;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\upgrade_rector\ProjectCollector;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\upgrade_rector\RectorProcessor;

class UpgradeRectorForm extends FormBase {

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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('upgrade_rector.project_collector'),
      $container->get('keyvalue'),
      $container->get('upgrade_rector.rector_processor')
    );
  }

  /**
   * Constructs a \Drupal\upgrade_status_rector\UpgradeStatusRectorForm.
   *
   * @param \Drupal\upgrade_rector\ProjectCollector $project_collector
   *   The project collector service.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value_factory
   *   The key/value factory.
   * @param \Drupal\upgrade_rector\RectorProcessor $rector_processor
   *   The rector processor.
   */
  public function __construct(
    ProjectCollector $project_collector,
    KeyValueFactoryInterface $key_value_factory,
    RectorProcessor $rector_processor
  ) {
    $this->projectCollector = $project_collector;
    $this->rectorResults = $key_value_factory->get('upgrade_status_rector_results');
    $this->rectorProcessor = $rector_processor;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'drupal_upgrade_rector_form';
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'upgrade_rector/upgrade_rector.admin';

    // Gather project list grouped by custom and contrib projects.
    $projects = $this->projectCollector->collectProjects();

    // List custom project status first.
    $custom = ['#type' => 'markup', '#markup' => '<br /><strong>' . $this->t('No custom projects found.') . '</strong>'];
    if (count($projects['custom'])) {
      $custom = $this->buildProjectList($projects['custom'], 'custom');
    }
    $form['custom'] = [
      '#type' => 'details',
      '#title' => $this->t('Custom projects'),
      '#description' => $this->t('Custom code is specific to your site, and must be upgraded manually. <a href=":upgrade">Read more about how developers can upgrade their code to Drupal 9</a>.', [':upgrade' => 'https://www.drupal.org/docs/9/how-drupal-9-is-made-and-what-is-included/how-and-why-we-deprecate-on-the-way-to-drupal-9']),
      '#open' => TRUE,
      '#attributes' => ['class' => ['upgrade-rector-summary']],
      'data' => $custom,
      '#tree' => TRUE,
    ];

    // List contrib project status second.
    $contrib = ['#type' => 'markup', '#markup' => '<br /><strong>' . $this->t('No contributed projects found.') . '</strong>'];
    if (count($projects['contrib'])) {
      $contrib = $this->buildProjectList($projects['contrib'], 'contrib');
    }
    $form['contrib'] = [
      '#type' => 'details',
      '#title' => $this->t('Contributed projects'),
      '#description' => $this->t('Contributed code is available from drupal.org. Problems here may be partially resolved by updating to the latest version. <a href=":update">Read more about how to update contributed projects</a>.', [':update' => 'https://www.drupal.org/docs/8/update/update-modules']),
      '#open' => TRUE,
      '#attributes' => ['class' => ['upgrade-rector-summary']],
      'data' => $contrib,
      '#tree' => TRUE,
    ];
    return $form;
  }

  /**
   * Builds a list and status summary of projects.
   *
   * @param \Drupal\Core\Extension\Extension[] $projects
   *   Array of extensions representing projects.
   * @param string $category
   *   One of 'custom' or 'contrib'. Presenting messages may be different for each.
   *
   * @return array
   *   Build array.
   */
  private function buildProjectList(array $projects, string $category) {
    $list = $form = [];
    foreach ($projects as $name => $extension) {
      $info = $extension->info;
      $label = $info['name'] . (!empty($info['version']) ? ' ' . $info['version'] : '');
      $list[$name] = $label;
      if ($results = $this->rectorResults->get($extension->getName())) {
        $form[$name] = $this->rectorProcessor->formatResults($results, $extension, $category) + ['#type' => 'details', '#closed' => TRUE];
      }
    }
    $form['project'] = [
      '#type' => 'select',
      '#title' => $this->t('Select project'),
      '#options' => $list,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#name' => 'rector_' . $category,
      '#value' => $this->t('Run rector'),
      '#button_type' => 'primary',
      '#submit' => [[$this, 'submit' . ucfirst($category) . 'Rector']],
    ];
    return $form;
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitContribRector(array &$form, FormStateInterface $form_state) {
    $projects = $this->projectCollector->collectProjects();
    $this->submitRector(
      $projects['contrib'][$form_state->getValue(['contrib', 'data', 'project'])]
    );
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitCustomRector(array &$form, FormStateInterface $form_state) {
    $projects = $this->projectCollector->collectProjects();
    $this->submitRector(
      $projects['custom'][$form_state->getValue(['custom', 'data', 'project'])]
    );
  }

  /**
   * Form submission handler.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   Selected extension.
   */
  public function submitRector(Extension $extension) {
    $info = $extension->info;
    $label = $info['name'] . (!empty($info['version']) ? ' ' . $info['version'] : '');
    if ( \Drupal::service('upgrade_rector.rector_processor')->runRector($extension)) {
      $this->messenger()->addMessage($this->t('Parsing @project was successful.', ['@project' => $label]));
    }
    else {
      $this->messenger()->addError($this->t('Error while parsing @project.', ['@project' => $label]));
    }
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

}

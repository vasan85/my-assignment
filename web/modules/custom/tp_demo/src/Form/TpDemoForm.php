<?php

namespace Drupal\tp_demo\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\tp_demo\TypedData\Definition\ResponseDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\TypedData\TypedDataManager;

/**
 * Class TpDemoForm.
 */
class TpDemoForm extends FormBase {

  /**
   * Drupal\Core\TypedData\TypedDataManager definition.
   *
   * @var \Drupal\Core\TypedData\TypedDataManager
   */
  protected $typedDataManager;

  /**
   * Constructs a new TpDemoForm object.
   */
  public function __construct(TypedDataManager $typed_data_manager) {
    $this->typedDataManager = $typed_data_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('typed_data_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tp_demo_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['json_data'] = [
      '#type' => 'textarea',
      '#title' => $this->t('JSON data'),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Create Response Data Type instance.
    $definition = ResponseDefinition::create('demo_response');
    $response = $this->typedDataManager->create($definition);
    // Convert json to array.
    $raw_response = json_decode($form_state->getValue('json_data'), TRUE);
    $response->setValue($raw_response);
    // Validate inserted data.
    $violations = $response->validate();
    if ($violations->count() != 0) {
      $form_state->setErrorByName('json_data', $this->t('Json data is invalid'));
      // If we have validation errors - print message with error.
      foreach ($violations as $violation) {
        // Print validation errors.
        drupal_set_message($this->t('@message (@property = @value)', [
          '@message' => $violation->getMessage(),
          '@property' => $violation->getPropertyPath(),
          '@value' => $violation->getInvalidValue(),
        ]), 'error');
      }
    }
    else {
      // Move response object to form_state storage.
      $form_state->setStorage(['response' => $response]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $storage = $form_state->getStorage();
    if (!isset($storage['response'])) {
      drupal_set_message($this->t('Response not found!'), 'error');
      return;
    }

    $response = $storage['response'];
    // For generate-article command create new nodes based on response data.
    if ($response->get('command')->getValue() == 'generate-article') {
      foreach ($response->get('results') as $result) {
        $node = Node::create([
          'type' => 'article',
          'title' => $result->get('title')->getValue(),
          'body' => [
            'value' => $result->get('body')->getValue(),
            'format' => 'full_html',
          ],
          'author' => $result->get('author')->getValue(),
        ]);
        $node->save();

        \Drupal::messenger()->addMessage($this->t('Article @title has been created (NID=@id).', [
          '@id' => $node->id(),
          '@title' => $node->getTitle(),
        ]));
      }
    }

  }

}
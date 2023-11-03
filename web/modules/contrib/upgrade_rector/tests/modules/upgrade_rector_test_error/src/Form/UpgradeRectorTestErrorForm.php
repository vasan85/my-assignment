<?php

namespace Drupal\upgrade_rector_test_error\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class UpgradeRectorTestErrorForm extends FormBase {

  public function getFormId() {
    return 'drupal_upgrade_rector_test_error_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $foo = LOCALE_PLURAL_DELIMITER;
    drupal_set_message('Sample message');
    $url = Drupal::url('<front');
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {}

}

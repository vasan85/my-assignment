<?php

namespace Drupal\tp_demo\TypedData\Definition;

use Drupal\Core\TypedData\ComplexDataDefinitionBase;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\ListDataDefinition;

/**
 * Demo Api Response Definition.
 */
class ResponseDefinition extends ComplexDataDefinitionBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions() {
    if (!isset($this->propertyDefinitions)) {
      $info = &$this->propertyDefinitions;

      $info['status'] = DataDefinition::create('string')
        ->setRequired(TRUE)
        ->setLabel('status')
        ->addConstraint('AllowedValues', ['OK']);
      $info['command'] = DataDefinition::create('string')
        ->setRequired(TRUE)
        ->setLabel('command');
      $info['results'] = ListDataDefinition::create('demo_results')
        ->setLabel('results')
        ->addConstraint('NotNull');
    }
    return $this->propertyDefinitions;
  }

}
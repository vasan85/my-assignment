<?php

namespace Drupal\tp_demo\TypedData\Definition;

use Drupal\Core\TypedData\ComplexDataDefinitionBase;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Demo Api Response results definition.
 */
class ResultsDefinition extends ComplexDataDefinitionBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions() {
    if (!isset($this->propertyDefinitions)) {
      $info = &$this->propertyDefinitions;

      $info['title'] = DataDefinition::create('string')
        ->setLabel('title')
        ->addConstraint('NotNull');
      $info['body'] = DataDefinition::create('string')
        ->setLabel('body');
      $info['author'] = DataDefinition::create('integer')
        ->setRequired(TRUE)
        ->setLabel('author');
    }
    return $this->propertyDefinitions;
  }

}

<?php

namespace Drupal\tp_demo\Plugin\DataType;

use Drupal\Core\TypedData\Plugin\DataType\Map;

/**
 * Slide processor response `Convert png` data type.
 *
 * @DataType(
 * id = "demo_results",
 * label = @Translation("Demo results"),
 * definition_class = "\Drupal\tp_demo\TypedData\Definition\ResultsDefinition"
 * )
 */
class ResultsData extends Map {

}
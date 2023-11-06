<?php

namespace Drupal\tp_demo\Plugin\DataType;

use Drupal\Core\TypedData\Plugin\DataType\Map;

/**
 * Slide processor response `Convert png` data type.
 *
 * @DataType(
 * id = "demo_response",
 * label = @Translation("Demo response"),
 * definition_class = "\Drupal\tp_demo\TypedData\Definition\ResponseDefinition"
 * )
 */
class ResponseData extends Map {

}
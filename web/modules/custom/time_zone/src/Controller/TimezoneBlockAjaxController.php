<?php

namespace Drupal\time_zone\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\time_zone\TimezoneDateTimeService;

/**
 * Controller for Timezone Block Ajax callback.
 */
class TimezoneBlockAjaxController extends ControllerBase {

  /**
   * The timezone date time service.
   *
   * @var \Drupal\time_zone\TimezoneDateTimeService
   */
  protected $timezoneDateTimeService;

  /**
   * Constructs a TimezoneBlockAjaxController object.
   *
   * @param \Drupal\time_zone\TimezoneDateTimeService $timezoneDateTimeService
   *   The timezone date time service.
   */
  public function __construct(TimezoneDateTimeService $timezoneDateTimeService) {
    $this->timezoneDateTimeService = $timezoneDateTimeService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('time_zone.datetime_service')
    );
  }

  /**
   * Callback to get the updated time.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the updated time.
   */
  public function ajaxCallback() {
    // Fetch the current time using the service.
    $dateTime = $this->timezoneDateTimeService->getCurrentTime();

    // Return the current time as JSON response.
    return new JsonResponse([
      'hour' => $dateTime['hour'],
      'minute' => $dateTime['minute'],
    ]);
  }

}

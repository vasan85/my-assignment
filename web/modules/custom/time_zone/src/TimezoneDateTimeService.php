<?php

namespace Drupal\time_zone;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Component\Datetime\Time;

/**
 * Provide current timestamp based on the timezone.
 */
class TimezoneDateTimeService {

  /**
   * The configuration factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The date formatter service for formatting timestamps.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The time service for obtaining the current timestamp.
   *
   * @var \Drupal\Component\Datetime\Time
   */
  protected $time;

  /**
   * Constructs a TimezoneDateTimeService object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter service for formatting timestamps.
   * @param \Drupal\Component\Datetime\Time $time
   *   The time service for obtaining the current timestamp.
   */
  public function __construct(ConfigFactoryInterface $configFactory, DateFormatterInterface $dateFormatter, Time $time) {
    $this->configFactory = $configFactory;
    $this->dateFormatter = $dateFormatter;
    $this->time = $time;
  }

  /**
   * Gets the current time in the selected timezone.
   *
   * @return string
   *   The formatted current time in the format 'jS F Y - g:i A'.
   */
  public function getCurrentTime() {
    $config = $this->configFactory->get('time_zone.settings');
    $selectedTimezone = $config->get('timezone');
    $currentTimestamp = $this->time->getCurrentTime();

    // Format the date and time separately.
    $formattedDate = $this->dateFormatter->format($currentTimestamp, 'custom', 'l, jS F Y', $selectedTimezone);
    $formattedTime = $this->dateFormatter->format($currentTimestamp, 'custom', 'g:i A', $selectedTimezone);
    $formattedHour = $this->dateFormatter->format($currentTimestamp, 'custom', 'g', $selectedTimezone);
    $formattedMinute = $this->dateFormatter->format($currentTimestamp, 'custom', 'i', $selectedTimezone);

    // Create an array to return both date and time.
    $formattedDateTime = [
      'date' => $formattedDate,
      'time' => $formattedTime,
      'hour' => $formattedHour,
      'minute' => $formattedMinute,
    ];

    return $formattedDateTime;
  }

}

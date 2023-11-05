<?php

namespace Drupal\time_zone\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\time_zone\TimezoneDateTimeService;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Provides a custom block displaying location and current time.
 *
 * @Block(
 *   id = "time_zone_block",
 *   admin_label = @Translation("Timezone Block"),
 *
 * )
 */
class TimezoneBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The timezone date time service.
   *
   * @var \Drupal\time_zone\TimezoneDateTimeService
   */
  protected $timezoneDateTimeService;

  /**
   * The configuration factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a TimezoneBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\time_zone\TimezoneDateTimeService $timezoneDateTimeService
   *   The timezone date time service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, TimezoneDateTimeService $timezoneDateTimeService, ConfigFactoryInterface $configFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->timezoneDateTimeService = $timezoneDateTimeService;
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('time_zone.datetime_service'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->configFactory->get('time_zone.settings');
    $country = $config->get('country');
    $city = $config->get('city');
    $dateTime = $this->timezoneDateTimeService->getCurrentTime();

    $build = [
      '#theme' => 'time-zone-block',
      '#country' => $country,
      '#city' => $city,
      '#date' => $dateTime['date'],
      '#title' => FALSE,
      '#time' => $dateTime['time'],
      '#cache' => [
        'tags' => ['config:time_zone.settings'],
      ],
    ];

    return $build;
  }

}

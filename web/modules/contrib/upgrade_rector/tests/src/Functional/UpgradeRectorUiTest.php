<?php

namespace Drupal\Tests\upgrade_rector\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the UI before and after running scans.
 *
 * @group upgrade_rector
 */
class UpgradeRectorUiTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'upgrade_rector',
    'upgrade_rector_test_error',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->drupalCreateUser(['administer software updates']));
  }

  /**
   * Test the user interface before running rector.
   */
  public function testUiBeforeScan() {
    $this->drupalGet(Url::fromRoute('upgrade_rector.run'));
    $assert_session = $this->assertSession();
    $assert_session->buttonExists('Run rector');
    $this->assertCount(2, $this->getSession()->getPage()->findAll('css', 'details'));
  }

  /**
   * Test the user interface after running rector.
   */
  public function testUiAfterScan() {
    $edit = [
      'custom[data][project]' => 'upgrade_rector_test_error',
    ];
    $this->drupalGet('admin/reports/upgrade-rector');
    $this->submitForm($edit, 'Run rector');

    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $assert_session->buttonExists('Run rector');

    // One module result exists.
    $this->assertCount(3, $this->getSession()->getPage()->findAll('css', 'details'));
    $this->assertCount(1, $this->getSession()->getPage()->findAll('css', 'details#edit-custom-data-upgrade-rector-test-error'));

    // Find patch portion for drupal_set_message().
    //$this->assertSession()->responseContains("-  drupal_set_message('Sample message');");
    //$this->assertSession()->responseContains("+  \Drupal::messenger()->addStatus('Sample message');");

    // At least two files should be processed.
    $this->assertSession()->responseContains("upgrade_rector_test_error.module");
    $this->assertSession()->responseContains("UpgradeRectorTestErrorForm.php");

    // These rectors should have been executed.
    $this->assertSession()->responseContains("DrupalSetMessageRector");
    $this->assertSession()->responseContains("DrupalURLRector");

    // A total of two files should have a patch with an [OK] result
    $this->assertSession()->responseContains("[OK] 2 files would have changed");
  }

}

<?php

declare(strict_types=1);

namespace Drupal\Tests\field_copyright\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\field_copyright\Plugin\Field\FieldFormatter\CopyrightLinkFormatter;

/**
 * Unit tests for CopyrightLinkFormatter default settings.
 *
 * @group field_copyright
 */
class CopyrightLinkFormatterDefaultsTest extends UnitTestCase {

  /**
   * defaultSettings() includes 'target' defaulting to '_blank'.
   */
  public function testDefaultSettingsTarget(): void {
    $defaults = CopyrightLinkFormatter::defaultSettings();
    $this->assertArrayHasKey('target', $defaults);
    $this->assertSame('_blank', $defaults['target']);
  }

}

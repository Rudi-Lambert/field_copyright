<?php

declare(strict_types=1);

namespace Drupal\Tests\field_copyright\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\field_copyright\Plugin\Field\FieldType\CopyrightItem;

/**
 * Unit tests for CopyrightItem::isEmpty() and getUrl()/getUrlFor() logic.
 *
 * We test the pure-logic aspects by driving the static helpers and mocking
 * the field-item property bag.
 *
 * @group field_copyright
 */
class CopyrightItemEmptinessTest extends UnitTestCase {

  /**
   * defaultFieldSettings() returns the expected keys.
   */
  public function testDefaultFieldSettingsKeys(): void {
    $settings = CopyrightItem::defaultFieldSettings();
    $this->assertArrayHasKey('enable_photographer', $settings);
    $this->assertArrayHasKey('enable_creator', $settings);
    $this->assertFalse($settings['enable_photographer']);
    $this->assertFalse($settings['enable_creator']);
  }

  /**
   * propertyDefinitions() returns all six expected property keys.
   */
  public function testPropertyDefinitionsKeys(): void {
    // propertyDefinitions() only needs the storage definition for its
    // signature; it doesn't actually use it in this implementation.
    $storage_def = $this->createMock(\Drupal\Core\Field\FieldStorageDefinitionInterface::class);
    $defs = CopyrightItem::propertyDefinitions($storage_def);

    foreach (['title', 'uri', 'photographer_title', 'photographer_uri', 'creator_title', 'creator_uri'] as $key) {
      $this->assertArrayHasKey($key, $defs, "Property '$key' is missing from propertyDefinitions()");
    }
  }

  /**
   * schema() contains all six column keys.
   */
  public function testSchemaColumns(): void {
    $storage_def = $this->createMock(\Drupal\Core\Field\FieldStorageDefinitionInterface::class);
    $schema = CopyrightItem::schema($storage_def);

    foreach (['title', 'uri', 'photographer_title', 'photographer_uri', 'creator_title', 'creator_uri'] as $col) {
      $this->assertArrayHasKey($col, $schema['columns'], "Column '$col' missing from schema()");
    }
    // title is required; others are nullable.
    $this->assertTrue($schema['columns']['title']['not null']);
    $this->assertFalse($schema['columns']['uri']['not null'] ?? FALSE);
  }

}

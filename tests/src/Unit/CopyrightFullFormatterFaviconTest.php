<?php

declare(strict_types=1);

namespace Drupal\Tests\field_copyright\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\field_copyright\Plugin\Field\FieldFormatter\CopyrightFullFormatter;

/**
 * Unit tests for CopyrightFullFormatter static/pure helpers.
 *
 * buildFaviconUrl() and normaliseHost() are extractable without a DB or
 * container.  We test them by invoking via a concrete instance created from the
 * static method.
 *
 * @group field_copyright
 */
class CopyrightFullFormatterFaviconTest extends UnitTestCase {

  /**
   * buildFaviconUrl() returns NULL when provider is 'none'.
   */
  public function testBuildFaviconUrlNoneProvider(): void {
    $result = CopyrightFullFormatter::buildFaviconUrl('https://example.com', 'none', 16);
    $this->assertNull($result);
  }

  /**
   * buildFaviconUrl() returns NULL for empty URL.
   */
  public function testBuildFaviconUrlEmptyUrl(): void {
    $result = CopyrightFullFormatter::buildFaviconUrl('', 'google', 16);
    $this->assertNull($result);
  }

  /**
   * buildFaviconUrl() returns NULL for null URL.
   */
  public function testBuildFaviconUrlNullUrl(): void {
    $result = CopyrightFullFormatter::buildFaviconUrl(NULL, 'google', 16);
    $this->assertNull($result);
  }

  /**
   * buildFaviconUrl() builds a valid Google favicon URL.
   */
  public function testBuildFaviconUrlGoogle(): void {
    $result = CopyrightFullFormatter::buildFaviconUrl('https://commons.wikimedia.org/wiki/File:Test.jpg', 'google', 32);
    $this->assertNotNull($result);
    $this->assertStringContainsString('google.com/s2/favicons', $result);
    $this->assertStringContainsString('commons.wikimedia.org', $result);
    $this->assertStringContainsString('sz=32', $result);
  }

  /**
   * buildFaviconUrl() builds a valid DuckDuckGo favicon URL.
   */
  public function testBuildFaviconUrlDuckDuckGo(): void {
    $result = CopyrightFullFormatter::buildFaviconUrl('https://creativecommons.org/licenses/by/4.0/', 'duckduckgo', 16);
    $this->assertNotNull($result);
    $this->assertStringContainsString('icons.duckduckgo.com/ip3/', $result);
    $this->assertStringContainsString('creativecommons.org', $result);
  }

  /**
   * buildFaviconUrl() returns NULL for a URL without a recognisable host.
   */
  public function testBuildFaviconUrlNoHost(): void {
    $result = CopyrightFullFormatter::buildFaviconUrl('not-a-url', 'google', 16);
    $this->assertNull($result);
  }

  /**
   * defaultSettings() returns all expected keys with their default values.
   */
  public function testDefaultSettings(): void {
    $defaults = CopyrightFullFormatter::defaultSettings();
    $expected_keys = [
      'link_target',
      'separator',
      'label_format',
      'favicon_provider',
      'favicon_size',
      'favicon_external_only',
      'favicon_ignored_hosts',
    ];
    foreach ($expected_keys as $key) {
      $this->assertArrayHasKey($key, $defaults, "Default setting '$key' is missing");
    }
    $this->assertSame('none', $defaults['favicon_provider']);
    $this->assertSame('symbol', $defaults['label_format']);
    $this->assertSame(16, $defaults['favicon_size']);
    $this->assertTrue($defaults['favicon_external_only']);
  }

}

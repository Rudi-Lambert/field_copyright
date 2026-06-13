<?php

namespace Drupal\field_copyright\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Renders the copyright text as plain text. URL is ignored.
 *
 * @FieldFormatter(
 *   id = "field_copyright_text",
 *   label = @Translation("Text only"),
 *   field_types = {
 *     "field_copyright"
 *   }
 * )
 */
class CopyrightTextFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    $elements['#attached']['library'][] = 'field_copyright/field_copyright';

    foreach ($items as $delta => $item) {
      $elements[$delta] = [
        '#plain_text' => $item->title,
      ];
    }

    return $elements;
  }

}

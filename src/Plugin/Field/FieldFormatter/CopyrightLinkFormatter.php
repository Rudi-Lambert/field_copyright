<?php

namespace Drupal\field_copyright\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Renders the copyright text as a link when a URL is available,
 * and as plain text when it is not.
 *
 * @FieldFormatter(
 *   id = "field_copyright_link",
 *   label = @Translation("Link (if URL available)"),
 *   field_types = {
 *     "field_copyright"
 *   }
 * )
 */
class CopyrightLinkFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'target' => '_blank',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);

    $elements['target'] = [
      '#type' => 'select',
      '#title' => $this->t('Link target'),
      '#default_value' => $this->getSetting('target'),
      '#options' => [
        '_blank' => $this->t('New window / tab (_blank)'),
        '_self'  => $this->t('Same window (_self)'),
        ''       => $this->t('None (omit attribute)'),
      ],
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $target = $this->getSetting('target');
    $summary[] = $target
      ? $this->t('Opens in: @target', ['@target' => $target])
      : $this->t('No target attribute');
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    $target   = $this->getSetting('target');

    $elements['#attached']['library'][] = 'field_copyright/field_copyright';

    foreach ($items as $delta => $item) {
      $url = $item->getUrl();

      if ($url !== NULL) {
        $attributes = ['rel' => 'noopener noreferrer'];
        if ($target !== '') {
          $attributes['target'] = $target;
        }

        $elements[$delta] = [
          '#type'  => 'link',
          '#title' => $item->title,
          '#url'   => $url,
          '#options' => ['attributes' => $attributes],
        ];
      }
      else {
        // No URL — fall back to plain text so the formatter is always safe.
        $elements[$delta] = [
          '#plain_text' => $item->title,
        ];
      }
    }

    return $elements;
  }

}

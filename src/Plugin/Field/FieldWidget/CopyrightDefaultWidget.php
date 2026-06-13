<?php

namespace Drupal\field_copyright\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'field_copyright_default' widget.
 *
 * Renders the copyright holder pair (always) plus an optional
 * photographer pair and creator pair when the corresponding field
 * settings are enabled. Each optional pair is hidden behind a checkbox
 * that reveals the inputs via the core States API — no custom JS.
 *
 * @FieldWidget(
 *   id = "field_copyright_default",
 *   label = @Translation("Copyright"),
 *   field_types = {
 *     "field_copyright"
 *   }
 * )
 */
class CopyrightDefaultWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $item     = $items[$delta];
    $settings = [
      'enable_photographer' => (bool) $this->getFieldSetting('enable_photographer'),
      'enable_creator'      => (bool) $this->getFieldSetting('enable_creator'),
    ];

    // Per-delta, per-field selectors for States API reveal. Uses the
    // field name + delta so multiple deltas don't tie to one checkbox.
    $field_name = $this->fieldDefinition->getName();
    $sel_show_photo   = ':input[name="' . $field_name . '[' . $delta . '][show_photographer]"]';
    $sel_show_creator = ':input[name="' . $field_name . '[' . $delta . '][show_creator]"]';

    // --- Holder pair (always shown — 1.0-compatible). ---
    $element['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Copyright text'),
      '#default_value' => $item->title ?? '',
      '#required' => $element['#required'],
      '#maxlength' => 255,
      '#placeholder' => $this->t('e.g. © 2024 The Some Museum'),
      '#description' => $this->t('The copyright holder credit to display.'),
    ];

    $element['uri'] = [
      '#type' => 'url',
      '#title' => $this->t('URL'),
      '#default_value' => $item->uri ?? '',
      '#required' => FALSE,
      '#maxlength' => 2048,
      '#placeholder' => 'https://example.com',
      '#description' => $this->t('Optional. When provided, the copyright text will be rendered as a link.'),
    ];

    // --- Photographer pair (per-instance opt-in, per-value reveal). ---
    if ($settings['enable_photographer']) {
      $element['show_photographer'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Add photographer / source credit'),
        '#default_value' => !empty($item->photographer_title),
      ];

      $element['photographer'] = [
        '#type' => 'container',
        '#states' => [
          'visible' => [
            $sel_show_photo => ['checked' => TRUE],
          ],
        ],
        'photographer_title' => [
          '#type' => 'textfield',
          '#title' => $this->t('Photographer / source'),
          '#default_value' => $item->photographer_title ?? '',
          '#maxlength' => 255,
          '#placeholder' => $this->t('e.g. Photo by Jane Doe'),
        ],
        'photographer_uri' => [
          '#type' => 'url',
          '#title' => $this->t('Photographer URL'),
          '#default_value' => $item->photographer_uri ?? '',
          '#maxlength' => 2048,
          '#placeholder' => 'https://commons.wikimedia.org/...',
        ],
      ];
    }

    // --- Creator pair (per-instance opt-in, per-value reveal). ---
    if ($settings['enable_creator']) {
      $element['show_creator'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Add creator / original artist credit'),
        '#default_value' => !empty($item->creator_title),
      ];

      $element['creator'] = [
        '#type' => 'container',
        '#states' => [
          'visible' => [
            $sel_show_creator => ['checked' => TRUE],
          ],
        ],
        'creator_title' => [
          '#type' => 'textfield',
          '#title' => $this->t('Creator / artist'),
          '#default_value' => $item->creator_title ?? '',
          '#maxlength' => 255,
          '#placeholder' => $this->t('e.g. The Painter'),
        ],
        'creator_uri' => [
          '#type' => 'url',
          '#title' => $this->t('Creator URL'),
          '#default_value' => $item->creator_uri ?? '',
          '#maxlength' => 2048,
          '#placeholder' => 'https://en.wikipedia.org/wiki/...',
        ],
      ];
    }

    // Wrap multi-value deltas in a fieldset for visual grouping.
    if ($this->fieldDefinition->getFieldStorageDefinition()->getCardinality() !== 1) {
      $element += ['#type' => 'fieldset'];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   *
   * Flattens nested form values back to the field item's flat property
   * shape, and clears optional pairs when their show_* checkbox is off.
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    foreach ($values as &$value) {
      // Holder pair.
      $value['title'] = isset($value['title']) ? trim($value['title']) : '';
      $value['uri']   = isset($value['uri'])   ? trim($value['uri'])   : '';
      if ($value['uri'] === '') {
        $value['uri'] = NULL;
      }

      // Photographer pair.
      $show_photo = !empty($value['show_photographer']);
      $photo = $value['photographer'] ?? [];
      $value['photographer_title'] = $show_photo && !empty($photo['photographer_title'])
        ? trim($photo['photographer_title'])
        : NULL;
      $value['photographer_uri'] = $show_photo && !empty($photo['photographer_uri'])
        ? trim($photo['photographer_uri'])
        : NULL;

      // Creator pair.
      $show_creator = !empty($value['show_creator']);
      $creator = $value['creator'] ?? [];
      $value['creator_title'] = $show_creator && !empty($creator['creator_title'])
        ? trim($creator['creator_title'])
        : NULL;
      $value['creator_uri'] = $show_creator && !empty($creator['creator_uri'])
        ? trim($creator['creator_uri'])
        : NULL;

      unset($value['show_photographer'], $value['show_creator'], $value['photographer'], $value['creator']);
    }
    return $values;
  }

}

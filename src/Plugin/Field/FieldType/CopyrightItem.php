<?php

namespace Drupal\field_copyright\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\Url;

/**
 * Plugin implementation of the 'field_copyright' field type.
 *
 * Stores up to three attribution pairs per delta:
 *  - Copyright holder (text required, URL optional) — always present.
 *  - Photographer / source (text + URL, both optional) — opt-in per
 *    field instance via the `enable_photographer` setting.
 *  - Creator / original artist (text + URL, both optional) — opt-in
 *    per field instance via the `enable_creator` setting.
 *
 * The optional pairs are NULL-by-default. A field instance with both
 * toggles off reproduces the 1.0 storage shape and behaviour.
 *
 * @FieldType(
 *   id = "field_copyright",
 *   label = @Translation("Copyright"),
 *   description = @Translation("Stores a copyright / attribution line: required holder credit with optional source URL, plus optional photographer and creator credits."),
 *   default_widget = "field_copyright_default",
 *   default_formatter = "field_copyright_text",
 * )
 */
class CopyrightItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings(): array {
    return [
      'enable_photographer' => FALSE,
      'enable_creator'      => FALSE,
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state): array {
    $element = parent::fieldSettingsForm($form, $form_state);
    $settings = $this->getSettings();

    $element['enable_photographer'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable photographer / source credit'),
      '#default_value' => $settings['enable_photographer'],
      '#description' => $this->t('When enabled, editors can add a second attribution pair (photographer or source) alongside the copyright holder.'),
    ];

    $element['enable_creator'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable original creator / artist credit'),
      '#default_value' => $settings['enable_creator'],
      '#description' => $this->t('When enabled, editors can add a third attribution pair (the original artist / maker of the depicted work). Use this when crediting a photograph of an artwork.'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties['title'] = DataDefinition::create('string')
      ->setLabel(t('Copyright text'))
      ->setRequired(TRUE);

    $properties['uri'] = DataDefinition::create('uri')
      ->setLabel(t('URL'))
      ->setRequired(FALSE);

    $properties['photographer_title'] = DataDefinition::create('string')
      ->setLabel(t('Photographer / source text'))
      ->setRequired(FALSE);

    $properties['photographer_uri'] = DataDefinition::create('uri')
      ->setLabel(t('Photographer / source URL'))
      ->setRequired(FALSE);

    $properties['creator_title'] = DataDefinition::create('string')
      ->setLabel(t('Creator / artist text'))
      ->setRequired(FALSE);

    $properties['creator_uri'] = DataDefinition::create('uri')
      ->setLabel(t('Creator / artist URL'))
      ->setRequired(FALSE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    return [
      'columns' => [
        'title' => [
          'description' => 'Copyright holder credit text.',
          'type' => 'varchar',
          'length' => 255,
          'not null' => TRUE,
          'default' => '',
        ],
        'uri' => [
          'description' => 'Optional copyright holder source URL.',
          'type' => 'varchar',
          'length' => 2048,
          'not null' => FALSE,
        ],
        'photographer_title' => [
          'description' => 'Optional photographer / source credit text.',
          'type' => 'varchar',
          'length' => 255,
          'not null' => FALSE,
        ],
        'photographer_uri' => [
          'description' => 'Optional photographer / source URL.',
          'type' => 'varchar',
          'length' => 2048,
          'not null' => FALSE,
        ],
        'creator_title' => [
          'description' => 'Optional original creator / artist credit text.',
          'type' => 'varchar',
          'length' => 255,
          'not null' => FALSE,
        ],
        'creator_uri' => [
          'description' => 'Optional original creator / artist URL.',
          'type' => 'varchar',
          'length' => 2048,
          'not null' => FALSE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   *
   * Empty when there is no copyright holder text. Photographer / creator
   * data without a holder credit is not a valid value.
   */
  public function isEmpty(): bool {
    $title = $this->get('title')->getValue();
    return $title === NULL || $title === '';
  }

  /**
   * Returns a Url object for a stored URI property, or NULL if unset / invalid.
   */
  public function getUrlFor(string $property): ?Url {
    $value = $this->get($property)->getValue();
    if (empty($value)) {
      return NULL;
    }
    try {
      return Url::fromUri($value);
    }
    catch (\InvalidArgumentException $e) {
      return NULL;
    }
  }

  /**
   * Convenience accessor for the copyright holder URL (1.0-compatible API).
   */
  public function getUrl(): ?Url {
    return $this->getUrlFor('uri');
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition): array {
    $year   = mt_rand(2000, (int) date('Y'));
    $holders = ['The Some Museum', 'Acme Photography', 'OpenMedia', 'Wikimedia Commons'];
    $people  = ['Jane Doe', 'John Smith', 'Alex Park', 'Sam Lee'];

    $values = [
      'title' => '© ' . $year . ' ' . $holders[array_rand($holders)],
    ];

    if (mt_rand(0, 1)) {
      $values['uri'] = 'https://example.com/' . strtolower(str_replace(' ', '-', $values['title']));
    }

    $settings = $field_definition->getSettings();
    if (!empty($settings['enable_photographer']) && mt_rand(0, 1)) {
      $values['photographer_title'] = 'Photo by ' . $people[array_rand($people)];
      if (mt_rand(0, 1)) {
        $values['photographer_uri'] = 'https://commons.wikimedia.org/wiki/' . urlencode($values['photographer_title']);
      }
    }
    if (!empty($settings['enable_creator']) && mt_rand(0, 1)) {
      $values['creator_title'] = $people[array_rand($people)] . ' (original work)';
    }

    return $values;
  }

}

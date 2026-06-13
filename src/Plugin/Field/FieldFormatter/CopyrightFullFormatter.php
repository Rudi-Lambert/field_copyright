<?php

namespace Drupal\field_copyright\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Renders all populated attribution pairs (holder + photographer + creator)
 * with optional links and optional favicons.
 *
 * @FieldFormatter(
 *   id = "field_copyright_full",
 *   label = @Translation("Full (holder + photographer + creator, with favicon)"),
 *   field_types = {
 *     "field_copyright"
 *   }
 * )
 */
class CopyrightFullFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'link_target'           => '_blank',
      'separator'             => ' · ',
      'label_format'          => 'symbol',
      'favicon_provider'      => 'none',
      'favicon_size'          => 16,
      'favicon_external_only' => TRUE,
      'favicon_ignored_hosts' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);

    $elements['link_target'] = [
      '#type' => 'select',
      '#title' => $this->t('Link target'),
      '#default_value' => $this->getSetting('link_target'),
      '#options' => [
        '_blank' => $this->t('New window / tab (_blank)'),
        '_self'  => $this->t('Same window (_self)'),
        ''       => $this->t('None (omit attribute)'),
      ],
    ];

    $elements['separator'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Separator between pairs'),
      '#default_value' => $this->getSetting('separator'),
      '#size' => 6,
      '#description' => $this->t('Inserted between the holder, photographer, and creator credits when more than one is shown.'),
    ];

    $elements['label_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Label format'),
      '#default_value' => $this->getSetting('label_format'),
      '#options' => [
        'symbol' => $this->t('Symbol (©, 📷, 🎨)'),
        'text'   => $this->t('Text (Copyright, Photo, Creator)'),
        'none'   => $this->t('No labels'),
      ],
    ];

    $elements['favicon_provider'] = [
      '#type' => 'select',
      '#title' => $this->t('Favicon provider'),
      '#default_value' => $this->getSetting('favicon_provider'),
      '#options' => [
        'none'       => $this->t('No favicons'),
        'google'     => $this->t('Google (s2/favicons) — supports size, clean 404 fallback'),
        'duckduckgo' => $this->t("DuckDuckGo (icons.duckduckgo.com) — privacy-friendly, returns a placeholder when missing"),
      ],
      '#description' => $this->t('When enabled, a tiny site icon for each source URL is loaded by the visitor\'s browser directly from the provider. <strong>Privacy note:</strong> the provider observes which sites your visitors look up favicons for.'),
    ];

    $elements['favicon_external_only'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Only show favicons on URLs that point to a different site'),
      '#default_value' => $this->getSetting('favicon_external_only'),
      '#description' => $this->t('When on, URLs whose host matches the current site host (after normalising case and stripping <code>www.</code>) skip the favicon. Useful: the favicon is mainly meaningful when the source is external. Same-site copyright links don\'t need to advertise the site to itself.'),
      '#states' => [
        'invisible' => [
          ':input[name$="[settings_edit_form][settings][favicon_provider]"]' => ['value' => 'none'],
        ],
      ],
    ];

    $elements['favicon_ignored_hosts'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Additional hosts treated as local'),
      '#default_value' => $this->getSetting('favicon_ignored_hosts'),
      '#rows' => 4,
      '#description' => $this->t('One host per line. Use this for alternative domains, subdomains, and dev URLs that should be treated like the current site — favicons will be suppressed for any URL whose host matches. Bare hostnames or full URLs both work; case and a leading <code>www.</code> are ignored. Example:<br><code>museoavellonia.se<br>staging.museo-avellonia.se<br>museo.test</code>'),
      '#states' => [
        'invisible' => [
          [':input[name$="[settings_edit_form][settings][favicon_provider]"]' => ['value' => 'none']],
          'or',
          [':input[name$="[settings_edit_form][settings][favicon_external_only]"]' => ['checked' => FALSE]],
        ],
      ],
    ];

    $elements['favicon_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Favicon size (px)'),
      '#default_value' => $this->getSetting('favicon_size'),
      '#min' => 8,
      '#max' => 128,
      '#size' => 4,
      '#description' => $this->t('Used as a request hint to Google. Ignored by DuckDuckGo, which returns a fixed size.'),
      '#states' => [
        'invisible' => [
          ':input[name$="[settings_edit_form][settings][favicon_provider]"]' => ['value' => 'none'],
        ],
      ],
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];

    $label_format = $this->getSetting('label_format');
    $summary[] = $this->t('Labels: @fmt', ['@fmt' => $label_format]);

    $target = $this->getSetting('link_target');
    $summary[] = $target
      ? $this->t('Opens in: @target', ['@target' => $target])
      : $this->t('No link target');

    $provider = $this->getSetting('favicon_provider');
    if ($provider === 'none') {
      $summary[] = $this->t('No favicons');
    }
    else {
      $scope = $this->getSetting('favicon_external_only')
        ? $this->t('external URLs only')
        : $this->t('all URLs');
      $summary[] = $this->t('Favicons: @p @s on @scope', [
        '@p'     => $provider,
        '@s'     => $provider === 'google' ? '(' . $this->getSetting('favicon_size') . 'px)' : '',
        '@scope' => $scope,
      ]);
      $ignored = $this->parseIgnoredHosts();
      if ($ignored && $this->getSetting('favicon_external_only')) {
        $summary[] = $this->t('Treated as local: @hosts', ['@hosts' => implode(', ', $ignored)]);
      }
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    $elements['#attached']['library'][] = 'field_copyright/field_copyright';

    $current_host = '';
    $request = \Drupal::requestStack()->getCurrentRequest();
    if ($request) {
      $current_host = $this->normaliseHost($request->getHost());
    }

    foreach ($items as $delta => $item) {
      $pairs = [];

      if (!empty($item->title)) {
        $pairs[] = $this->buildPair('holder', $item->title, $item->uri, $current_host);
      }
      if (!empty($item->photographer_title)) {
        $pairs[] = $this->buildPair('photographer', $item->photographer_title, $item->photographer_uri, $current_host);
      }
      if (!empty($item->creator_title)) {
        $pairs[] = $this->buildPair('creator', $item->creator_title, $item->creator_uri, $current_host);
      }

      $elements[$delta] = [
        '#field_copyright_full' => TRUE,
        '#pairs'                => $pairs,
        '#separator'            => $this->getSetting('separator'),
      ];
    }

    // Cache varies by host so the external-only check resolves correctly on
    // multi-site / multi-domain setups.
    $elements['#cache']['contexts'][] = 'url.site';

    return $elements;
  }

  /**
   * Build the data structure for one attribution pair.
   */
  protected function buildPair(string $role, string $text, ?string $url, string $current_host): array {
    $label_format = $this->getSetting('label_format');
    $target       = $this->getSetting('link_target');
    $provider     = $this->getSetting('favicon_provider');
    $size         = (int) $this->getSetting('favicon_size');
    $external_only = (bool) $this->getSetting('favicon_external_only');

    $label = '';
    if ($label_format === 'symbol') {
      $label = ['holder' => '©', 'photographer' => '📷', 'creator' => '🎨'][$role] ?? '';
    }
    elseif ($label_format === 'text') {
      $label = ['holder' => 'Copyright:', 'photographer' => 'Photo:', 'creator' => 'Creator:'][$role] ?? '';
    }

    $link_attributes = [];
    if (!empty($url) && $target !== '') {
      $link_attributes = ['target' => $target, 'rel' => 'noopener noreferrer'];
    }
    elseif (!empty($url)) {
      $link_attributes = ['rel' => 'noopener noreferrer'];
    }

    // Favicon resolution: skip if the URL points at the current site (or
    // an additional host the admin marked as local) and external_only is on.
    $favicon = NULL;
    if (!empty($url) && $provider !== 'none') {
      $skip_for_local = FALSE;
      if ($external_only) {
        $url_host = $this->normaliseHost(parse_url($url, PHP_URL_HOST) ?? '');
        if ($url_host !== '') {
          if ($current_host !== '' && $url_host === $current_host) {
            $skip_for_local = TRUE;
          }
          elseif (in_array($url_host, $this->parseIgnoredHosts(), TRUE)) {
            $skip_for_local = TRUE;
          }
        }
      }
      if (!$skip_for_local) {
        $favicon = self::buildFaviconUrl($url, $provider, $size);
      }
    }

    return [
      'role'            => $role,
      'label'           => $label,
      'text'            => $text,
      'url'             => $url ?: '',
      'link_attributes' => $link_attributes,
      'favicon'         => $favicon,
      'favicon_size'    => $size,
    ];
  }

  /**
   * Normalise a hostname for same-site comparison: lowercase, strip
   * leading "www.".
   */
  protected function normaliseHost(string $host): string {
    return preg_replace('/^www\./', '', strtolower($host));
  }

  /**
   * Parse the `favicon_ignored_hosts` textarea setting into a normalised
   * list of hostnames. Accepts bare hosts or full URLs; lines that don't
   * parse to a host are dropped. Result is cached per-render.
   */
  protected function parseIgnoredHosts(): array {
    static $cache = [];
    $raw = (string) $this->getSetting('favicon_ignored_hosts');
    if (isset($cache[$raw])) {
      return $cache[$raw];
    }
    $hosts = [];
    if ($raw !== '') {
      foreach (preg_split('/\r?\n/', $raw) as $line) {
        $line = trim($line);
        if ($line === '') {
          continue;
        }
        $host = str_contains($line, '://')
          ? parse_url($line, PHP_URL_HOST)
          : $line;
        if ($host) {
          $hosts[] = $this->normaliseHost($host);
        }
      }
    }
    return $cache[$raw] = $hosts;
  }

  /**
   * Build the favicon image URL for a given page URL using the chosen provider.
   *
   * Pure URL construction — no HTTP calls from PHP. The browser fetches
   * the favicon directly.
   */
  public static function buildFaviconUrl(?string $pageUrl, string $provider, int $size): ?string {
    if ($provider === 'none' || empty($pageUrl)) {
      return NULL;
    }
    $host = parse_url($pageUrl, PHP_URL_HOST);
    if (!$host) {
      return NULL;
    }
    switch ($provider) {
      case 'google':
        return 'https://www.google.com/s2/favicons?domain=' . rawurlencode($host) . '&sz=' . $size;
      case 'duckduckgo':
        return 'https://icons.duckduckgo.com/ip3/' . rawurlencode($host) . '.ico';
    }
    return NULL;
  }

}

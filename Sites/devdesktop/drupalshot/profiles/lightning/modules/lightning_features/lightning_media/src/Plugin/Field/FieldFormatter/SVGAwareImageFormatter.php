<?php

namespace Drupal\lightning_media\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatter;

/**
 * SVG-aware variant of the core Image formatter.
 *
 * This formatter disables image style processing for SVG images. All other
 * image formats are passed along to the core image formatter.
 *
 * @FieldFormatter(
 *   id = "image_svg",
 *   label = @Translation("Image (SVG-aware)"),
 *   field_types = {"image"}
 * )
 */
class SVGAwareImageFormatter extends ImageFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $build = parent::viewElements($items, $langcode);

    foreach ($build as $delta => $item) {
      /** @var \Drupal\file\FileInterface $file */
      $file = $item['#item']->entity;

      if ($file->getMimeType() == 'image/svg+xml' || preg_match('/.svg$/', $file->getFileUri())) {
        $build[$delta]['#image_style'] = '';
      }
    }
    return $build;
  }

}

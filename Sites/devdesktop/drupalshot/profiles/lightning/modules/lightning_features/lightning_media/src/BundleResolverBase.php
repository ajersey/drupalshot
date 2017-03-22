<?php

namespace Drupal\lightning_media;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\media_entity\MediaBundleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for media bundle resolvers.
 */
abstract class BundleResolverBase extends PluginBase implements BundleResolverInterface, ContainerFactoryPluginInterface {

  /**
   * The media bundle entity storage handler.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $bundleStorage;

  /**
   * The configurable field entity storage handler.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $fieldStorage;

  /**
   * BundleResolverBase constructor.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->bundleStorage = $entity_type_manager->getStorage('media_bundle');
    $this->fieldStorage = $entity_type_manager->getStorage('field_config');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * Returns all possible bundles for the field type(s) this plugin supports.
   *
   * @return MediaBundleInterface[]
   *   Applicable media bundles, keyed by ID.
   */
  protected function getPossibleBundles() {
    $plugin_definition = $this->getPluginDefinition();

    $filter = function (MediaBundleInterface $bundle) use ($plugin_definition) {
      $field = $this->getSourceField($bundle);
      return $field ? in_array($field->getType(), $plugin_definition['field_types']) : FALSE;
    };

    return array_filter($this->bundleStorage->loadMultiple(), $filter);
  }

  /**
   * Returns the source field for a media bundle.
   *
   * @param \Drupal\media_entity\MediaBundleInterface $bundle
   *   The media bundle entity.
   *
   * @return \Drupal\Core\Field\FieldConfigInterface
   *   The configurable source field entity.
   */
  protected function getSourceField(MediaBundleInterface $bundle) {
    $type_config = $bundle->getType()->getConfiguration();
    $id = 'media.' . $bundle->id() . '.' . $type_config['source_field'];
    return $this->fieldStorage->load($id);
  }

}

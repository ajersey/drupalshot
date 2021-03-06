<?php

namespace Drupal\entity_browser\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Bytes;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Url;
use Drupal\entity_browser\FieldWidgetDisplayManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Entity browser file widget.
 *
 * @FieldWidget(
 *   id = "entity_browser_file",
 *   label = @Translation("Entity browser"),
 *   provider = "entity_browser",
 *   multiple_values = TRUE,
 *   field_types = {
 *     "file",
 *     "image"
 *   }
 * )
 */
class FileBrowserWidget extends EntityReferenceBrowserWidget {

  /**
   * Due to the table structure, this widget has a different depth.
   *
   * @var int
   */
  protected static $deleteDepth = 3;

  /**
   * A list of currently edited items. Used to determine alt/title values.
   *
   * @var \Drupal\Core\Field\FieldItemListInterface
   */
  protected $items;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The image factory service.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * The display repository service.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $displayRepository;

  /**
   * Constructs widget plugin.
   *
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   Event dispatcher.
   * @param \Drupal\entity_browser\FieldWidgetDisplayManager $field_display_manager
   *   Field widget display plugin manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Image\ImageFactory $image_factory
   *   The image factory.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository
   *   The entity display repository service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $event_dispatcher, FieldWidgetDisplayManager $field_display_manager, ConfigFactoryInterface $config_factory, ImageFactory $image_factory, EntityDisplayRepositoryInterface $display_repository) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings, $entity_type_manager, $event_dispatcher, $field_display_manager);
    $this->entityTypeManager = $entity_type_manager;
    $this->fieldDisplayManager = $field_display_manager;
    $this->configFactory = $config_factory;
    $this->imageFactory = $image_factory;
    $this->displayRepository = $display_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'),
      $container->get('event_dispatcher'),
      $container->get('plugin.manager.entity_browser.field_widget_display'),
      $container->get('config.factory'),
      $container->get('image.factory'),
      $container->get('entity_display.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $settings = parent::defaultSettings();

    // These settings are hidden.
    unset($settings['field_widget_display']);
    unset($settings['field_widget_display_settings']);

    $settings['view_mode'] = 'default';
    $settings['preview_image_style'] = 'thumbnail';

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $element['field_widget_display']['#access'] = FALSE;
    $element['field_widget_display_settings']['#access'] = FALSE;

    $element['view_mode'] = [
      '#title' => $this->t('File view mode'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('view_mode'),
      '#options' => $this->displayRepository->getViewModeOptions('file'),
    ];

    $element['preview_image_style'] = [
      '#title' => $this->t('Preview image style'),
      '#type' => 'select',
      '#options' => image_style_options(FALSE),
      '#default_value' => $this->getSetting('preview_image_style'),
      '#description' => $this->t('The preview image will be shown while editing the content. Only relevant if using the default file view mode.'),
      '#weight' => 15,
      '#access' => $this->fieldDefinition->getType() == 'image',
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = $this->summaryBase();
    $view_mode = $this->getSetting('view_mode');
    $image_style_setting = $this->getSetting('preview_image_style');

    if ($this->fieldDefinition->getType() == 'image' && $view_mode == 'default') {
      $image_styles = image_style_options(FALSE);
      // Styles could be lost because of enabled/disabled modules that defines
      // their styles in code.
      if (isset($image_styles[$image_style_setting])) {
        $preview_image_style = $this->t('Preview image style: @style', ['@style' => $image_styles[$image_style_setting]]);
      }
      else {
        $preview_image_style = $this->t('No preview image');
      }
    }
    array_unshift($summary, $preview_image_style);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $this->items = $items;
    return parent::formElement($items, $delta, $element, $form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function displayCurrentSelection($details_id, $field_parents, $entities) {
    $field_type = $this->fieldDefinition->getType();
    $field_settings = $this->fieldDefinition->getSettings();
    $field_machine_name = $this->fieldDefinition->getName();
    $file_settings = $this->configFactory->get('file.settings');
    $widget_settings = $this->getSettings();
    $view_mode = $widget_settings['view_mode'];
    $can_edit = (bool) $widget_settings['field_widget_edit'];

    $delta = 0;

    $order_class = $field_machine_name . '-delta-order';

    $current = [
      '#type' => 'table',
      '#header' => [
        $this->t('Preview'),
        $this->t('Filename'),
        $this->t('Metadata'),
        ['data' => $this->t('Operations'), 'colspan' => 2],
        $this->t('Order', [], ['context' => 'Sort order']),
      ],
      '#empty' => $this->t('No files yet'),
      '#attributes' => ['class' => ['entities-list']],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => $order_class,
        ],
      ],
    ];
    /** @var \Drupal\file\FileInterface[] $entities */
    foreach ($entities as $entity) {
      // Check to see if this entity has an edit form. If not, the edit button
      // will only throw an exception.
      if (!$entity->getEntityType()->getFormClass('edit')) {
        $can_edit = FALSE;
      }

      $entity_id = $entity->id();
      $uri = $entity->getFileUri();
      if ($field_type == 'image' && $view_mode == 'default') {
        $image = $this->imageFactory->get($uri);
        if ($image->isValid()) {
          $width = $image->getWidth();
          $height = $image->getHeight();
        }
        else {
          $width = $height = NULL;
        }

        $display = [
          '#weight' => -10,
          '#theme' => 'image_style',
          '#width' => $width,
          '#height' => $height,
          '#style_name' => $widget_settings['preview_image_style'],
          '#uri' => $uri,
        ];
      }
      else {
        $display = $this->entityTypeManager->getViewBuilder('file')->view($entity, $view_mode);
      }

      // Find the default description.
      $description = '';
      $display_field = $field_settings['display_default'];
      $alt = '';
      $title = '';
      $weight = $delta;
      foreach ($this->items as $item) {
        if ($item->target_id == $entity_id) {
          if ($field_type == 'file') {
            $description = $item->description;
            $display_field = $item->display;
          }
          elseif ($field_type == 'image') {
            $alt = $item->alt;
            $title = $item->title;
          }
          $weight = $item->_weight ?: $delta;
        }
      }

      $current[$entity_id] = [
        '#attributes' => [
          'class' => ['draggable'],
          'data-entity-id' => $entity->getEntityTypeId() . ':' . $entity_id,
          'data-row-id' => $delta,
        ],
        'display' => $display,
        'filename' => ['#markup' => $entity->label()],
        'meta' => [
          'display_field' => [
            '#type' => 'checkbox',
            '#title' => $this->t('Include file in display'),
            '#default_value' => (bool) $display_field,
            '#access' => $field_type == 'file' && $field_settings['display_field'],
          ],
          'description' => [
            '#type' => $file_settings->get('description.type'),
            '#title' => $this->t('Description'),
            '#default_value' => $description,
            '#size' => 45,
            '#maxlength' => $file_settings->get('description.length'),
            '#description' => $this->t('The description may be used as the label of the link to the file.'),
            '#access' => $field_type == 'file' && $field_settings['description_field'],
          ],
          'alt' => [
            '#type' => 'textfield',
            '#title' => $this->t('Alternative text'),
            '#default_value' => $alt,
            '#size' => 45,
            '#maxlength' => 512,
            '#description' => $this->t('This text will be used by screen readers, search engines, or when the image cannot be loaded.'),
            '#access' => $field_type == 'image' && $field_settings['alt_field'],
            '#required' => $field_type == 'image' && $field_settings['alt_field_required'],
          ],
          'title' => [
            '#type' => 'textfield',
            '#title' => $this->t('Title'),
            '#default_value' => $title,
            '#size' => 45,
            '#maxlength' => 1024,
            '#description' => $this->t('The title is used as a tool tip when the user hovers the mouse over the image.'),
            '#access' => $field_type == 'image' && $field_settings['title_field'],
            '#required' => $field_type == 'image' && $field_settings['title_field_required'],
          ],
        ],
        'edit_button' => [
          '#type' => 'submit',
          '#value' => $this->t('Edit'),
          '#ajax' => [
            'url' => Url::fromRoute('entity_browser.edit_form', ['entity_type' => $entity->getEntityTypeId(), 'entity' => $entity_id]),
          ],
          '#attributes' => [
            'data-entity-id' => $entity->getEntityTypeId() . ':' . $entity->id(),
            'data-row-id' => $delta,
          ],
          '#access' => $can_edit,
        ],
        'remove_button' => [
          '#type' => 'submit',
          '#value' => $this->t('Remove'),
          '#ajax' => [
            'callback' => [get_class($this), 'updateWidgetCallback'],
            'wrapper' => $details_id,
          ],
          '#submit' => [[get_class($this), 'removeItemSubmit']],
          '#name' => $field_machine_name . '_remove_' . $entity_id,
          '#limit_validation_errors' => [array_merge($field_parents, [$field_machine_name, 'target_id'])],
          '#attributes' => [
            'data-entity-id' => $entity->getEntityTypeId() . ':' . $entity->id(),
            'data-row-id' => $delta,
          ],
          '#access' => (bool) $widget_settings['field_widget_remove'],
        ],
        '_weight' => [
          '#type' => 'weight',
          '#title' => $this->t('Weight for row @number', ['@number' => $delta + 1]),
          '#title_display' => 'invisible',
          // Note: this 'delta' is the FAPI #type 'weight' element's property.
          '#delta' => count($entities),
          '#default_value' => $weight,
          '#attributes' => ['class' => [$order_class]],
        ],
      ];

      $delta++;
    }

    return $current;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $ids = empty($values['target_id']) ? [] : explode(' ', trim($values['target_id']));
    $return = [];
    foreach ($ids as $id) {
      $id = explode(':', $id)[1];
      if (is_array($values['current']) && isset($values['current'][$id])) {
        $item_values = [
          'target_id' => $id,
          '_weight' => $values['current'][$id]['_weight'],
        ];
        if ($this->fieldDefinition->getType() == 'file') {
          if (isset($values['current'][$id]['meta']['description'])) {
            $item_values['description'] = $values['current'][$id]['meta']['description'];
          }
          if ($this->fieldDefinition->getSetting('display_field') && isset($values['current'][$id]['meta']['display_field'])) {
            $item_values['display'] = $values['current'][$id]['meta']['display_field'];
          }
        }
        if ($this->fieldDefinition->getType() == 'image') {
          if (isset($values['current'][$id]['meta']['alt'])) {
            $item_values['alt'] = $values['current'][$id]['meta']['alt'];
          }
          if (isset($values['current'][$id]['meta']['title'])) {
            $item_values['title'] = $values['current'][$id]['meta']['title'];
          }
        }
        $return[] = $item_values;
      }
    }

    // Return ourself as the structure doesn't match the default.
    usort($return, function ($a, $b) {
      return SortArray::sortByKeyInt($a, $b, '_weight');
    });

    return array_values($return);
  }

  /**
   * Retrieves the upload validators for a file field.
   *
   * This is a combination of logic shared between the File and Image widgets.
   *
   * @return array
   *   An array suitable for passing to file_save_upload() or the file field
   *   element's '#upload_validators' property.
   */
  public function getFileValidators() {
    $validators = [];
    $settings = $this->fieldDefinition->getSettings();

    // Cap the upload size according to the PHP limit.
    $max_filesize = Bytes::toInt(file_upload_max_size());
    if (!empty($settings['max_filesize'])) {
      $max_filesize = min($max_filesize, Bytes::toInt($settings['max_filesize']));
    }

    // There is always a file size limit due to the PHP server limit.
    $validators['file_validate_size'] = [$max_filesize];

    // Images have expected defaults for file extensions.
    // See \Drupal\image\Plugin\Field\FieldWidget::formElement() for details.
    if ($this->fieldDefinition->getType() == 'image') {
      // If not using custom extension validation, ensure this is an image.
      $supported_extensions = ['png', 'gif', 'jpg', 'jpeg'];
      $extensions = isset($settings['file_extensions']) ? $settings['file_extensions'] : implode(' ', $supported_extensions);
      $extensions = array_intersect(explode(' ', $extensions), $supported_extensions);
      $validators['file_validate_extensions'] = [implode(' ', $extensions)];
    }
    elseif (!empty($settings['file_extensions'])) {
      $validators['file_validate_extensions'] = [$settings['file_extensions']];
    }

    // Add upload resolution validation.
    if ($settings['max_resolution'] || $settings['min_resolution']) {
      $validators['entity_browser_file_validate_image_resolution'] = [$settings['max_resolution'], $settings['min_resolution']];
    }

    return $validators;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPersistentData() {
    $data = parent::getPersistentData();
    $settings = $this->fieldDefinition->getSettings();
    // Add validators based on our current settings.
    $data['validators']['file'] = ['validators' => $this->getFileValidators()];
    // Provide context for widgets to enhance their configuration. Currently
    // we only know that "upload_location" is used.
    $data['widget_context']['upload_location'] = $settings['uri_scheme'] . '://' . $settings['file_directory'];
    return $data;
  }

}

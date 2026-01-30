<?php

namespace Drupal\media_album_av_common\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\media_album_av_common\Service\AlbumGroupingFieldsService;

/**
 * Plugin implementation of the 'grouping_fields_widget'.
 *
 * @FieldWidget(
 *   id = "grouping_fields_widget",
 *   label = @Translation("Grouping Fields Selector"),
 *   field_types = {
 *     "list_string"
 *   },
 *   multiple_values = TRUE
 * )
 */
class GroupingFieldsWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The grouping fields service.
   *
   * @var \Drupal\media_album_av_common\Service\AlbumGroupingFieldsService
   */
  protected $groupingFieldsService;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, AlbumGroupingFieldsService $grouping_fields_service) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->groupingFieldsService = $grouping_fields_service;
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
      $container->get('media_album_av_common.grouping_fields')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element['#attached']['library'][] = 'core/drupal.tabledrag';

    // Récupérer tous les champs disponibles directement via le service.
    $field_options = $this->getFieldOptions();

    if (empty($field_options)) {
      $element['warning'] = [
        '#type' => 'markup',
        '#markup' => '<div class="messages messages--warning">' . $this->t('No grouping fields available. Please check that fields are defined on media types and nodes.') . '</div>',
      ];
      return $element;
    }
    // Wrapper fieldset.
    $element['grouping_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Define grouping hierarchy'),
      '#attributes' => ['class' => ['grouping-fields-fieldset']],
    ];

    $element['grouping_fieldset']['grouping_table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Level'),
        $this->t('Field'),
        $this->t('Weight'),
      ],
      '#attributes' => ['id' => 'grouping-fields-table'],
      '#empty' => $this->t('No grouping fields selected.'),
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'grouping-order-weight',
        ],
      ],
    ];

    // Construire les lignes existantes à partir des valeurs déjà sauvegardées.
    $values = [];
    foreach ($items as $item) {
      if (!empty($item->value)) {
        $values[] = $item->value;
      }
    }

    // Toujours ajouter une ligne vide pour permettre l'ajout d'un nouveau niveau.
    $values[] = '';

    foreach ($values as $delta => $field_value) {
      $level = $delta + 1;

      $element['grouping_fieldset']['grouping_table'][$delta] = [
        '#attributes' => [
          'class' => ['draggable'],
          'data-draggable-group' => 'grouping-order',
        ],
      ];

      $element['grouping_fieldset']['grouping_table'][$delta]['level'] = [
        '#markup' => '<strong>' . $this->t('Level @level', ['@level' => $level]) . '</strong>',
      ];

      $element['grouping_fieldset']['grouping_table'][$delta]['field'] = [
        '#type' => 'select',
        '#title' => $this->t('Field for level @level', ['@level' => $level]),
        '#title_display' => 'invisible',
        '#options' => ['' => $this->t('- Select field -')] + $field_options,
        '#default_value' => $field_value,
      // Les existants sont requis, le nouveau est optionnel.
        '#required' => FALSE,
      ];

      $element['grouping_fieldset']['grouping_table'][$delta]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for row @number', ['@number' => $level]),
        '#title_display' => 'invisible',
        '#default_value' => $delta,
        '#attributes' => ['class' => ['grouping-order-weight']],
        '#delta' => 10,
      ];
    }

    // Message d'aide.
    $element['grouping_fieldset']['help'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['description']],
      'content' => [
        '#markup' => '<p><strong>' . $this->t('Hierarchy:') . '</strong> ' .
        $this->t('Select fields and drag to reorder. Level 1 is the top-level grouping, Level 2 is nested within Level 1, etc.') . '</p>',
      ],
    ];

    return $element;
  }

  /**
   * Get all available field options from service.
   */
  protected function getFieldOptions() {
    $options = [];

    // Champs du node (album) - préfixés avec "node:".
    $node_fields = $this->groupingFieldsService->getNodeFields();
    foreach ($node_fields as $field_name => $config) {
      $options['node:' . $field_name] = $config['label'] . ' (' . $this->t('Album') . ')';
    }

    // Champs des médias - préfixés avec "media:".
    $media_fields = $this->groupingFieldsService->getMediaFields();
    foreach ($media_fields as $field_name => $config) {
      $options['media:' . $field_name] = $config['label'] . ' (' . $this->t('Media') . ')';
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $result = [];

    // Vérifier que grouping_table existe et est un tableau.
    if (!isset($values['grouping_fieldset']['grouping_table']) || !is_array($values['grouping_fieldset']['grouping_table'])) {
      return $result;
    }

    // Trier par poids.
    $sorted = $values['grouping_fieldset']['grouping_table'];
    uasort($sorted, function ($a, $b) {
      $weight_a = isset($a['weight']) && is_numeric($a['weight']) ? (int) $a['weight'] : 0;
      $weight_b = isset($b['weight']) && is_numeric($b['weight']) ? (int) $b['weight'] : 0;
      return $weight_a <=> $weight_b;
    });

    foreach ($sorted as $row) {
      if (isset($row['field']) && !empty($row['field']) && is_string($row['field'])) {
        $result[] = ['value' => $row['field']];
      }
    }

    return array_values($result);
  }

}

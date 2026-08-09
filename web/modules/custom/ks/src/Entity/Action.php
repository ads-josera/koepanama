<?php

namespace Drupal\ks\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\ks\ActionInterface;

/**
 * Defines the action entity class.
 *
 * @ContentEntityType(
 *   id = "action",
 *   label = @Translation("Action"),
 *   label_collection = @Translation("Actions"),
 *   label_singular = @Translation("action"),
 *   label_plural = @Translation("actions"),
 *   label_count = @PluralTranslation(
 *     singular = "@count actions",
 *     plural = "@count actions",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\ks\ActionListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\ks\Form\ActionForm",
 *       "edit" = "Drupal\ks\Form\ActionForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\ks\Routing\ActionHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "action",
 *   admin_permission = "administer action",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "id",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/action",
 *     "add-form" = "/action/add",
 *     "canonical" = "/action/{action}",
 *     "edit-form" = "/action/{action}",
 *     "delete-form" = "/action/{action}/delete",
 *   },
 *   field_ui_base_route = "entity.action.settings",
 * )
 */
class Action extends ContentEntityBase implements ActionInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the action was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}

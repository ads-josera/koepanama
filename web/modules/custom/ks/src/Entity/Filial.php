<?php

namespace Drupal\ks\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\ks\FilialInterface;

/**
 * Defines the filial entity class.
 *
 * @ContentEntityType(
 *   id = "filial",
 *   label = @Translation("Filial"),
 *   label_collection = @Translation("Filials"),
 *   label_singular = @Translation("filial"),
 *   label_plural = @Translation("filials"),
 *   label_count = @PluralTranslation(
 *     singular = "@count filials",
 *     plural = "@count filials",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\ks\FilialListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\ks\FilialAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\ks\Form\FilialForm",
 *       "edit" = "Drupal\ks\Form\FilialForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "filial",
 *   admin_permission = "administer filial",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/filial",
 *     "add-form" = "/filial/add",
 *     "canonical" = "/filial/{filial}",
 *     "edit-form" = "/filial/{filial}/edit",
 *     "delete-form" = "/filial/{filial}/delete",
 *   },
 *   field_ui_base_route = "entity.filial.settings",
 * )
 */
class Filial extends ContentEntityBase implements FilialInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Label'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}

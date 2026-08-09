<?php

namespace Drupal\ks\Plugin\views\field;

use Drupal\views\ResultRow;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\ks\Folio;
use Drupal\user\Entity\User;
use Drupal\Core\Url;
use Drupal;

/**
 * A handler to provide proper displays for bitácora folio.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("ks_view_bitacora_folio")
 */
class KsViewBitacoraFolio extends FieldPluginBase
{
    /**
     * {@inheritdoc}
     */
    public function render(ResultRow $values)
    {
        $relationship_entities = $values->_relationship_entities;
        if (isset($relationship_entities['webform_submission'])) {// First check the referenced entity.
            $webform_submission = $relationship_entities['webform_submission'];
        } else {
            $webform_submission = $values->_entity;
        }

        $data = $webform_submission->getData();
        $webform = $webform_submission->getWebform();
        $webform_id = $webform->id();

        if (in_array($webform_id, ['bienvenida', 'chequeo', 'facturacion', 'entrega'])) {
            $folio = isset($data[$webform_id.'_matricula']) ? $data[$webform_id.'_matricula'] : (isset($data[$webform_id.'_contrato']) ? $data[$webform_id.'_contrato'] : '-');
        } else {
            $folio = isset($data['folio']) ? 'ON-'.$data['folio'] : '-';
        }

        return $folio;
    }

    /**
     * {@inheritdoc}
     */
    public function query()
    {
        // Do nothing.
    }
}

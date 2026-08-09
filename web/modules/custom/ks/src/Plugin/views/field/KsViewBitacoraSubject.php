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
 * A handler to provide proper displays for bitácora subject.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("ks_view_bitacora_subject")
 */
class KsViewBitacoraSubject extends FieldPluginBase
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

        $messages = [];

        $all_handlers = $webform->getHandlers();
        $all_handlers_id = $all_handlers->getInstanceIds();
        $actual_handers = [];

        switch ($webform_id) {
            case 'contrato_status_update':
                $status = (int) $data['status'];

                $handlers = [8 => ['contrato_codeudor', 'aceptar_titular_ejecutivo', 'aceptar_titular_verificacion'], 2 => ['aceptar_ejecutivo', 'aceptar_verificacion'], 3 => ['revisar_ejecutivo'], 5 => ['reemplazar_ejecutivo'], 6 => ['aceptar_sustitucion']];
                if (isset($handlers[$status])) {
                    $actual_handers = $handlers[$status];
                }
            break;
            case 'contrato':
                $actual_handers = ['contrato_cliente', 'koe_mexico_contrato_ejecutivo'];
            break;
            case 'referidos':
            break;
            default:
                if (count($all_handlers_id) == 1) {
                    $actual_handers[] = reset($all_handlers_id);
                }
        }

        $output = [];
        if (!empty($actual_handers)) {
            $many = (count($actual_handers) > 1);
            $template = '<strong>@subject</strong><br>@to_mail<br><button type="button" class="bitacora-accion btn btn-light mt-1" data-url="@url">REENVIAR</button>';
            $template .= $many ? '<hr>' : '';

            foreach ($actual_handers as $handler_id) {
                if ($all_handlers->has($handler_id)) {
                    $handler = $webform->getHandler($handler_id);
                    $message = $handler->getMessage($webform_submission);
                    $message['resend_url'] = Url::fromRoute('ks.email_reenviar', ['sid' => $webform_submission->id(), 'handler_id' => $handler_id])->toString();
                    $output[] = ['#markup' => new FormattableMarkup($template, ['@subject' => $message['subject'], '@to_mail' => str_ireplace(',', ', ', $message['to_mail']), '@url' => $message['resend_url']])];
                }
            }
        }

        return $output;
    }

    /**
     * {@inheritdoc}
     */
    public function query()
    {
        // Do nothing.
    }
}

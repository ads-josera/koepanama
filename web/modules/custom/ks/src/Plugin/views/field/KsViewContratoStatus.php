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
 * A handler to provide proper displays for contrato status.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("ks_view_contrato_status")
 */
class KsViewContratoStatus extends FieldPluginBase
{
    /**
     * {@inheritdoc}
     */
    private const KOE_BORRADOR_STATUS = [//Esta constante funciona como referencia nada más
         0 => ['color' => 'E7E7E7', 'label' => 'Nuevo contrato', 'description' => 'Nuevo contrato sin borrador guardado'],
         1 => ['color' => '66CCFF', 'label' => 'En espera de aceptaciÓn por cliente', 'description' => 'Contrato enviado para aceptación de cliente'],
         8 => ['color' => '66CCFF', 'label' => 'En espera de aceptaciÓn por codeudor', 'description' => 'Contrato enviado para aceptación de codeudor'],
         2 => ['color' => 'FFCC66', 'label' => 'En espera de legalizar', 'description' => 'Contrato aceptado por cliente a espera de ser legalizado por Vendedor'],
         3 => ['color' => 'FFCC66', 'label' => 'RevisiÓn con ejecutivo', 'description' => 'Solicitud de revisión de contrato'],
         4 => ['color' => 'FF9966', 'label' => 'PerÍodo de aceptaciÓn vencido', 'description' => 'Cancelado por vencimiento de aceptación'],
         5 => ['color' => 'FF9966', 'label' => 'Cancelado para ediciÓn con nuevo folio', 'description' => 'Habilitado para registrarse con nuevo folio'],
         6 => ['color' => '66CC99', 'label' => 'Folio legalizado', 'description' => 'Contrato legalizado en DPS'],
         7 => ['color' => 'FF9966', 'label' => 'Cancelado y actualizado con nuevo folio', 'description' => 'Registrado con nuevo folio'],
     ];

    public function render(ResultRow $values)
    {
        $relationship_entities = $values->_relationship_entities;
        if (isset($relationship_entities['contrato'])) {// First check the referenced entity.
            $contrato = $relationship_entities['contrato'];
        } else {
            $contrato = $values->_entity;
        }

        $markup = [];

        $proxy = Folio::koe_contrato_evaluar($contrato);
        $status_id = $proxy['status'];

        $account = Drupal::currentUser();
        $account = User::load($account->id());

        $isVendedor = $account->hasRole('vendedor');

        $accionExtender = ['id' => 'extender', 'label' => 'Extender aceptaciÓn ['.(Folio::KOE_LIMITE_EXTENSION/24).' días]', 'permission' => 'koe contrato extender'];
        $accionCancelar = ['id' => 'cancelar', 'label' => 'Habilitar ediciÓn con nuevo folio', 'permission' => 'koe contrato cancelar'];
        $accionEditar = ['id' => 'editar', 'label' => 'Editar contrato', 'permission' => 'koe contrato editar'];
        $accionLegalizar = ['id' => 'legalizar', 'label' => 'Legaliza el contrato en DPS', 'permission' => 'koe contrato legalizar'];
        $accionRevisar = ['id' => 'revisar', 'label' => 'Ajustar contrato (revisiÓn)', 'permission' => 'koe contrato revisar'];

        $accionList = [
                0 => [],
                1 => $isVendedor ? [] : [$accionExtender, $accionRevisar],
                8 => $isVendedor ? [] : [$accionExtender, $accionRevisar],
                2 => $isVendedor ? [$accionLegalizar] : [],
                3 => $isVendedor ? [$accionEditar] : [$accionExtender],
                4 => $isVendedor ? [] : [$accionCancelar],
                5 => $isVendedor ? [$accionEditar] : [],
                6 => $isVendedor ? [] : [$accionCancelar],
                7 => [],
            ];

        if ($proxy['vencimiento'] > 0) {
            $markup[] = '<br><strong>'.(ceil(($proxy['vencimiento']-time())/3600)).' horas restantes</strong>';
        }
        if ($proxy['sisk'] > 0) {
            $markup[] = '<br><small>Contrato legalizado el '.date('d/M/Y - h:ia', $proxy['sisk']).'</small>';
        }
        if ($proxy['sustitucion'] > 0) {
            $markup[] = '<br><small>Contrato sustituído por folio '.$proxy['sustitucion'].'</small>';
        }
        if ($proxy['precursor'] > 0) {
            $markup[] = '<br><small>Contrato sustituye folio '.$proxy['precursor'].'</small>';
        }

        $acciones = isset($accionList[$status_id]) ? $accionList[$status_id] : [];
        foreach ($acciones as $accion) {
            if (is_null($accion) || !$account->hasPermission($accion['permission'])) {
                continue;
            }

            $buttonUrl = /*($accion['id'] == 'editar') ? Url::fromUri('internal:/ejecutivo/contrato', ['query' => ['folio' => $proxy['folio']]])->toString() :*/ Url::fromRoute('ks.contrato_'.$accion['id'], ['folio' => $proxy['folio']])->toString();

            if ($accion['id'] == 'extender' && $proxy['limite'] == Folio::KOE_LIMITE_EXTENSION) {
                $markup[] = '<br><small>Límite extendido a '.(Folio::KOE_LIMITE_EXTENSION/24).' días</small>';
            } else {
                $buttonLabel = strtoupper($accion['label']);
                if ($status_id == 5 && $accion['id'] == 'editar') {
                    $buttonLabel = 'Editar contrato con nuevo folio';
                }
                $markup[] = '<br><button type="button" class="contrato-accion btn btn-light mt-1" data-folio="'.$proxy['folio'].'" data-accion="'.$accion['id'].'" data-url="'.$buttonUrl.'">'.strtoupper($buttonLabel).'</button>';
            }
        }


        $statusLabel = $contrato->field_status->getSetting('allowed_values')[$status_id];
        if ($status_id == 5) {
            $statusLabel = ($proxy['sisk'] > 0) ? 'Cancelado para ediciÓn con nuevo folio' : 'Folio cancelado';
        }

        $template = '<strong>@statusLabel</strong>'.implode('', $markup);

        $output = [];
        $output[] = ['#markup' => new FormattableMarkup($template, ['@statusLabel' => strtoupper($statusLabel)])];

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

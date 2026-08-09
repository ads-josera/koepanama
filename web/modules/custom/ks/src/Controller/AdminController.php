<?php

namespace Drupal\ks\Controller;

use Drupal;
use Drupal\ks\Pdf;
use Drupal\ks\Folio;
use Drupal\ks\Entity\Contrato;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Serialization\Json;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;

/**
 * Implementing our example JSON api.
 */
class AdminController extends ControllerBase
{
    /**
     * Callback for the API.
     */
    public function access()
    {
        return  AccessResult::allowed();
    }

    public static function renderArray($id, $children)
    {
        $render = [
            '#theme' => 'container',
            '#attributes' => [
                'class' => ['koe-container', 'koe-'.$id],
            ],
            '#children' => [$children],
        ];

        return $render;
    }

    public function oauth_callback(Request $request)
    {
        try {
            $adapter = Pdf::getAdapter();
            $adapter->authenticate();
            $token = $adapter->getAccessToken();

            Pdf::updateAccessToken(json_encode($token));

            $result = 'Access token inserted successfully.';
        } catch (Exception $e) {
            $result = $e->getMessage() ;
            Drupal::logger('ks')->warning($result);
        }

        return new JsonResponse(['result' => $result]);
    }

    public function contrato_extender(int $folio)
    {
        $result = Folio::koe_contrato_extender($folio);
        return new JsonResponse(['result' => $result]);
    }

    public function contrato_valoracion(int $folio, string $valoracion)
    {
        $build = [];
        $markup = null;

        $contrato = Folio::koe_get_contrato($folio);
        if (!$contrato) {
            $markup = '<div class="koe-contrato-invalid">El folio no es válido.</div>';
        } else {
            $status = $contrato->field_status->value;
            if ($valoracion == 'aceptar') {
                if (!in_array($status, [1,8,6])) {
                    switch ($status) {
                        case 2:
                            $markup = '<div class="koe-contrato-invalid">Tu contrato ya ha sido registrado.<a href="https://koe.com.pa/">Ir al Sitio</a></div>';
                        break;
                        case 3:
                            $markup = '<div class="koe-contrato-invalid">Tu contrato se encuentra en proceso de revisión con el Asesor.<a href="https://koe.com.pa/">Ir al Sitio</a></div>';
                        break;
                        case 4:
                            $markup = '<div class="koe-contrato-invalid">Este contrato se encuentra cancelado.<a href="https://koe.com.pa/">Ir al Sitio</a></div>';
                        break;
                    }
                } else {
                    $data = ['folio' => $folio];
                    if (isset($contrato->field_precursor->value) && $contrato->field_precursor->value) {
                        $data['precursor'] = $contrato->field_precursor->value;
                    }
                    $build[] = ['#type' => 'webform', '#webform' => 'aceptacion', '#default_data' => $data];
                }
            } elseif ($valoracion == 'revisar') {
                if (!in_array($status, [1,8])) {
                    switch ($status) {
                        case 2:
                            $markup = '<div class="koe-contrato-invalid">Tu contrato fue aprobado y registrado previamente. Si requieres una revisión comunícate con tu Asesor.</div>';
                        break;
                        case 3:
                            $markup = '<div class="koe-contrato-invalid">Tu contrato ya se encuentra en proceso de revisión con el Asesor.</div>';
                        break;
                        case 4:
                            $markup = '<div class="koe-contrato-invalid">Este contrato se encuentra cancelado.<a href="https://koe.com.pa/">Ir al Sitio</a></div>';
                        break;
                    }
                } else {
                    $markup = '<div class="koe-contrato-valid">Hemos notificado a tu Asesor para que pueda apoyarte en la revisión de tu contrato.</div> ';
                    Folio::koe_contrato_status_update($folio, 3);
                }
            }
        }

        if (!is_null($markup)) {
            $build[] = ['#type' => 'markup', '#markup' => $markup];
        }

        return static::renderArray('valoracion', $build);
    }
    public function contrato_revision(int $folio)//revisar se ocupa ya en contrato_valoración
    {
        $contrato = Folio::koe_get_contrato($folio);
        $result = ($contrato && in_array($contrato->field_status->value, [1,8])) ? Folio::koe_contrato_status_update($folio, 3) : false;
        return new JsonResponse(['result' => $result]);
    }

    public function contrato_ejecutivo(int $folio = 0)
    {
        $build = [];
        $data = [];

        if ($folio) {
            $contrato = Folio::koe_get_contrato($folio);
            if (!$contrato || !in_array($contrato->field_status->value, [3,5])) {
                $build[] = ['#type' => 'markup', '#markup' => '<div class="koe-contrato-invalid">El folio a editar no es válido.</div>'];
                return static::renderArray('editar', $build);
            } else {
                $submission = $contrato->field_submission->entity;
                $data = $submission->getData();
                $data['contrato_id'] = $contrato->id();
                if ($contrato->field_status->value == 5) {//Se reemplaza por nuevo folio
                    $folio = 0;
                    $data['contrato_sustitucion'] = 1;
                }
            }
        }

        $data['folio'] = $data['contrato_matricula'] = $folio ? $folio : Folio::koe_folio('contrato');
        $data['attachment_url'] = Pdf::getFileRoute('contrato', $data['folio']);
        $build[] = ['#type' => 'webform', '#webform' => 'contrato', '#default_data' => $data];

        $pago_popup = Drupal::config('ks.settings')->get('pago_popup');
        if ($pago_popup) {
            $pago_contenido = Drupal::config('ks.settings')->get('pago_contenido');
            $pago_contenido = $pago_contenido ? $pago_contenido['value'] : '';
            $build[] = [
                '#type' => 'markup',
                '#markup' => '<div id="koe-pago-popup" class="mfp-hide">'.$pago_contenido.'</div>',
             ];
        }

        $referidos_popup = Drupal::config('ks.settings')->get('referidos_popup');
        if ($referidos_popup) {
            $referidos_contenido = Drupal::config('ks.settings')->get('referidos_contenido');
            $referidos_contenido = $referidos_contenido ? $referidos_contenido['value'] : '';
            $referidos_cta = Drupal::config('ks.settings')->get('referidos_cta');
            $referidos_cta = $referidos_cta ? $referidos_cta['value'] : '';
            $build[] = [
                '#type' => 'markup',
                '#markup' => '<div id="koe-referidos-popup" class="mfp-hide">'.$referidos_contenido.'</div>',
             ];
            $build[] = [
                 '#type' => 'markup',
                 '#markup' => '<div id="koe-referidos-cta" class="mfp-hide">'.$referidos_cta.'</div>',
              ];
        }

        return static::renderArray('editar', $build);
    }

    public function contrato_legalizar(int $folio)
    {
        $message = '';
        $contrato = Folio::koe_get_contrato($folio);
        if (!$contrato || $contrato->field_status->value != 2) {
            $message = 'Folio no válido o sin estatus para legalización.';
        } else {
            $submission = $contrato->field_submission->entity;
            $contratoData = unserialize($submission->getData()['contrato_data']);
            $contratoData['debug'] = 'false';//descomentar para que se legalice de verdad
            $result = Folio::koe_api_call('api/online/contrato', $contratoData);

            if (is_string($result) && strlen($result) > 3 && strlen($result) < 9) {
                $contrato->field_sisk = time();
                $contrato->save();

                Folio::koe_contrato_status_update($folio, 6);
                $message = 'El contrato fue legalizado exitosamente en el SISK.';
            } else {
                $message = isset($result['message']) ? $result['message'] : (string) $result;
            }
        }

        return new JsonResponse(['message' => $message]);
    }

    public function contrato_cancelar(int $folio)
    {
        $contrato = Folio::koe_get_contrato($folio);
        $result = (!$contrato || !in_array($contrato->field_status->value, [4,6])) ? false : Folio::koe_contrato_status_update($folio, 5);
        return new JsonResponse(['result' => $result]);
    }

    public function email_reenviar(int $sid, string $handler_id)
    {
        $webform_submission = WebformSubmission::load($sid);
        $webform = $webform_submission->getWebform();
        $handler = $webform->getHandler($handler_id);
        $message = $handler->getMessage($webform_submission);
        $result = $handler->sendMessage($webform_submission, $message);

        Drupal::messenger()->addStatus('Correo reenviado.');

        return new JsonResponse(['result' => $result]);
    }
}

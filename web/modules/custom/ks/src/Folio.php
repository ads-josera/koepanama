<?php

namespace Drupal\ks;

use Drupal;
use Drupal\user\Entity\User;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;

/**
 * Defines a class to keep business and session logic
 *
 * @ingroup ks_folio
 */
class Folio
{
    public static function listar($form_id)
    {
        $connection = Drupal::database();
        $result = $connection->query('SELECT * FROM {ks_folio} WHERE form_id=:form_id ORDER BY pid DESC LIMIT 30', [':form_id' => $form_id]);
        return $result->fetchAllAssoc('folio_id', \PDO::FETCH_ASSOC);
    }

    public static function actualizar($fields, $pid = null)
    {
        $connection = Drupal::database();

        if ($pid) {
            $connection->update('ks_folio')->fields($fields)->condition('pid', $pid)->execute();
        } else {
            $connection->insert('ks_folio')->fields($fields)->execute();
        }
    }

    public static function folio_has_contrato($folio_id)
    {
        $result = Drupal::entityTypeManager()->getStorage('contrato')->getQuery()->condition('field_folio', $folio_id)->accessCheck(false)->execute();
        return (bool) $result;
    }

    private const FOLIO_EXPIRATION_TIME = 60*60*36;//36 Horas
    public static function koe_folio($form_id, $update = false)
    {
        $current_user_id = Drupal::currentUser()->id();

        $now = time();

        $min_folio = null;
        $user_folio = null;

        $folios = static::listar($form_id);

        if (empty($folios)) {
            $folios = [];
            $folios[1] = ['pid' => null, 'form_id' => $form_id, 'folio_id' => 0001, 'user_id' => $current_user_id, 'date' => $now, 'taken' => 0];
        }

        $date_str = date('d/M/Y H:i:s');
        ddm($date_str.' - Usuario con ID '.$current_user_id.' y correo '.Drupal::currentUser()->getEmail().' solicita folio para formulario '.$form_id);

        $folio_ids = array_keys($folios);
        $folio_id_min = min($folio_ids);
        $folio_id_max = max($folio_ids);
        for ($folio_id = $folio_id_min; $folio_id <= $folio_id_max; $folio_id++) {
            $folio = $folios[$folio_id];
            $taken = intval($folio['taken']);
            if ($taken) {
                continue;
            }

            $user_id = intval($folio['user_id']);
            $date = intval($folio['date']);

            if ($user_id > 0 && ($now-$date > static::FOLIO_EXPIRATION_TIME)) {
                $folio['user_id'] = $user_id = 0;//Folio pasa a estar disponible
                static::actualizar($folio, $folio['pid']);
            }

            if ($user_id == 0 && is_null($min_folio) && !static::folio_has_contrato($folio_id)) {
                $min_folio = $folio;
            }//Folio mínimo disponible

            if ($user_id == $current_user_id) {
                $user_folio = $folio;
            }//Folio reservado por usuario actual
        }

        if (is_null($user_folio)) {
            $folio_id = $min_folio ? $min_folio['folio_id'] : $folio_id_max+1;
            $user_folio = ['pid' => $min_folio ? $min_folio['pid'] : null, 'form_id' => $form_id, 'folio_id' => $folio_id, 'user_id' => $current_user_id, 'date' => $now];
        }
        $user_folio['taken'] = $update ? 1 : 0;

        static::actualizar($user_folio, $user_folio['pid']);

        $folio_str = str_pad($user_folio['folio_id'], 4, '0', STR_PAD_LEFT);

        ddm($date_str.' - Se le entrega el folio: '.$folio_str);

        return $folio_str;
    }

    private const KOE_API_URL = 'https://apikoeweb2.koeonline.net/APIVerificacion';
    public static function koe_api_call($endpoint, $data, $method = 'POST', $use_token = true, $type = 'application/json')
    {
        $data = ($method == 'GET' || $type != 'application/json') ? http_build_query($data) : json_encode($data);

        $url = static::KOE_API_URL.'/'.$endpoint;
        if ($method == 'GET') {
            $url = sprintf('%s?%s', $url, $data);
        }

        $curl = curl_init($url);

        $headers = ['Content-Type: '.$type];
        if ($use_token) {
            $headers[] = 'Authorization: Bearer '.static::koe_session('token');
        }

        $date = date('d/M/Y H:i:s');
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        //curl_setopt($curl, CURLOPT_FAILONERROR, true);//Detección de errores de http

        if ($method == 'POST') {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        ddm($date.' - '.'Iniciando petición a '.$endpoint." con la siguiente data:\n".$data);

        $result = curl_exec($curl);
        ddm($date.' - '.'Se recibió la respuesta a '.$endpoint);

        if (curl_errno($curl) || !$result) {
            $error = 'koe_api_call error at: '.$url;
            ddm($date.' - '.$error);
            if (curl_errno($curl)) {
                ddm(curl_error($curl));
            }
            curl_close($curl);
            return $error;
        } else {
            ddm($date.' - '.$result);

            $result = json_decode($result, true);
            ddm(print_r($result, true));
            curl_close($curl);
            return $result;
        }
    }
    public static function koe_session($key, $value = null)
    {
        $session = Drupal::request()->getSession();

        if (is_null($session)) {
            return null;
        }

        if (is_null($value)) {
            return $session->get($key);
        } else {
            $session->set($key, $value);
            return $value;
        }
    }

    public const VENDEDOR_PASSWORD = '(})\_:Bs},4Efr}wQOC4';
    public static function koe_account_create($email, $username)
    {
        $account = User::create();
        $account->setPassword(static::VENDEDOR_PASSWORD);
        $account->enforceIsNew();
        $account->setEmail($email);
        $account->setUsername($username.' '.rand(10, 99));
        $account->set('field_fullname', $username);
        $account->addRole('vendedor');
        $account->activate();
        $account->save();
        return $account;
    }

    public static function koe_get_contrato($folio)
    {
        $contratos = Drupal::entityTypeManager()->getStorage('contrato')->loadByProperties(['field_folio' => $folio]);
        $contrato = reset($contratos);
        return $contrato;
    }

    public const KOE_LIMITE_EXTENSION = 30*24;//30 días

    public static function koe_contrato_extender($folio)
    {
        $contrato = static::koe_get_contrato($folio);
        if (!$contrato) {
            return false;
        }

        $contrato->field_limite = static::KOE_LIMITE_EXTENSION;
        $contrato->save();
        return true;
    }
    public static function koe_contrato_status_update($folio, $status, $extra = null)
    {
        $contrato = static::koe_get_contrato($folio);
        if (!$contrato) {
            return false;
        }

        $contrato->field_status = $status;
        $contrato->save();

        $data = ['folio' => $folio, 'status' => $status];
        if ($extra) {
            $data += $extra;
        }

        $data['ejecutivo_nombre'] = $contrato->uid->entity->field_fullname->value;
        $data['ejecutivo_email'] = $contrato->uid->entity->mail->value;

        $submission = $contrato->field_submission->entity;
        $submission_data = $submission->getData();
        $data['attachment_url'] = $submission_data['attachment_url'];

        $webform = Webform::load('contrato_status_update');
        $values = ['webform_id' => $webform->id(), 'data' => $data];
        $webform_submission = WebformSubmission::create($values);
        $webform_submission->save();

        return true;
    }

    public static function koe_contrato_evaluar($contrato)
    {
        $proxy = [
            'folio' => $contrato->field_folio->value,
            'status' => $contrato->field_status->value,
            'timestamp' => $contrato->created->value,
            'vencimiento' => 0,
            'limite' => $contrato->field_limite->value,
            'sisk' => $contrato->field_sisk->value,
            'sustitucion' => $contrato->field_sustitucion->value,
            'precursor' => $contrato->field_precursor->value,
        ];

        if (in_array($proxy['status'], [1, 8, 3])) {
            $vencimiento = $proxy['timestamp']+($proxy['limite']*60*60);//convirtiendo límite de horas a minutos a segundos
            $vencido = (time() > $vencimiento);
            if ($vencido) {
                $proxy['status'] = 4;
                static::koe_contrato_status_update($proxy['folio'], $proxy['status']);
            } else {
                $proxy['vencimiento'] = $vencimiento;
            }
        }

        return $proxy;
    }
}

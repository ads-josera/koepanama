<?php

namespace Drupal\ks\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\Component\Utility\Html;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

use Drupal;
use Drupal\ks\Folio;

/**
 * Webform validate handler.
 *
 * @WebformHandler(
 *   id = "ks_contrato_handler",
 *   label = @Translation("Contrato Handler"),
 *   category = @Translation("Settings"),
 *   description = @Translation("Validación y envío de contrato a SISK."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 * )
 */
class ContratoHandler extends WebformHandlerBase
{
    use StringTranslationTrait;

    public static function validatePhone($phone)
    {
        return preg_replace('/[-\s]+/', '', $phone);
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission)
    {
        if ($form_state->hasAnyErrors()) {
            return;
        }

        $values = $form_state->getValues();

        $pagare = (int) $values['pagare'];
        $pagare = (bool) $pagare;

        $date = date('m/d/Y h:i:s a', time());

        if ($pagare && trim(strtolower($values['comprador_correo'])) == trim(strtolower($values['pagare_correo']))) {
            ddm('pagare_correo');
            $form_state->setErrorByName('pagare_correo', 'El correo del codeudor y del titular no pueden ser iguales.');
            return;
        }

        if (trim($values['comprador_correo']) != trim($values['comprador_correo_verificar'])) {
            ddm('comprador_correo');
            $form_state->setErrorByName('comprador_correo', 'Por favor verifique el correo del titular.');
            return;
        }

        if (isset($values['revisar_completo']) && !is_null($values['revisar_completo']) && strlen(trim($values['revisar_completo'])) == 0) {
            ddm('revisar_completo');
            $form_state->setErrorByName('revisar_completo', 'Por favor indique el Estatus del contrato.');
            return;
        }

        $nombreArr = explode(' ', trim($values['comprador_nombre']));
        $titular = ['codusuario' => $values['comprador_rfc'], 'nombres' => array_shift($nombreArr), 'apellidos' => implode(' ', $nombreArr), 'email' => $values['comprador_correo'], 'sexo' => $values['beneficiarios_uno_sexo'], 'fechanacimiento' => $values['comprador_nacimiento'], 'telefonocelular' => static::validatePhone($values['comprador_celular']), 'telefonocasa' => static::validatePhone($values['comprador_telefono']), 'telefonooficina' => static::validatePhone($values['comprador_empresa_telefono']), 'ciudad' => $values['comprador_ciudad'], 'codPlanAcademico' => $values['beneficiarios_uno_servicio'], 'direccionCasa' => $values['comprador_domicilio'], 'direccionOficina' => $values['comprador_empresa_domicilio'], 'empresa' => $values['comprador_empresa'], 'cargo' => $values['comprador_cargo']];

        $nombreArr = explode(' ', trim($values['beneficiarios_uno_nombre']));
        $beneficiarioUno = ['codusuario' => $values['beneficiarios_uno_usuario'], 'nombres' => array_shift($nombreArr), 'apellidos' => implode(' ', $nombreArr), 'email' => $values['beneficiarios_uno_correo'], 'sexo' => $values['beneficiarios_uno_sexo'], 'fechanacimiento' => $values['beneficiarios_uno_nacimiento'], 'telefonocelular' => static::validatePhone($values['beneficiarios_uno_celular']), 'telefonocasa' => static::validatePhone($values['beneficiarios_uno_telefono']), 'codPlanAcademico' => $values['beneficiarios_uno_servicio'], 'direccionCasa' => $values['beneficiarios_uno_domicilio']];

        $nombreArr = explode(' ', trim($values['beneficiarios_dos_nombre']));
        $beneficiarioDos = ['codusuario' => $values['beneficiarios_dos_usuario'], 'nombres' => array_shift($nombreArr), 'apellidos' => implode(' ', $nombreArr), 'email' => $values['beneficiarios_dos_correo'], 'sexo' => $values['beneficiarios_dos_sexo'], 'fechanacimiento' => $values['beneficiarios_dos_nacimiento'], 'telefonocelular' => static::validatePhone($values['beneficiarios_dos_celular']), 'telefonocasa' => static::validatePhone($values['beneficiarios_dos_telefono']), 'codPlanAcademico' => $values['beneficiarios_dos_servicio'], 'direccionCasa' => $values['beneficiarios_dos_domicilio']];

        $beneficiarios = [$beneficiarioUno];

        if ($values['beneficiarios_dos']) {
            array_push($beneficiarios, $beneficiarioDos);
        }

        $contratoFecha = substr($values['contrato_fecha'], 0, 10);
        $contratoMatricula = str_pad($values['contrato_matricula'], 4, '0', STR_PAD_LEFT);
        $contrato = ['codMatricula' => 'ON'.$contratoMatricula, 'fechaContrato' => $contratoFecha, 'idDato' => $values['comprador_dato'], 'idFilial' => $values['contrato_filial'], 'codPlan' => $values['condiciones_plan'], 'titular' => $titular, 'beneficiarios' => $beneficiarios];

        //En caso de revisión de contrato noEmailValidationInDebug es igual a true, false otherwise
        $revision = (int) $values['contrato_sustitucion'];
        $contratoData = ['debug' => 'true', 'noEmailValidationInDebug' => (bool) $revision, 'contrato' => $contrato];
        $contratoData['contrato']['emailoperador'] = Folio::koe_session('emailoperador');
        $contratoData['contrato']['codPais'] = Folio::koe_session('codPais');

        ddm('validateForm');
        //ddm($contratoData);
        $result = Folio::koe_api_call('api/online/contrato', $contratoData);//Esta llamada es siempre con validación (debug = true) ya que se ejecuta múltiples veces a lo largo del formulario
        ddm($result);
        if (is_string($result) && strlen($result) > 3 && strlen($result) < 9) {
            $form_state->set('revisar_ok', true);
            $form_state->setValue('contrato_data', serialize($contratoData));
        } else {
            ddm('error en revisar_trigger');
            $form_state->set('revisar_ok', false);
            $message = isset($result['message']) ? $result['message'] : (string) $result;
            $form_state->setErrorByName('revisar_trigger', $message);
            //Drupal::messenger()->addError($message);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission)
    {
        if (!$form_state->hasAnyErrors()) {
            ddm('Envío sin errores');//Aquí no se lleva a cabo ninguna legalización, eso se hace únicamente con el botón legalizar en AdminController.
        } else {
            ddm('Favor de revisar errores: ');
            $errors = $form_state->getErrors();
            $errors = print_r($errors, true);
            ddm($errors);
        }
    }
}

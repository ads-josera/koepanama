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
 *   id = "ks_referidos_handler",
 *   label = @Translation("Referidos Handler"),
 *   category = @Translation("Settings"),
 *   description = @Translation("Validación y envío de referidos a SISK."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 * )
 */
class ReferidosHandler extends WebformHandlerBase
{
    use StringTranslationTrait;
    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission)
    {
        ksm($form);

        $values = $form_state->getValues();

        $dato = $values['dato'];
        if (strlen($dato) < 5) {
            $form_state->setErrorByName('dato', 'Debe proporcionar un DATO válido.');
            return;
        }

        $list = [];
        foreach ($values['referido'] as $referido) {
            $list[] = ['nombres' => $referido['nombre'], 'apellidos' => $referido['apellido'], 'documento' => $referido['telefono'], 'email' => $referido['email']];
        }
        $referidosData = ['iddato' => $dato, 'list' => $list, 'debug' => true];
        $results = Folio::koe_api_call('api/online/referidos', $referidosData);
        ddm($results);
        if (is_array($results)) {
            $referido_idx = 0;
            foreach ($results as $result) {
                $referido_idx++;
                if ($result['exitoso']) {
                } else {
                    $form_state->setError($form['elements']['referido']['items'][$referido_idx-1], $this->t('Referido No. %idx presenta el siguiente problema: %error', ['%idx' => $referido_idx, '%error' => $result['mensajeError']]));
                    //$form_state->setErrorByName('dato', $this->t('Referido No. %idx presenta el siguiente problema: %error', ['%idx' => $referido_idx, '%error' => $result['mensajeError']]));
                }
            }
        }
        if (!$form_state->hasAnyErrors()) {
            $form_state->setValue('referidos_data', serialize($referidosData));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission)
    {
        if (!$form_state->hasAnyErrors()) {
            $values = $form_state->getValues();
            $referidosData = unserialize($values['referidos_data']);
            $referidosData['debug'] = 'false';
            $results = Folio::koe_api_call('api/online/referidos', $referidosData);
        //$this->messenger()->addStatus($this->t('Referidos ingresados correctamente.'));
        } else {
            $errors = $form_state->getErrors();
            $this->messenger()->addError($this->t('Favor de revisar errores: %errores', ['%errores' => print_r($errors, true)]));
        }
    }
}

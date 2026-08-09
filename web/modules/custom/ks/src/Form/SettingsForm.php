<?php

namespace Drupal\ks\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Render\FormattableMarkup;

/**
 * Configure Ks settings for this site.
 */
class SettingsForm extends ConfigFormBase
{
    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'ks_settings';
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames()
    {
        return ['ks.settings'];
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $form['pago_title'] = ['#markup' => new FormattableMarkup('<h5>@title</h5>', ['@title' => 'Pago Popup'])];

        $form['pago_popup'] = [
            '#type' => 'checkbox',
            '#default_value' => $this->config('ks.settings')->get('pago_popup'),
            '#title' => $this->t('Mostrar automáticamente popup de pago en Datos Asesor.'),
        ];

        $pago_contenido = $this->config('ks.settings')->get('pago_contenido');
        $form['pago_contenido'] = [
            '#type' => 'text_format',
            '#format' => $pago_contenido ? $pago_contenido['format'] : 'full_html',
            '#default_value' => $pago_contenido ? $pago_contenido['value'] : '',
            '#title' => $this->t('Contenido'),
        ];

        $form['hr'] = ['#markup' => '<hr/>'];

        $form['referidos_title'] = ['#markup' => new FormattableMarkup('<h5>@title</h5>', ['@title' => 'Referidos Popup'])];

        $form['referidos_popup'] = [
            '#type' => 'checkbox',
            '#default_value' => $this->config('ks.settings')->get('referidos_popup'),
            '#title' => $this->t('Mostrar automáticamente popup y call to action de referidos al finalizar contrato.'),
        ];

        $referidos_contenido = $this->config('ks.settings')->get('referidos_contenido');
        $form['referidos_contenido'] = [
            '#type' => 'text_format',
            '#format' => $referidos_contenido ? $referidos_contenido['format'] : 'full_html',
            '#default_value' => $referidos_contenido ? $referidos_contenido['value'] : '',
            '#title' => $this->t('Popup'),
            '#description' => $this->t('El token [dato] dentro del html de este campo se reemplaza por el dato correspondiente.'),
        ];

        $referidos_cta = $this->config('ks.settings')->get('referidos_cta');
        $form['referidos_cta'] = [
            '#type' => 'text_format',
            '#format' => $referidos_cta ? $referidos_cta['format'] : 'full_html',
            '#default_value' => $referidos_cta ? $referidos_cta['value'] : '',
            '#title' => $this->t('Call To Action'),
            '#description' => $this->t('El token [dato] dentro del html de este campo se reemplaza por el dato correspondiente.'),
        ];

        $form['referidos_indicaciones'] = ['#markup' => new FormattableMarkup('<strong>@indicaciones</strong>', ['@indicaciones' => 'Colocar class “link-img” dentro de tu “<a>” link de imagen para mantener diseño.
        El link dinámico de referidos debe ser dominio más token ejemplo: https://dominio/referidos?dato=[dato]'])];


        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        parent::validateForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $fields = ['pago_popup', 'pago_contenido', 'referidos_popup', 'referidos_contenido', 'referidos_cta'];
        foreach ($fields as $field) {
            $this->config('ks.settings')->set($field, $form_state->getValue($field))->save();
        }
        parent::submitForm($form, $form_state);
    }
}

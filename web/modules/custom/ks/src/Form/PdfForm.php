<?php

namespace Drupal\ks\Form;

use Drupal;
use Drupal\ks\Pdf;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Entity\Webform;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystemInterface;

/**
 * Class PdfForm.
 */
class PdfForm extends FormBase
{
    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'pdf_form';
    }

    public function access(Webform $webform = null)
    {
        return Pdf::hasPdf($webform->id()) ? AccessResult::allowed() : AccessResult::forbidden();
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, Webform $webform = null)
    {
        $webform_id = $webform->id();
        $form_state->set('webform_id', $webform_id);

        $form['title'] = [
            '#type' => 'html_tag',
            '#tag' => 'h2',
            '#value' => $this->t('PDF @webform_id', ['@webform_id' => $webform_id])
        ];

        $templatesLocation = 'public://pdf/templates/';
        $pdfpath = $templatesLocation.$webform_id.'.pdf';
        $form_state->set('pdfpath', $pdfpath);

        $form['link'] = Link::fromTextAndUrl(t('Descargar PDF actual [Respaldo]'), Drupal::service('file_url_generator')->generate($pdfpath))->toRenderable();
        $form['link']['#attributes'] = ['download' => true];
        $form['pdf'] = [
          '#type' => 'managed_file',
          '#title' => $this->t('Reemplazar'),
          '#upload_location' => $templatesLocation,
          '#progress_indicator' => 'throbber',
          '#progress_message' => $this->t('reemplazando pdf...'),
          '#accept' => 'application/pdf'
        ];
        $form['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Reemplazar PDF'),
        ];

        $form['rule'] = [
            '#prefix' => '<p>',
            '#suffix' => '</p>',
            '#type' => 'html_tag',
            '#tag' => 'hr',
        ];

        $mapas = Pdf::listar($webform_id);
        $options = [];
        $idx = 1;
        foreach ($mapas as $pid => $mapa) {
            $options[$pid] = 'Página '.($idx++);
        }
        $form['pagina'] = [
            '#type' => 'select',
            '#options' => $options,
            '#size' => 2,
            '#ajax' => ['callback' => '::paginaChange', 'wrapper' => 'campos-wrapper', 'progress' => ['type' => 'none', 'message' => null]]
          ];

        $pid = $form_state->getValue('pagina');
        $form_state->set('pid', $pid);

        $form['campos'] = [
            '#prefix' => '<div id="campos-wrapper">',
            '#suffix' => '</div>',
            '#type' => $pid ? 'fieldset' : 'hidden',
            '#title' => $pid ? $options[$pid] : '',
            '#open' => true,
            '#collapsible' => false
          ];

        $campos = $pid ? unserialize($mapas[$pid]['campos']) : [];
        $form_state->set('campos', $campos);

        $options = [];
        foreach ($campos as $key => $value) {
            $options[$key] = $key;
        }

        $form['campos']['add'] = [
            '#prefix' => '<p>',
            '#suffix' => '</p>',
            '#type' => 'submit',
            '#value' => $this->t('Nuevo campo'),
            '#submit' => ['::campoAdd'],
        ];

        $form['campos']['campo'] = [
            '#prefix' => '<div class="koe-admin-select">',
            '#suffix' => '</div>',
            '#type' => 'select',
            '#options' => $options,
            '#size' => count($options),
            '#ajax' => ['callback' => '::campoChange', 'wrapper' => 'campo-wrapper', 'progress' => ['type' => 'none', 'message' => null]],
        ];

        $campo = $form_state->getValue('campo');

        $form['campos']['posiciones'] = [
            '#prefix' => '<div id="campo-wrapper" class="koe-admin-group">',
            '#suffix' => '</div>',
            '#type' => $campo ? 'fieldset' : 'hidden',
            '#title' => $campo ? $campo : '',
            '#open' => true,
            '#collapsible' => false
        ];

        $values = ($campo && isset($campos[$campo])) ? $campos[$campo] : null;

        if (!is_null($values)) {
            $form['campos']['posiciones']['key'] = [
                '#type' => 'textfield',
                '#title' => 'ID',
                '#pattern' => '[a-z_]+',
                '#required' => true,
                '#value' => $campo,
            ];

            for ($i = 1; $i <= 3; $i++) {
                $form['campos']['posiciones']['open_div'.$i] = ['#markup' => '<div class="koe-admin-inline">'];

                $form['campos']['posiciones']['title'.$i] = [
                    '#type' => 'html_tag',
                    '#tag' => 'h6',
                    '#value' => $this->t('Posición @i', ['@i' => $i])
                ];

                $form['campos']['posiciones']['x'.$i] = [
                    '#type' => 'number',
                    '#title' => 'X',
                    '#value' => isset($values['x'.$i]) ? (float) $values['x'.$i] : 0,
                    '#min' => '0',
                    '#max' => '999.9',
                    '#step' => '0.1',
                ];

                $form['campos']['posiciones']['y'.$i] = [
                    '#type' => 'number',
                    '#title' => 'Y',
                    '#value' => isset($values['y'.$i]) ? (float) $values['y'.$i] : 0,
                    '#min' => '0',
                    '#max' => '999.9',
                    '#step' => '0.1',
                ];

                $form['campos']['posiciones']['formato'.$i] = [
                    '#type' => 'select',
                    '#title' => 'Formato',
                    '#value' => isset($values['formato'.$i]) ? $values['formato'.$i] : '',
                    '#empty_value' => '',
                    '#options' => ['zerofill' => 'Zero Fill', 'date' => 'Fecha', 'split_date' => 'Fecha dividida', 'split_date_bienvenida' => 'Fecha dividida bienvenida', 'split_date_contrato' => 'Fecha dividida contrato','currency' => 'Moneda', 'number' => 'Número', 'yes_no' => 'Sí / No', 'checkbox'	=> 'Checkbox', 'wordwrap_chequeo' => 'Chequeo Wordwrap', 'wordwrap_bienvenida'=> 'Bienvenida Wordwrap', 'facturacion_metodo_pago' => 'Facturación Método Pago', 'facturacion_formas_pago' => 'Facturación Formas Pago', 'facturacion_regimen' => 'Facturación Régimen', 'entrega_tipo' => 'Entrega Tipo'],
                ];

                $form['campos']['posiciones']['pagare'.$i] = [
                    '#type' => 'checkbox',
                    '#title' => 'Pagaré',
                    '#value' => isset($values['pagare'.$i]),
                ];

                $form['campos']['posiciones']['close_div'.$i] = ['#markup' => '</div>'];
            }

            $form['campos']['posiciones']['actions'] = ['#type' => 'actions'];

            $form['campos']['posiciones']['actions']['save'] = [
                '#type' => 'submit',
                '#value' => $this->t('Guardar cambios de este campo'),
                '#submit' => ['::campoSave'],
            ];

            $form['campos']['posiciones']['actions']['delete'] = [
                '#type' => 'submit',
                '#value' => $this->t('Eliminar este campo'),
                '#submit' => ['::campoDelete'],
            ];
        }



        return $form;
    }
    public function paginaChange(array &$form, FormStateInterface $form_state)
    {
        return $form['campos'];
    }
    public function campoChange(array &$form, FormStateInterface $form_state)
    {
        return $form['campos']['posiciones'];
    }

    public function campoAdd(array &$form, FormStateInterface $form_state)
    {
        static::campoProcesar($form_state);

        Drupal::messenger()->addStatus($this->t('Se agregó un nuevo campo.'));
        $form_state->setRebuild(true);
    }
    public function campoSave(array &$form, FormStateInterface $form_state)
    {
        $values = $form_state->getUserInput();

        $campo = $values['campo'];

        $posiciones = [];

        for ($i = 1; $i <= 3; $i++) {
            $x = (float) $values['x'.$i];
            $y = (float) $values['y'.$i];

            if ($x == 0 || $y == 0) {
                break;
            }

            $posiciones['x'.$i] = $x;
            $posiciones['y'.$i] = $y;

            $formato = isset($values['formato'.$i]) ? $values['formato'.$i] : false;
            $pagare = isset($values['pagare'.$i]) ? $values['pagare'.$i] : false;

            if ($formato) {
                $posiciones['formato'.$i] = $formato;
            }
            if ($pagare) {
                $posiciones['pagare'.$i] = true;
            }
        }

        static::campoProcesar($form_state, $campo, $posiciones, $values['key']);

        Drupal::messenger()->addStatus($this->t('Se guardaron los cambios al campo @campo.', ['@campo' => $campo]));
        $form_state->setRebuild(true);
    }
    public function campoDelete(array &$form, FormStateInterface $form_state)
    {
        $values = $form_state->getUserInput();

        $campo = $values['campo'];
        static::campoProcesar($form_state, $campo);

        Drupal::messenger()->addStatus($this->t('Se eliminó el campo @campo.', ['@campo' => $campo]));
        $form_state->setRebuild(true);
    }

    public static function campoProcesar(FormStateInterface $form_state, $campoActual = null, $posiciones = null, $campoNuevo = null)
    {
        $webform_id = $form_state->get('webform_id');
        $campos = $form_state->get('campos');
        $pid = $form_state->get('pid');

        if (is_null($campoActual)) {//Add new
            $campos['nuevo_campo'] = [];
        } else {//Unset prior campoActual either due to deletion or to replacement
            unset($campos[$campoActual]);
        }

        if (!is_null($posiciones)) {
            $campos[$campoNuevo] = $posiciones;
        }

        Pdf::actualizar($webform_id, $campos, $pid);
    }

    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $pdf = $form_state->getValue('pdf');
        if (!$pdf) {
            Drupal::messenger()->addWarning($this->t('Elija un archivo PDF.'));
            return;
        }
        $file = File::load($pdf[0]);

        $file_real_path = \Drupal::service('file_system')->realpath($file->getFileUri());
        $file_contents = file_get_contents($file_real_path);

        Drupal::service('file.repository')->writeData($file_contents, $form_state->get('pdfpath'), FileSystemInterface::EXISTS_REPLACE);
        Drupal::messenger()->addStatus($this->t('Se reemplazo el PDF.'));
    }
}

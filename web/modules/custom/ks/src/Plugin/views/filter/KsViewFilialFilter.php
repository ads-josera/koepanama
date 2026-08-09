<?php

namespace Drupal\ks\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\StringFilter;

/**
 * Filter by start and end date.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("ks_view_filial_filter")
 */
class KsViewFilialFilter extends StringFilter
{
    /**
     * {@inheritdoc}
     */
    protected function defineOptions()
    {
        $options = parent::defineOptions();

        return $options;
    }

    /**
     * {@inheritdoc}
     */
    public function buildOptionsForm(&$form, FormStateInterface $form_state)
    {
        $form['filial_filter'] = [
            '#markup' => $this->t('Filtra los contratos de acuerdo a las filiales del usuario máster o submáster.'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function validateOptionsForm(&$form, FormStateInterface $form_state)
    {
        parent::validateOptionsForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function query()
    {
        $this->ensureMyTable();

        /** @var \Drupal\views\Plugin\views\query\Sql $query */
        $query = $this->query;

        $account = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
        $filiales = $account->field_filiales->referencedEntities();
        if (!empty($filiales)) {
            $ids = [];
            foreach ($filiales as $filial) {
                $ids[] = $filial->field_id->value;
            }
            $query->addWhere($this->options['group'], 'webform_submission_contrato__field_submission_webform_submission_field_contrato_contrato_filial.value', $ids, 'IN');
        }
    }
}

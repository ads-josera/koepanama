<?php

namespace Drupal\ks\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the contrato entity edit forms.
 */
class ContratoForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $result = parent::save($form, $form_state);

    $entity = $this->getEntity();

    $message_arguments = ['%label' => $entity->toLink()->toString()];
    $logger_arguments = [
      '%label' => $entity->label(),
      'link' => $entity->toLink($this->t('View'))->toString(),
    ];

    switch ($result) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('New contrato %label has been created.', $message_arguments));
        $this->logger('ks')->notice('Created new contrato %label', $logger_arguments);
        break;

      case SAVED_UPDATED:
        $this->messenger()->addStatus($this->t('The contrato %label has been updated.', $message_arguments));
        $this->logger('ks')->notice('Updated contrato %label.', $logger_arguments);
        break;
    }

    $form_state->setRedirect('entity.contrato.canonical', ['contrato' => $entity->id()]);

    return $result;
  }

}

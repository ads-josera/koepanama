<?php

namespace Drupal\ks\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the filial entity edit forms.
 */
class FilialForm extends ContentEntityForm {

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
        $this->messenger()->addStatus($this->t('New filial %label has been created.', $message_arguments));
        $this->logger('ks')->notice('Created new filial %label', $logger_arguments);
        break;

      case SAVED_UPDATED:
        $this->messenger()->addStatus($this->t('The filial %label has been updated.', $message_arguments));
        $this->logger('ks')->notice('Updated filial %label.', $logger_arguments);
        break;
    }

    $form_state->setRedirect('entity.filial.canonical', ['filial' => $entity->id()]);

    return $result;
  }

}

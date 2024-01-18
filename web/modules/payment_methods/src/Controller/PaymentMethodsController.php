<?php

namespace Drupal\payment_methods\Controller;

use Drupal\Core\Controller\ControllerBase;

class PaymentMethodsController extends ControllerBase {

  public function listing() {
    $content = [];

    $content['title'] = 'Payment Methods';

    $content['data'] = \Drupal::database()->select('payment_methods', 'n')
      ->fields('n', array('id', 'name', 'name_ar', 'code', 'description', 'description_ar', 'status', 'created', 'changed'))
      ->execute()->fetchAllAssoc('id');

    return [
      '#theme' => 'payment-methods',
      '#content' => $content,
    ];
  }

  public function enable($id) {
    $connection = \Drupal::database();
    $connection->update('payment_methods')
      ->fields([
        'status' => '1',
        'changed' => time(),
      ])
      ->condition('id', $id)
      ->execute();

    \Drupal::messenger()->addStatus($this->t('Payment method has been enabled.'));
    return $this->redirect('payment_methods.listing');
  }

  public function disable($id)
  {
    $connection = \Drupal::database();
    $connection->update('payment_methods')
      ->fields([
        'status' => '0',
        'changed' => time(),
      ])
      ->condition('id', $id)
      ->execute();

    \Drupal::messenger()->addStatus($this->t('Payment method has been disabled.'));
    return $this->redirect('payment_methods.listing');
  }

}

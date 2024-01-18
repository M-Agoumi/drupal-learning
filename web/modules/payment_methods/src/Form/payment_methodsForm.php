<?php

namespace Drupal\payment_methods\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;

class payment_methodsForm extends FormBase
{

  protected $id;

  public function getFormId()
  {
    return 'payment_methods_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $id = null)
  {
    $form = [];

    $form['payment_method_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#description' => $this->t('Enter the name of the payment method.'),
      '#required' => TRUE,
    ];

    $form['payment_method_name_ar'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name in Arabic'),
      '#description' => $this->t('Enter the name of the payment method.'),
      '#required' => TRUE,
    ];

    $form['payment_method_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Code'),
      '#description' => $this->t('Enter the code of the payment method.'),
      '#required' => TRUE,
    ];

    $form['payment_method_description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Description'),
      '#description' => $this->t('Enter the description of the payment method.'),
      '#required' => false,
    ];

    $form['payment_method_description_ar'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Description in arabic'),
      '#description' => $this->t('Enter the description of the payment method.'),
      '#required' => false,
    ];


    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
      ],
    ];

    // If $id is not NULL, it means it's an edit operation.
    if ($id) {
      // Fetch payment method details based on $id and pre-fill the form fields.
      // You may need to implement a method to retrieve data based on the ID.
      $payment_method = $this->loadPaymentMethodById($id);

      // Set default values for the form fields.
      $form['payment_method_name']['#default_value'] = $payment_method['name'];
      $form['payment_method_name_ar']['#default_value'] = $payment_method['name_ar'];
      $form['payment_method_code']['#default_value'] = $payment_method['code'];
      $form['payment_method_description']['#default_value'] = $payment_method['description'];
      $form['payment_method_description_ar']['#default_value'] = $payment_method['description_ar'];

      // Store the ID for later use in the submit handler.
      $this->id = $id;
    }
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    $formFields = $form_state->getValues();

    $name = $formFields['payment_method_name'];
    $code = $formFields['payment_method_code'];
    $description = $formFields['payment_method_description'];

    if (!preg_match('/^[a-z_\d]{2,}$/i', $code)) {
      $form_state->setErrorByName('payment_method_code', $this->t('The code must be at least 2 characters long and contain only letters, numbers, underscores.'));
    }
  }


  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($this->id) {
      // It's an edit operation, update the existing record.
      $this->updatePaymentMethod($this->id, $form_state->cleanValues());
    } else {
      // It's an add operation, insert a new record.
      $this->insertPaymentMethod($form_state->cleanValues());
    }
    $form_state->setRedirect('payment_methods.listing');
  }

  protected function updatePaymentMethod($id, $values) {
    $payment_method_name = $values->getValue('payment_method_name');
    $payment_method_name_ar = $values->getValue('payment_method_name_ar');
    $payment_method_code = $values->getValue('payment_method_code');
    $payment_method_description = $values->getValue('payment_method_description');
    $payment_method_description_ar = $values->getValue('payment_method_description_ar');

    $connection = Database::getConnection();

    $connection->update('payment_methods')
      ->fields([
        'name' => $payment_method_name,
        'name_ar' => $payment_method_name_ar,
        'code' => $payment_method_code,
        'description' => $payment_method_description,
        'description_ar' => $payment_method_description_ar,
        'status' => '1',
        'changed' => time(),
        'changedBy' => \Drupal::currentUser()->id()
      ])
      ->condition('id', $id)
      ->execute();
  }

  protected function insertPaymentMethod($values)
  {
    $payment_method_name = $values->getValue('payment_method_name');
    $payment_method_name_ar = $values->getValue('payment_method_name_ar');
    $payment_method_code = $values->getValue('payment_method_code');
    $payment_method_description = $values->getValue('payment_method_description');
    $payment_method_description_ar = $values->getValue('payment_method_description_ar');


    $connection = Database::getConnection();
    $user_id = \Drupal::currentUser()->id();

    $connection->insert('payment_methods')
      ->fields([
        'name' => $payment_method_name,
        'name_ar' => $payment_method_name_ar,
        'code' => $payment_method_code,
        'description' => $payment_method_description,
        'description_ar' => $payment_method_description_ar,
        'createdBy' => $user_id,
        'status' => '1',
        'created' => time(),
      ])
      ->execute();

    \Drupal::messenger()->addStatus($this->t('Payment method @name (@code) has been created.', [
      '@name' => $payment_method_name,
      '@code' => $payment_method_code,
    ]));
  }


  private function loadPaymentMethodById($id)
  {
    return \Drupal::database()->select('payment_methods', 'n')
      ->fields('n', array('id', 'name', 'name_ar', 'code', 'description', 'description_ar'))
      ->where('id = :id', [':id' => $id])
      ->execute()->fetchAssoc();
  }
}

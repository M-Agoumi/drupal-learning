<?php

/**
 * @file
 * Contains \Drupal\configuration\Form\ConfigurationGeneraleAdminForm.
 */

namespace Drupal\configuration\Form;

use Drupal\Core\Cache\DatabaseBackend;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Driver\mysql\Connection;

/**
 * Implements an notification admin form.
 */
class ConfigurationGeneraleAdminForm extends ConfigFormBase {

  /**
   * {@inheritdoc}.
   */
  public function getFormID() {
    return 'configuration_generale_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return array('configuration_generale_generale.config');
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['limit_retailers'] = array(
      '#type' => 'textfield',
      '#title' => t('Limite des retailers'),
      '#default_value' => \Drupal::config('configuration_generale.settings')->get('limit_retailers'),
      '#required' => TRUE,
    );

    $form['submit'] = array(
    	'#type'    => 'submit',
    	'#value'   => "Enregistrer la configuration",
    );
    
    return $form; 

    //return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $configuration_generale_limit_retailers = $form_state->getValue('limit_retailers');

    \Drupal::configFactory()->getEditable('configuration_generale.settings')->set('limit_retailers', $configuration_generale_limit_retailers)->save();

    parent::submitForm($form, $form_state);
  }
  
  
}

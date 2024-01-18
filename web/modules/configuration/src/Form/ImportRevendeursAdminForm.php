<?php

/**
 * @file
 * Contains \Drupal\configuration\Form\ImportRevendeursAdminForm.
 */

namespace Drupal\configuration\Form;

use Drupal\Core\Cache\DatabaseBackend;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Driver\mysql\Connection;
use Drupal\Core\Url;
use Drupal\Core\Entity\Query\QueryFactory;
use \Drupal\node\Entity\Node;


/**
 * Implements a configuration admin form.
 */
class ImportRevendeursAdminForm extends ConfigFormBase {

  /**
   * {@inheritdoc}.
   */
  public function getFormID() {
    return 'config_import_revendeurs_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return array('config_rvd.config');
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['file'] = array(
      '#type' => 'managed_file',
      '#title' => t('Fichier Excel'),
      '#upload_location' => 'public://config_rvd/',
      '#default_value' => \Drupal::config('config_rvd.settings')->get('file'),
      '#file_extensions' => 'csv',
      '#description' => t('Format : CSV'),
      '#required' => TRUE,
      '#upload_validators' => array(
        'file_validate_extensions' => array('csv'),
        //'file_validate_size' => array(MAX_FILE_SIZE*1024*1024),
      ),
    );

    $form['submit'] = array(
      '#type'    => 'submit',
      '#value'   => "Mettre à jours les revendeurs",
    );

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state)
  {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $separator = ";";

    $config_rvd_file = $form_state->getValue('file');
    \Drupal::configFactory()->getEditable('config_rvd.settings')->set('file', $config_rvd_file)->save();

    $fid = $config_rvd_file[0];
    $file = \Drupal\file\Entity\File::load($fid);

    $uri = $file->getFileUri();
    $stream_wrapper_manager = \Drupal::service('stream_wrapper_manager')->getViaUri($uri);
    $path = $stream_wrapper_manager->realpath();

    // Extraire les données du fichier CSV
    ini_set('auto_detect_line_endings',TRUE);
    //$path = "/var/www/vhosts/mobiblanc_inwi-ma/sites/default/files/config_rvd/Base-operationnelle-Deploiement.csv";
    $csv = array_map("str_getcsv", file($path,FILE_SKIP_EMPTY_LINES));
    foreach ($csv as $i=>$row) {
      foreach($row as $y=>$el) {
        $row[$y] = str_replace("#REF!", "", $el);
      }
      $csv[$i] = $row;
    }
    // Remove first ligne of Titles
    unset($csv[0]);

    $postFields = array();

    foreach ($csv as $line)
    {
      $arr = explode($separator,$line[0]);

      $postFields['firstName'] = $arr[0];
      $postFields['lastName'] = $arr[1];
      $postFields['email'] = $arr[2];
      $postFields['msisdn'] = $arr[3];
      $postFields['city'] = $arr[4];
      $postFields['sexe'] = $arr[5];
      $postFields['password'] = $this->generatePassword();

      $ville_tid = \Drupal::entityQuery('taxonomy_term')->condition(
        'vid',
        'ville'
      )->condition('name', $postFields['city'])->execute();

      $ville_term = \Drupal\taxonomy\Entity\Term::load(reset($ville_tid));

      $villeCode = $ville_term->field_code->value;
      $postFields['city'] = $villeCode;

      $base_url = \Drupal::getContainer()->getParameter('be_base_url');
      $create_user = \Drupal::getContainer()->getParameter('be_create_user');
      $url = $base_url.$create_user;

      $response = curl($url, $postFields);

      $json_response = json_decode($response,TRUE);

      if(200 != $json_response['header']['code']){
        \Drupal::logger('import_revendeur_handler')->error('Response : ', $json_response);
      }else{
        \Drupal::logger('import_revendeur_handler')->debug('Response : ', $json_response);
        if($this->sendSms(
          "Votre mot de passe de l'application Saphir est : ".$postFields['password'],
          $postFields['msisdn']
        ))
          \Drupal::logger('import_revendeur_handler')->debug('SMS SENT');
        else
          \Drupal::logger('import_revendeur_handler')->error('SMS NOT SENT');
      }
    }

    unset($postFields['password']);

    \Drupal::logger('import_revendeur_handler')->debug('Request - Url : '.$url, $postFields);

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function poiMapping($data, $lang) {
    $date_ouverture = new \DateTime($data[7]);
    $node = array(
      "field_distributeur" => $data[0],
      "field_pos" => $data[1],
      "field_code" => $data[2],
      "field_categorie" => $data[3],
      "field_adresse" => $data[4],
      "field_quartier" => $lang=="fr" ? $data[5] : $data[5], // AR : 24
      "field_ville" => $lang=="fr" ? $data[6] : $data[6], // AR : 25
      "field_date_ouverture" => [
        $date_ouverture->format('Y-m-d\TH:i:s'),
      ],
      "field_type" => $data[8],
      "field_rgion" => $data[9],
      "field_chef_region" => $data[10],
      "field_superviseur" => $data[11],
      "field_ctc_superviseur" => $data[12],
      "field_type_d_amenagement_" => $data[13],
      "field_num_contact_1" => $data[14],
      "field_num_contact_2" => $data[15],
      "field_dealer" => $data[16],
      "field_adresse_mail" => $data[17],
      "field_longitude" => $data[18],
      "field_latitude" => $data[19],
      "field_paiement_payx" => $data[20],
      "field_type_cnx_operationnelle" => $data[21],
      "field_type_de_couverture" => $data[22],
      //"field_horaire_ouverture" => $data[23],
    );
    return $node;
  }

  private function generatePassword($length = 8) {
    $min = 'abcdefghijklmnopqrstuvwxyz';
    $maj = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $num = '0123456789';
    $countmin = mb_strlen($min);
    $countmaj = mb_strlen($maj);
    $countnum = mb_strlen($num);

    $result = '';

    for ($i = 0 ; $i < $length/2-1; $i++) {
      $index = rand(0, $countmin - 1);
      $result .= mb_substr($min, $index, 1);
    }

    for ($i = 0; $i < $length/2-1; $i++) {
      $index = rand(0, $countmaj - 1);
      $result .= mb_substr($maj, $index, 1);
    }

    for ($i = 0; $i < 2; $i++) {
      $index = rand(0, $countnum - 1);
      $result .= mb_substr($num, $index, 1);
    }

    return $result;
  }

  private function sendSms($messageBody,$msisdn){
    $response = array();

    $postfields = array(
      "to"=>$msisdn,
      "message"=>$messageBody,
    );

    $base_url = \Drupal::getContainer()->getParameter('be_base_url');
    $send_sms = \Drupal::getContainer()->getParameter('be_send_sms');
    $url = $base_url.$send_sms;

    for ($i=0; $i <= 10; $i++) {
      $response = json_decode( curl($url,$postfields),TRUE);

      if(isset($response["header"]["code"]) && 200 == $response["header"]["code"]) {
        break;
      }

      sleep(1);
    }
    if(200 == $response["header"]["code"])
      return true;
    return false;
  }

}
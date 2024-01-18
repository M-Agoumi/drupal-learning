<?php

namespace Drupal\notification\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class notificationController extends ControllerBase {

  /**
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function newNotification(Request $request){

    $pAssigned_by = $request->request->get('assigned_by');
    $pAssigned_to = $request->request->get('assigned_to');
    $pAction      = $request->request->get('action');

    \Drupal::logger('new_notification_handler')->info(
      "[-Get Parameters : ".json_encode($request->query->all())."-Post Parameters : ".
      json_encode($request->request->all()));

    /*
     * Verification des parametres requis
     */
    if(
      !empty($pAssigned_by) &&
      !empty($pAssigned_to) &&
      !empty($pAction)
    )
    {
      $idTaxonomyNotificationAction =
        self::getNotificationActionTaxonomy($pAction);

      if(0 != $idTaxonomyNotificationAction){

      /*
       * Send from backoffice
       */
      $pRole        = $request->request->get('role');
      $params = self::getAllParams($request);

      //Par default la date de notification est la date actuelle
      //$dateNow      = date('Y-m-d\TH:i:s');

      $data = array(
        'type' => 'notification',
        'title' => "Nouvelle notification $pAction - by : $pAssigned_by",
        'field_assigned_by' => $pAssigned_by,
        'field_assigned_to' => $pAssigned_to,
        //'field_date' => $dateNow,
        'field_action' => $idTaxonomyNotificationAction,
      );


      if ("backoffice" == $pRole) {
        $email = $request->request->get('email');
        $isSent = self::notifyRevendeur($email, $pAction, $pAssigned_to, $params);
        $data['field_isread'] = TRUE;
      }
      else {
        $isSent = self::notifyRegional($pAction, $pAssigned_by, $pAssigned_to);
      }

      $node = \Drupal::entityManager()
        ->getStorage('node')
        ->create($data);
      $node->save();

      if($isSent)
          $response = new JsonResponse(array("status" => true));
      else
        $response =  new JsonResponse(array("status" => false, "message" => "Message not sent"));

      }else{
        $response =  new JsonResponse(array(
          "status" => false,
          "message" => "Action not found"
        ));
      }
    }else{
      $response =  new JsonResponse(array(
        "status" => false,
        "message" => "missing params"
      ));
    }

    \Drupal::logger('new_notification_handler')->debug(
      "Response : ". $response);

    return $response;
  }

  public function getCountUnreadMessage(){

    $cities = null;
    $current_user = \Drupal::currentUser();
    $account = User::load($current_user->id());
    if($account->hasRole("regional")){
      $cities = getCurrentRegionalCities();
    }

    $query = \Drupal::entityQuery('node')
      ->condition('field_isread', false)
      ->condition('type', 'notification');

    if($cities)
      $query->condition('field_assigned_to', $cities,'IN');

    $result = $query->count()->execute();
    return new JsonResponse(array(
      "count" => $result,
    ));
  }

  /**
   * @param $action
   *
   * @return int|null|string
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  private function getNotificationActionTaxonomy($action){
    $properties['vid'] = "action_notification";

    if (!empty($action)) {
      $properties['field_action'] = $action;
    }

    $terms = \Drupal::entityManager()
                ->getStorage('taxonomy_term')
                ->loadByProperties($properties);
    $term = reset($terms);

    return !empty($term) ? $term->id() : 0;
  }

  /**
   * @param $pAction
   * @param $pAssigned_by
   * @param $pAssigned_to
   *
   * @return bool
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  private function notifyRegional($pAction, $pAssigned_by, $pAssigned_to){
    $idTaxonomyNotificationAction =
      self::getNotificationActionTaxonomy($pAction);

    $aAction = self::getActionById($idTaxonomyNotificationAction);
    $messageTitle = $aAction["title"];
    $messageBody = $aAction["message"];

    $cityCode = $pAssigned_to;
    $email = self::getRegionalMailByCity($cityCode);

    return self::sendEmail($messageTitle, $messageBody, $email, $pAssigned_by, array("1" => $pAssigned_by));
  }

  /**
   * @param $pAction
   * @param $pAssigned_to
   * @param $formattedParams
   *
   * @return bool
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  private function notifyRevendeur($email, $pAction, $pAssigned_to, $formattedParams){
    $idTaxonomyNotificationAction =
      self::getNotificationActionTaxonomy($pAction);

    $aAction = self::getActionById($idTaxonomyNotificationAction);
    $messageTitle = $aAction["title"];
    $messageSubject = $aAction["message"];

    $msisdnRevendeur = $pAssigned_to;

    $isSentMail =  self::sendEmail($messageTitle,$messageSubject,$email, null, $formattedParams);
    $isSentSms = self::sendSms($messageSubject,$msisdnRevendeur, $formattedParams);

    return ($isSentMail && $isSentSms);
  }

  /**
   * @param $idTaxonomyNotificationAction
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  private function getActionById($idTaxonomyNotificationAction){
    $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($idTaxonomyNotificationAction);

    return array(
      "title"=>$term->field_mail->value,
      "message"=>strip_tags($term->field_body->value),
    );
  }

  /**
   * @param $messageTitle
   * @param $messageBody
   * @param $email
   * @param $pAssigned_by
   *
   * @return bool
   */
  private function sendEmail($messageTitle,$messageBody,$email,$pAssigned_by,$params){

    $from = \Drupal::getContainer()->getParameter('send_email_from');

    foreach ($params as $key => $param){
      $messageBody = str_replace("{{param_$key}}",$param,$messageBody);
    }

    $postfields = array(
      "to"=>$email,
      "from"=>$from,
      "subject"=>$messageTitle,
      "body"=>$messageBody,
    );

    $base_url = \Drupal::getContainer()->getParameter('be_base_url');
    $send_email = \Drupal::getContainer()->getParameter('be_send_email');
    $url = $base_url.$send_email;

    $response = json_decode( curl($url,$postfields),TRUE);

    if(isset($response["header"]["code"]) && 200 == $response["header"]["code"] )
      return true;
    return false;
  }

  /**
   * @param $messageBody
   * @param $msisdn
   * @param $params array
   *
   * @return bool
   */
  private function sendSms($messageBody,$msisdn,$params){
    $response = array();

    foreach ($params as $key => $param){
      $messageBody = str_replace("{{param_$key}}",$param,$messageBody);
    }

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

  function getRegionalMailByCity($cityCode) {
    //Load ville
    $ville_tid = \Drupal::entityQuery('taxonomy_term')
      ->condition('vid', 'ville')
      ->condition('field_code', $cityCode)
      ->execute();

    $ville_term = \Drupal\taxonomy\Entity\Term::load(reset($ville_tid));

    //Load region
    $region_tid = \Drupal::entityQuery('taxonomy_term')->condition(
      'vid',
      'region'
    )->condition(
      'tid',
      $ville_term->get("field_region")[0]->target_id
    )->execute();

    $region_term = \Drupal\taxonomy\Entity\Term::load(reset($region_tid));


    $ids = \Drupal::entityQuery('user')
      ->condition('status', 1)
      ->condition('roles', 'regional')
      ->execute();

    foreach ($ids as $userId) {
      $current_user = User::load($userId);

      $user_region_id = $current_user->get(
        "field_region"
      )[0]->target_id; //

      if ($user_region_id == $region_term->id()) {
        return $current_user->getEmail();
      }
    }
    return NULL;
  }

  /**
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return array
   */
  function getAllParams(Request $request){
    $params = $request->request->all();
    unset(
      $params['assigned_by'],
      $params['assigned_to'],
      $params['action'],
      $params['role'],
      $params['email']
    );

    $formattedParams = array();
    if(!empty($params))
    {
      $i = 1;
      foreach ($params as $pParam)
      {
        $formattedParams[$i] = $pParam;
        $i++;
      }
    }
    return $formattedParams;
  }
}

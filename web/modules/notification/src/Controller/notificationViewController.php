<?php

namespace Drupal\notification\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\dealer\dao\BackendDAO;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\taxonomy\Entity\Term;

class notificationViewController extends ControllerBase {


  public static $BackendDaoCon;

  public function __construct() {
    self::$BackendDaoCon = new BackendDAO();
  }

  /**
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function allNotifications(){

    $header = [
      'Source de notification' ,
      'Message' ,
      'Action' ,
      'Date' ,
      'Validation' ,
    ];

    $actions = ['limit_reached',];

    $idsNotificationActionTaxonomy = array();
    foreach ($actions as $action)
    {
      array_push($idsNotificationActionTaxonomy,self::getNotificationActionTaxonomy($action));
    }

    $cities = null;
    $current_user = \Drupal::currentUser();
    $account = User::load($current_user->id());
    if($account->hasRole("regional")){
      $cities = getCurrentRegionalCities();
    }

    $query = \Drupal::entityQuery('node')
      ->condition('type', 'notification')
      ->condition('field_action', $idsNotificationActionTaxonomy,'IN');

    if($cities)
      $query->condition('field_assigned_to', $cities,'IN');

    $nids = $query->execute();

    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadMultiple($nids);

    $row = [];

    foreach ($nodes as $node)
    {
      $userFromBE = self::$BackendDaoCon->getUserIdByUsername($node->field_assigned_by->value);

      $notificationUrl = $userFromBE ?
        Url::fromRoute('dealer.retailers.validation', array("id_revendeur"=>$userFromBE->id), array('absolute' => TRUE))->toString()
        : "-"
      ;

      /*
       * Si l'utilisateur n'existe pas marquer la notification comme lu
       */
      if(!$userFromBE)
      {
        $node->set('field_isread', 1);
        $node->save();
      }

      $action = Term::load($node->field_action->entity->id());
      array_push(
        $row,
        [
          'message' => $action->field_mail->value,
          'action' => $action->getName(),
          'assigned_by' => $node->field_assigned_by->value,
          'assigned_to' => $node->field_assigned_to->value,
          'date' => date('Y-m-d H:i', strtotime($node->field_date->value)),
          'isread' => $node->field_isread->value,
          'validationUrl' => $notificationUrl,
        ]
      );

    }
    /*
     * Generate the table.
     */
    $build = [
      '#theme' => 'list-notification',
      '#header' => $header,
      '#rows' => $row,
    ];


    return $build;
  }


  /**
   * @param $action
   *
   * @return int|null|string
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
}

<?php

namespace Drupal\dealer\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dealer\dao\BackendDAO;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\user\Entity\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Zend\Diactoros\Response\JsonResponse;

class listDealerController extends ControllerBase {

  const VERIFIED = "VERIFIED";

  //REVENDEUR
  const UNVERIFIED_SMS = "UNVERIFIED_SMS";

  const UNVERIFIED_CIN = "UNVERIFIED_CIN";

  const UNVERIFIED_ACCOUNT = "UNVERIFIED_ACCOUNT";

  //RETAILER
  const UNVERIFIED = "UNVERIFIED";

  const DUPLICATED = "DUPLICATED";

  const GMAPS = "https://www.google.com/maps/search/?api=1&query=";

  public static $BackendDaoCon;

  public function __construct() {
    self::$BackendDaoCon = new BackendDAO();
  }

  /*
   * Afficahge
   */
  /**
   * Affichage de la liste des revendeurs validés
   *
   * @return array
   */
  public function listRevendeur() {

    $header = [
      "Nom",
      "Prenom",
      "msisdn",
      "email",
      'id validation',
      "Nombre retailers",
      "action",
    ];
    /*
     * If the Connected user is a regional, get the city to send it in param.
     */
    $cities = null;
    $current_user = \Drupal::currentUser();
    $account = User::load($current_user->id());
    if($account->hasRole("regional")){
      $cities = getCurrentRegionalCities();
    }
    $dealers = self::$BackendDaoCon->getVerifiedRevendeurs($cities);

    /*
     * Build an array to show it in the datatable.
     */
    $rows = [];
    foreach ($dealers as $dealer) {
      array_push(
        $rows,
        [
          'last_name' => $dealer->last_name,
          'first_name' => $dealer->first_name,
          'msisdn' => $dealer->msisdn,
          'email' => $dealer->email,
          'id_validation' => '123123',
          'n_retailers' => self::$BackendDaoCon->countVerifiedRetailers($dealer->id),
          'action' => "/admin/revendeurs/get/" . $dealer->id,
        ]
      );
    }

    /*
     * Generate the table.
     */
    $build = [
      '#theme' => 'list-dealer',
      '#header' => $header,
      '#rows' => $rows,
    ];

    return $build;
  }


  /**
   * details d'un revendeur validé : Affichage des Données personnelles + retailers
   *
   * @param $id_revendeur
   *
   * @return array
   */
  public function detailRevendeur($id_revendeur) {

    /*
     * Information personnel
     */
    $userFromBE = self::$BackendDaoCon->getInfoRevendeur($id_revendeur);

    $cinPath = downloadFile($userFromBE->cin_file);

    $userInfos = [
      "id" => $id_revendeur,
      "last_name" => $userFromBE->last_name,
      "first_name" => $userFromBE->first_name,
      "msisdn" => $userFromBE->msisdn,
      "email" => $userFromBE->email,
      "cin" => $userFromBE->cin,
      "cin_file" => $cinPath ? $cinPath : "",
      "address" => $userFromBE->address,
      "status" => $userFromBE->status,
      "created_at" => $userFromBE->created_at,
      "updated_at" => $userFromBE->updated_at,
    ];

    /*
     * Get verified retailers of a dealer
     */
    $retailersBE = self::$BackendDaoCon->getVerifiedRetailers($id_revendeur, self::VERIFIED);

    /*
     * build response
     */
    $retailers = [];
    foreach ($retailersBE as $retailer) {
      $maps_link = "-";
      if((int)$retailer->longitude != 0 && (int)$retailer->latitude != 0)
        $maps_link = self::GMAPS . $retailer->latitude.",".$retailer->longitude;
      array_push(
        $retailers,
        [
          'last_name' => $retailer->lastName,
          'first_name' => $retailer->firstName,
          'msisdn' => $retailer->msisdn,
          'adresse' => $retailer->adresse,
          'maps_link' => $maps_link,
        ]
      );
    }

    /*
     * Table header
     */
    $header = [
      "Nom",
      "Prenom",
      "Numero de téléphone",
      "Adresse",
      "Coordonnées GPS",
    ];
    //Operations
    $operationsHeaders = ['Numéro Retailler', 'Valeur', 'Date', 'Action', 'Type', 'Commentaire', ];
    $response = self::$BackendDaoCon->getDashboardSalesAndPurchases($id_revendeur);
    $history = $response && 200 == $response['header']['code'] ? $response['body']['history'] : [];
    $operations = [];
    foreach ($history as $order) {
      $operations[] = [
        'numero_retailler' => $order['retailer']['msisdn'],
        'montant' => $order['amount'],
        'date' => $order['date'],
        'action' => ucfirst(strtolower($order['action'])),
        'type' => ucfirst(strtolower($order['type'])),
        'comment' => array_key_exists('comment', $order) ? $order['comment'] : '-',
      ];
    }
      /*
       * Generate the table.
       */
    $build = [
      '#theme' => 'list-retailer',
      '#header' => $header,
      '#retailers' => $retailers,
      '#userInfos' => $userInfos,
      '#operationsHeaders' => $operationsHeaders,
      '#operations' => $operations,
    ];
    echo '<pre>';
    var_dump($build);
    die();
    return $build;
  }

  /*
   * Validation
   */
  /**
   * Liste des revendeurs à valider
   *
   * @return array
   */
  public function validationRevendeur() {
    $header = [
      "Nom",
      "Prenom",
      "Msisdn",
      "Email",
      "Nb retailers validés",
      "Nb retailers à valider",
      "Action",
    ];

    $cities = null;
    $current_user = \Drupal::currentUser();
    $account = User::load($current_user->id());
    if($account->hasRole("regional")){
      $cities = getCurrentRegionalCities();
    }

    $dealers = self::$BackendDaoCon->getUnverifiedRevendeurs($cities);

    $rows = [];
    foreach ($dealers as $dealer) {
      array_push(
        $rows,
        [
          'last_name' => $dealer->last_name,
          'first_name' => $dealer->first_name,
          'msisdn' => $dealer->msisdn,
          'email' => $dealer->email,
          'status' => $dealer->status,
          'n_retailers' => self::$BackendDaoCon->countVerifiedRetailers($dealer->id),
          'n_u_retailers' => self::$BackendDaoCon->countUnverifiedRetailers($dealer->id),
          'action' => "/admin/revendeurs/validate/" . $dealer->id,
        ]
      );
    }

    $build = [
      '#theme' => 'validate-dealer',
      '#header' => $header,
      '#rows' => $rows,
    ];

    return $build;
  }

  /**
   * Données personnelles + Liste des retailers d'un revendeur à valider
   *
   * @param $id_revendeur
   *
   * @return array
   */
  public function validationRetailers($id_revendeur)
  {
    /*
     * Get personnel informations
     */
    $userFromBE = self::$BackendDaoCon->getInfoRevendeur($id_revendeur);

    $cinPath = downloadFile($userFromBE->cin_file);

    $this->setIsReadNotification($userFromBE->msisdn);

    /*
     * Build infos to show in personnel informations
     */
    $userInfos = [
      "id" => $userFromBE->id,
      "last_name" => $userFromBE->last_name,
      "first_name" => $userFromBE->first_name,
      "msisdn" => $userFromBE->msisdn,
      "email" => $userFromBE->email,
      "cin" => $userFromBE->cin,
      "cin_file" => $cinPath ? $cinPath : "",
      "address" => $userFromBE->address,
      "status" => $userFromBE->status,
      "created_at" => $userFromBE->created_at,
      "updated_at" => $userFromBE->updated_at,
    ];


    /*
     * Get unverified retailers of a dealer
     */
    $retailersBE = self::$BackendDaoCon->getUnverifiedRetailers($id_revendeur);

    /*
     * build retailers infos to show in datatable
     */
    $retailers = [];
    foreach ($retailersBE as $retailer) {
      $maps_link = "-";
      if((int)$retailer->longitude != 0 && (int)$retailer->latitude != 0)
        $maps_link = self::GMAPS . $retailer->latitude.",".$retailer->longitude;
      array_push(
        $retailers,
        [
          'id' => $retailer->id,
          'last_name' => $retailer->lastName,
          'first_name' => $retailer->firstName,
          'msisdn' => $retailer->msisdn,
          'adresse' => $retailer->adresse,
          'status' => $retailer->status,
          'maps_link' => $maps_link,
        ]
      );
    }


    /*
     * datatable header
     */
    $header = [
      "Nom",
      "Prenom",
      "Numero de téléphone",
      "Adresse",
      "Coordonnées GPS",
      "Action",
    ];


    // Generate the table.
    $build = [
      '#theme' => 'validate-retailers',
      '#header' => $header,
      '#retailers' => $retailers,
      '#userInfos' => $userInfos,
    ];

    return $build;
  }

  /**
   * Requete d'activation d'un retailer
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function retailerActivate(Request $request) {
    $id_revendeur = $request->query->get('id_revendeur');
    $id_retailer = $request->query->get('id_retailer');
    try{
      self::$BackendDaoCon->validateRetailer($id_revendeur,$id_retailer);
    }catch (\Exception $e){
      return new Response(array(
        "status" => "201",
        "message" => $e->getMessage(),
      ));
    }
    return new Response("200");
  }

  /**
   * Requete de déclinaison d'un retailer
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function retailerDecline(Request $request) {
    $id_revendeur = $request->query->get('id_revendeur');
    $id_retailer = $request->query->get('id_retailer');
    try{
      self::$BackendDaoCon->declineRetailer($id_revendeur,$id_retailer);
    }catch (\Exception $e){
      return new Response(array(
        "status" => "201",
        "message" => $e->getMessage(),
      ));
    }

    return new Response("200");
  }

  /**
   * Valider les informations d'un revendeur
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function acceptRevendeur(Request $request) {

    $id_revendeur = $request->query->get('id_revendeur');
    try{
      self::$BackendDaoCon->acceptRevendeur($id_revendeur);
    }catch (\Exception $e){
      return new Response(array(
        "status" => "201",
        "message" => $e->getMessage(),
      ));
    }
    return new Response("200");
  }

  /**
   * Valider les informations d'un revendeur
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function countActivatedRetailers(Request $request) {

    $id_revendeur = $request->query->get('id_revendeur');

    $activatedRetailers = self::$BackendDaoCon->countVerifiedRetailers($id_revendeur);

    $settings = \Drupal::config('configuration_generale.settings');
    $limit_retailers = $settings->get('limit_retailers');

    if((int)$activatedRetailers >= (int)$limit_retailers)
      return new Response("200");
    return new Response("201");
  }

  /**
   * Valider les informations d'un revendeur
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function declineRevendeur(Request $request) {

    $id_revendeur = $request->query->get('id_revendeur');
    try{
      self::$BackendDaoCon->declineRevendeur($id_revendeur);
    }catch (\Exception $e){
      return new Response(array(
        "status" => "201",
        "message" => $e->getMessage(),
      ));
    }
    return new Response("200");
  }

  /**
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function dealerDashboard(Request $request) {
    $id_revendeur = $request->query->get('id_revendeur');

    $response = self::$BackendDaoCon->getDashboardSalesAndPurchases($id_revendeur);

    $dateFormat = 'Y-m-d H:i:s';

    $rechargeVente = $rechargeAchat = $factureVente = $factureAchat = $scratchAchat = array();
    $months = array();
    if($response){
      if(200 == $response['header']['code'])
      {
        $history = $response['body']['history'];

        foreach ($history as $order)
        {
          $action = $order['action'];
          $type = $order['type'];
          $amount = $order['amount'];
          $date = \DateTime::createFromFormat($dateFormat, $order['date']);


          $switchVal = str_replace(' ', '', strtolower($action));
          $switchVal.=" ";
          $switchVal .= str_replace(' ', '', strtolower($type));


          $monthNum = $date->format('m');
          $monthName = date("F", mktime(0, 0, 0, $monthNum, 10));
          $months[$monthName] = $monthName;

          switch ($switchVal){
            case "vente recharge":
              if(!isset($rechargeVente[$monthName]))
                $rechargeVente[$monthName] = 0;
              $rechargeVente[$monthName] += $amount;
              break;
            case "vente facture":
              if(!isset($factureVente[$monthName]))
                $factureVente[$monthName] = 0;
              $factureVente[$monthName] += $amount;
              break;
            case "achat recharge":
              if(!isset($rechargeAchat[$monthName]))
                $rechargeAchat[$monthName] = 0;
              $rechargeAchat[$monthName] += $amount;
              break;
            case "achat facture":
              if(!isset($factureAchat[$monthName]))
                $factureAchat[$monthName] = 0;
              $factureAchat[$monthName] += $amount;
              break;
            case "achat scratch":
              if(!isset($scratchAchat[$monthName]))
                $scratchAchat[$monthName] = 0;
              $scratchAchat[$monthName] += $amount;
              break;
          }

        }

      }
    }

    $response = formatDashboardResponse($factureAchat, $factureVente, $rechargeAchat, $rechargeVente, $rechargeAchat, $months);

    return new Response($response);
  }


  /**
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function duplicatedRetailer(Request $request) {
    $retailer_msisdn = $request->query->get('msisdn');

    $listVendeursOfDuplicatedUser = json_decode(self::$BackendDaoCon->getRevendeurOfDuplicatedRetailer($retailer_msisdn));

    $messageString = "";
    if(count($listVendeursOfDuplicatedUser)>1){
      $messageString = "Attention, ce retailer est affecté au revendeurs suivants : " ;
      foreach ($listVendeursOfDuplicatedUser as $vendeur)
      {
        $messageString .= $vendeur->first_name ." ". $vendeur->last_name .' ('.$vendeur->msisdn.')'.", ";
      }
    }


    return new Response($messageString);
  }

  private function setIsReadNotification($msisdn){
    $actions = ['limit_reached',];

    $idsNotificationActionTaxonomy = array();
    foreach ($actions as $action)
    {
      array_push($idsNotificationActionTaxonomy,self::getNotificationActionTaxonomy($action));
    }

    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'notification')
      ->condition('field_action', $idsNotificationActionTaxonomy,'IN')
      ->condition('field_assigned_by', $msisdn,'=')
      ->execute();

    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadMultiple($nids);

    foreach ($nodes as $node)
    {
      $node->set('field_isread', 1);
      $node->save();
    }
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

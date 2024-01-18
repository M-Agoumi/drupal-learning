<?php
namespace Drupal\dealer\dao;
/**
 * Created by PhpStorm.
 * User: zakaria
 * Date: 01/11/2017
 * Time: 09:29
 */

class BackendDAO {

    const VERIFIED = "VERIFIED";

    //REVENDEUR
    const UNVERIFIED_SMS = "UNVERIFIED_SMS";

    const UNVERIFIED_CIN = "UNVERIFIED_CIN";

    const UNVERIFIED_ACCOUNT = "UNVERIFIED_ACCOUNT";

    //RETAILER
    const UNVERIFIED = "UNVERIFIED";

    const DUPLICATED = "DUPLICATED";

    public $backendCon;

    public function __construct() {
      $this->backendCon = \Drupal\Core\Database\Database::getConnection(
        'default',
        'backend'
      );
    }

  /**
   * La liste des revendeurs validés
   *
   * @param $city
   *
   * @return mixed
   */
    public function getVerifiedRevendeurs($cities = null){
      $query = $this->backendCon
        ->select('user', 'user')
        ->fields('user')
        ->where('user.status = :status AND user.enabled = :enabled', [":status" => self::VERIFIED, ":enabled" => 1]);

      if($cities){
        $query->condition('user.	city', $cities, 'IN');
      }

      return $query->execute();
    }

  /**
   * Données personnelles d'un revendeur
   *
   * @param $id_revendeur
   *
   * @return mixed
   */
  public function getInfoRevendeur($id_revendeur){
    $query = $this->backendCon
      ->select('user', 'user')
      ->fields('user')
      ->where('user.id = :id_user', [':id_user' => $id_revendeur]);

    return $query->execute()->fetchObject();
  }

  /**
   * Les retailers d'un revendeur.
   *
   * @param $id_revendeur
   *
   * @return mixed
   */
  public function getVerifiedRetailers($id_revendeur){
    $query = $this->backendCon
      ->select('retailer', 'retailer')
      ->fields('retailer')
      ->where('retailer.user_id = :id_user AND retailer.status = :status AND retailer.enabled = :enabled AND retailer.deleted = :deleted', [':id_user' => $id_revendeur, ':status' => self::VERIFIED, ':enabled' => 1, ':deleted' => 0]);

    return $query->execute();
  }

  /**
   * Les retailers d'un revendeur.
   *
   * @param $id_revendeur
   *
   * @return mixed
   */
  public function getUnverifiedRetailers($id_revendeur){
    $query = $this->backendCon
      ->select('retailer', 'retailer')
      ->fields('retailer')
      ->where('retailer.user_id = :id_user AND retailer.status != :status AND retailer.enabled = :enabled AND retailer.deleted = :deleted', [':id_user' => $id_revendeur, ':status' => self::VERIFIED, ':enabled' => 1, ':deleted' => 0]);

    return $query->execute();
  }

  /**
   * Get Unverified Revendeurs (les revendeurs avec le statut UNVERIFIED_ACCOUNT + les revendeurs avec le status VERIFIED et qu'on des retailers UNVERIFIED)
   *
   * @param $city
   *
   * @return mixed
   */
  public function getUnverifiedRevendeurs($cities){
    /*
     * id des revendeurs avec un retailer non verifié
     */
    $rrnv = array();
    $retailersQuery = $this->backendCon
      ->select('retailer', 'retailer')
      ->fields('retailer')
      ->where('retailer.status != :status AND retailer.enabled = :enabled AND retailer.deleted = :deleted', [":status" => self::VERIFIED, ":enabled" => 1, ':deleted' => 0]);

    $retailers = $retailersQuery->execute();
    foreach ($retailers as $retailer) {
      $rrnv[$retailer->user_id] = $retailer->user_id;
    }

    /*
     *    List des vendeurs
     */
    $query =  $this->backendCon
      ->select('user', 'user')
      ->fields('user');

    /*
     * Afficher les utilisateur avec status enabled
     */

    $query->condition('user.	enabled', 1, '=');

    /*
     *  Si l'utilisateur est un regional Filter les vendeurs par ville
     */
    if($cities){
      $query->condition('user.	city', $cities, 'IN');
    }

    /*
     * Si il y a des retailers non verifié Ajouter les vendeurs de cette liste
     */
    if(empty($rrnv))
    {
      $group = $query->orConditionGroup()
        ->condition('user.status', self::UNVERIFIED_ACCOUNT, '=');
    }
    else
    {
      $group = $query->orConditionGroup()
        ->condition('user.id', $rrnv, 'IN')
        ->condition('user.status', self::UNVERIFIED_ACCOUNT, '=');
    }

    return $query->condition($group)->execute();
  }

  /**
   * @param $id_revendeur
   * @param $id_retailer
   */
  public function validateRetailer($id_revendeur, $id_retailer){

    $query = $this->backendCon->update('retailer');
    $query->fields(
      [
        'status' => self::DUPLICATED,
        'enabled' => 0,
      ]
    );
    $query->condition('retailer.id', $id_retailer);
    $query->execute();

    $query = $this->backendCon->update('retailer');
    $query->fields(
      [
        'status' => self::VERIFIED,
        'enabled' => 1,
      ]
    );
    $query->condition('retailer.user_id', $id_revendeur);
    $query->condition('retailer.id', $id_retailer);
    $query->execute();
  }

  /**
   * @param $id_revendeur
   * @param $id_retailer
   */
  public function declineRetailer($id_revendeur, $id_retailer){
    $query = $this->backendCon->update('retailer');
    $query->fields(
      [
        'enabled' => 0,
      ]
    );
    $query->condition('retailer.user_id', $id_revendeur);
    $query->condition('retailer.id', $id_retailer);
    $query->execute();
  }

  /**
   * @param $id_revendeur
   */
  public function acceptRevendeur($id_revendeur){
    $query = $this->backendCon->update('user');
    $query->fields(
      [
        'status' => self::VERIFIED,
      ]
    );
    $query->condition('user.id', $id_revendeur);
    $query->execute();
  }

  /**
   * @param $id_revendeur
   */
  public function declineRevendeur($id_revendeur){
    $query = $this->backendCon->update('user');
    $query->fields(
      [
        'enabled' => 0,
      ]
    );
    $query->condition('user.id', $id_revendeur);
    $query->execute();
  }

  /**
   * @param $id_revendeur
   *
   * @return int
   */
  public function countVerifiedRetailers($id_revendeur) {
    $results = $this->backendCon
      ->select('retailer', 'retailer')
      ->fields('retailer')
      ->where(
        'retailer.status = :status AND retailer.enabled = :enabled AND retailer.user_id = :user_id AND retailer.deleted = :deleted',
        [
          ":status" => self::VERIFIED,
          ":enabled" => 1,
          ":user_id" => $id_revendeur,
          ':deleted' => 0
        ]
      )->execute()->fetchAll();;

    return count($results);
  }

  /**
   * @param $id_revendeur
   *
   * @return int
   */
  public function countUnverifiedRetailers($id_revendeur) {
    $results = $this->backendCon
      ->select('retailer', 'retailer')
      ->fields('retailer')
      ->where(
        '(retailer.status = :unverified OR retailer.status = :duplicated) AND retailer.enabled = :enabled AND retailer.user_id = :user_id AND retailer.deleted = :deleted',
        [
          ":unverified" => self::UNVERIFIED,
          ":duplicated" => self::DUPLICATED,
          ":enabled" => 1,
          ':deleted' => 0,
          ":user_id" => $id_revendeur,
        ]
      )->execute()->fetchAll();;

    return count($results);
  }

  /**
   * @param $id_revendeur
   *
   * @return mixed
   */
  public function getDashboardSales($id_revendeur, $beginDate, $endDate) {
    $results = $this->backendCon
      ->select('sale', 'sale')
      ->fields('sale')
      ->where('sale.user_id = :user_id
                       AND DATE(sale.created_at) between :beginDate AND :endDate',
        [
          ":user_id" => $id_revendeur,
          ":beginDate" => $beginDate,
          ":endDate" => $endDate,
        ]
      )->execute()->fetchAll();;
    return json_encode($results);
  }

  /**
   * @param $id_revendeur
   *
   * @return mixed
   */
  public function getDashboardPurchases($id_revendeur, $beginDate, $endDate) {
    $results = $this->backendCon
      ->select('purchase', 'purchase')
      ->fields('purchase')
      ->where('purchase.user_id = :user_id
                       AND DATE(purchase.created_at) between :beginDate AND :endDate',
        [
          ":user_id" => $id_revendeur,
          ":beginDate" => $beginDate,
          ":endDate" => $endDate,
        ]
      )->execute()->fetchAll();;

    return json_encode($results);
  }

  public function getDashboardSalesAndPurchases($id_revendeur){
    $base_url = \Drupal::getContainer()->getParameter('be_base_url');
    $orders_history = \Drupal::getContainer()->getParameter('be_orders_history');
    $url = $base_url.$orders_history;

    $beWS = str_replace("{{id_revendeur}}",$id_revendeur,$url);

    $response = curl($beWS,array(),"GET");
    return $response ? \json_decode($response, true) : [];
  }

  public function getRevendeurOfDuplicatedRetailer($msisdn){

    $rvdrs = array();
    $retailersQuery = $this->backendCon
      ->select('retailer', 'r')
      ->fields('r', ['user_id'])
      ->where('r.msisdn = :msisdn 
                      AND r.enabled = :enabled 
                      AND r.deleted = :deleted',
                      [
                        ":msisdn" => $msisdn,
                        ":enabled" => 1,
                        ':deleted' => 0,
                      ]
              );
    $retailers = $retailersQuery->execute();

    foreach ($retailers as $retailer) {
      $rvdrs[$retailer->user_id] = $retailer->user_id;
    }

    $results = $this->backendCon
      ->select('user', 'u')
      ->fields('u')
      ->condition('u.id', $rvdrs, 'IN')
      ->execute()->fetchAll();;

    return json_encode($results);
  }

  public function getUserIdByUsername($msisdn){
    $query = $this->backendCon
      ->select('user', 'user')
      ->fields('user')
      ->where('user.msisdn = :msisdn', [':msisdn' => $msisdn]);

    return $query->execute()->fetchObject();
  }
}
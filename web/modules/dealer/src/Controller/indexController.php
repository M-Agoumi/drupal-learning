<?php

namespace Drupal\dealer\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\block_content\Entity\BlockContent;

class indexController extends ControllerBase {

  /*
   * Afficahge
   */

  /**
   * Affichage de la page d'accueil
   *
   * @return array
   */
  public function homePage() {

    $items = array();

    $build = array(
      '#theme' => 'homepage',
      '#items' => $items
    );

    return $build;

  }

}

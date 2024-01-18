<?php

namespace Drupal\api\Controller;

use Drupal\api\Service\ApiDao;
use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\Entity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class apiController extends ControllerBase
{

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function configuration()
    {
        $settings = \Drupal::config('configuration_generale.settings');
        $limit_retailers = $settings->get('limit_retailers');

        $response = array("limitRetailers" => (int)$limit_retailers);

        \Drupal::logger('configuration_handler')->debug('Response : ', $response);

        return new JsonResponse($response);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function listCities()
    {

        $query = \Drupal::entityQuery('taxonomy_term');
        $query->condition('vid', "ville");
        $tids = $query->execute();

        $cities = array();

        foreach ($tids as $key => $val) {
            $taxonomy_term = \Drupal\taxonomy\Entity\Term::load($val);

            array_push($cities,
                array(
                    "code" => $taxonomy_term->field_code->value,
                    "name" => $taxonomy_term->name->value,
                )
            );
        }

        $response = array("cities" => $cities);

        \Drupal::logger('list_cities_handler')->debug('Response : ', $response);

        return new JsonResponse($response);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function catalogue()
    {

        $query = \Drupal::entityQuery('taxonomy_term');
        $query->condition('vid', "catalogue");
        $tids = $query->execute();

        $catalogue = array();

        foreach ($tids as $key => $val) {
            $taxonomy_term = \Drupal\taxonomy\Entity\Term::load($val);
            $types = $taxonomy_term->get('field_type')->getValue();
            foreach ($types as $type) {
                $type = $type['value'];
                $amount = $taxonomy_term->field_montant->value;
                $title = $taxonomy_term->name->value;
                try {
                    if (!is_array($catalogue[$type]))
                        $catalogue[$type] = array();
                    array_push($catalogue[$type],
                        array(
                            "amount" => $amount,
                            "title" => $title,
                            "to_pay" => array(),
                        )
                    );

                    /*
                     *
                     * Calcul des montants à payer
                     */
                    $queryMarge = \Drupal::entityQuery('taxonomy_term');
                    $queryMarge->condition('vid', "marge_de_reduction");
                    $tidsMarge = $queryMarge->execute();

                    foreach ($tidsMarge as $key2 => $val) {
                        $taxonomy_marge = \Drupal\taxonomy\Entity\Term::load($val);
                        $marge = $taxonomy_marge->field_marge->value;
                        $mode_paiement = $taxonomy_marge->field_moyen_paiement->value;
                        $i = count($catalogue[$type]) - 1;
                        array_push($catalogue[$type][$i]["to_pay"],
                            array(
                                "amount" => $amount - $marge * $amount / 100,
                                "moyen_paiement" => $mode_paiement,
                            )
                        );
                    }
                } catch (\Exception $ex) {
                    continue;
                }
            }

        }


        \Drupal::logger('catalogue_handler')->debug('Response : ', array("cities" => $catalogue));

        return new JsonResponse($catalogue);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function marge_reduction()
    {
        $query = \Drupal::entityQuery('taxonomy_term');
        $query->condition('vid', "marge_de_reduction");
        $tids = $query->execute();

        $marge_reduction = array();

        foreach ($tids as $key => $val) {
            $taxonomy_term = \Drupal\taxonomy\Entity\Term::load($val);

            array_push($marge_reduction,
                array(
                    "marge" => $taxonomy_term->field_marge->value,
                    "moyen_paiement" => $taxonomy_term->field_moyen_paiement->value,
                )
            );
        }

        $response = array("marge_reduction" => $marge_reduction);

        \Drupal::logger('marge_reduction_handler')->info('Response : ', $response);

        return new JsonResponse($response);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function tuile_dashboard()
    {

        $query = \Drupal::entityQuery('taxonomy_term');
        $query->condition('vid', "tuile_dashboard");
        $query->sort('weight', 'ASC');
        $tids = $query->execute();

        $tuile_dashboard = [];

        foreach ($tids as $key => $val) {
            $taxonomy_term = \Drupal\taxonomy\Entity\Term::load($val);

            if ($taxonomy_term->field_is_enabled->value) {
                if ($taxonomy_term->field_icone->entity) {
                    $enabledIcon = file_create_url(
                        $taxonomy_term->field_icone->entity->uri->value
                    );
                } else {
                    $enabledIcon = "";
                }

                if ($taxonomy_term->field_disabled_icone->entity) {
                    $disabledIcon = file_create_url(
                        $taxonomy_term->field_disabled_icone->entity->uri->value
                    );
                } else {
                    $disabledIcon = "";
                }

                $description = $taxonomy_term->description->value;
                $description = strip_tags($description);
                $description = trim($description);
                array_push(
                    $tuile_dashboard,
                    [
                        "id" => $taxonomy_term->tid->value,
                        "titre" => $taxonomy_term->name->value,
                        "description" => $description,
                        "couleur" => $taxonomy_term->field_couleur->value,
                        //"is_enabled" => ($taxonomy_term->field_is_enabled->value) ? true : false,
                        "code" => $taxonomy_term->field_tuile_code->value,
                        "icon_enabled" => $enabledIcon,
                        "icon_disabled" => $disabledIcon,
                    ]
                );
            }

        }

        \Drupal::logger('tuile_dashboard_handler')->debug('Response : ', $tuile_dashboard);

        return new JsonResponse($tuile_dashboard);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function avantages()
    {
        $nids = \Drupal::entityQuery('node')
            ->condition('type', 'avantages')
            ->execute();

        $nodes = \Drupal::entityTypeManager()
            ->getStorage('node')
            ->loadMultiple($nids);

        $lastNode = NULL;
        foreach ($nodes as $node)
            $lastNode = $node;


        $avantages = array();
        if ($lastNode)
            $avantages = array(
                "title" => $lastNode->getTitle(),
                "html_body" => $lastNode->body->value,
            );

        $response = array("avantages" => $avantages);

        return new JsonResponse($response);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function versionControl(Request $request)
    {
        $lang = $request->query->get('lang', 'fr');
        $os = $request->query->get('os');
        if (!in_array($os, ['ios', 'android'])) {
            return new JsonResponse(['header' => ['code' => 404,], 'body' => []]);
        }
        $versionControl = ApiDao::getVersionControlObject();
        $states = ApiDao::getVersionStatusNameIndexedById();
        $versions = ApiDao::getVersionsExceptionsByOs($os);
        $exceptionVersions = [];
        foreach ($versions as $version) {
            $exceptionVersions[$version->title->value] = $states[$version->field_status->target_id];
        }
        $response = [
            'header' => [
                'code' => 200,
            ],
            'body' => [
                'os' => $os,
                'version_min' => $os === 'ios' ? $versionControl->field_version_min_ios->value : $versionControl->field_version_min_android->value,
                'version_max' => $os === 'ios' ? $versionControl->field_version_max_ios->value : $versionControl->field_version_max_android->value,
                'exceptions' => $exceptionVersions,
                'messages' => [
                    'update' => $lang === 'ar' ? $versionControl->field_message_update_ar->value : $versionControl->field_message_update->value,
                    'obsolete' => $lang === 'ar' ? $versionControl->field_message_obsolete_ar->value : $versionControl->field_message_obsolete->value,
                ]
            ]
        ];
        return new JsonResponse($response);
    }

}

<?php

namespace Drupal\dealer\Controller;

use Drupal\api\Service\ApiDao;
use Drupal\Core\Controller\ControllerBase;

class VersionController extends ControllerBase
{

    public function listVersion()
    {
        $header = ['Version', 'Os', 'C\'est une exception', 'Statut', 'Action'];
        $states = ApiDao::getVersionStatusNameIndexedById();
        $versions = [
            'iOS' => ApiDao::getVersionsByOs('ios'),
            'Android' => ApiDao::getVersionsByOs('android'),
        ];
        $rows = [];
        foreach ($versions as $os => $versionsOs) {
            foreach ($versionsOs as $version) {
                $rows[] = [
                    'version' => $version->title->value,
                    'os' => $os,
                    'isException' => $version->field_isexception->value ? 'Oui' : 'Non',
                    'status' => $version->field_status->target_id ? $states[$version->field_status->target_id] : '-',
                    'action' => [
                        'edit' => "/node/{$version->nid->value}/edit?destination=/admin/version/list",
                        'delete' => "/node/{$version->nid->value}/delete?destination=/admin/version/list",
                    ]
                ];
            }
        }
        $build = [
            '#theme' => 'list-version',
            '#header' => $header,
            '#rows' => $rows,
        ];
        return $build;
    }
}
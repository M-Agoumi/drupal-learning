<?php

namespace Drupal\api\Service;

class ApiDao
{
    
    public static function getVersionControlObject()
    {
        $ids = \Drupal::entityQuery('node')
            ->condition('type', 'version_control')
            ->execute();
        $id = array_shift($ids);
        return \Drupal::entityTypeManager()->getStorage('node')->load($id);
    }
    public static function getOsIdByName($os)
    {
        $tids = \Drupal::entityQuery('taxonomy_term')
            ->condition('vid', 'os')
            ->condition('name', $os)
            ->execute();
        return array_shift($tids);
    }
    public static function getVersionStatusNameIndexedById()
    {
        $tids = \Drupal::entityQuery('taxonomy_term')
            ->condition('vid', 'version_status')
            ->execute();
        $states = \Drupal\taxonomy\Entity\Term::loadMultiple($tids);
        foreach ($states as &$status) {
            $status = $status->field_vs_code->value;
        }
        return $states;
    }
    public static function getVersionsExceptionsByOs($os)
    {
        $ids = \Drupal::entityQuery('node')
            ->condition('type', 'version')
            ->condition('field_os', static::getOsIdByName($os))
            ->condition('field_isexception', 1)
            ->execute();
        return \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($ids);
    }
    public static function getVersionsByOs($os)
    {
        $ids = \Drupal::entityQuery('node')
            ->condition('type', 'version')
            ->condition('field_os', static::getOsIdByName($os))
            ->execute();
        return \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($ids);
    }

}
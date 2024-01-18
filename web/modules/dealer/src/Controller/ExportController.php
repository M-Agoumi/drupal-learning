<?php

namespace Drupal\dealer\Controller;


use Drupal\Core\Controller\ControllerBase;
use Drupal\dealer\dao\BackendDAO;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\user\Entity\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Zend\Diactoros\Response\JsonResponse;

class ExportController extends ControllerBase
{

    /**
    * @var \Drupal\dealer\dao\BackendDAO
    */
    private $dao;
    public function __construct() {
        $this->dao = \Drupal::getContainer()->get('backend.dao');
    }

    public function getValidatedDealersListInTheGivenFormat($format)
    {
        $header = ['Nom', 'Prenom', 'Msisdn', 'Email', 'Nombre retailers',];
        $account = User::load(\Drupal::currentUser()->id());
        //If the Connected user is a regional, get the city to send it in param.
        $cities = $account->hasRole('regional') ? getCurrentRegionalCities() : null;
        $dealers = $this->dao->getVerifiedRevendeurs($cities);
        $rows = [];
        foreach ($dealers as $dealer) {
            $rows[] = [
                'last_name' => $dealer->last_name,
                'first_name' => $dealer->first_name,
                'msisdn' => $dealer->msisdn,
                'email' => $dealer->email,
                'n_retailers' => $this->dao->countVerifiedRetailers($dealer->id),
            ];
        }
        switch ($format) {
            case 'json': return new JsonResponse($rows); break;
            case 'xlsx': return $this->getDealersXlsxResponse('liste_revendeurs_valides', $header, $rows); break;
            default: throw new NotFoundHttpException(); break;
        }
    }
    public function getUnvalidatedDealersListInTheGivenFormat($format)
    {
        $header = ['Nom', 'Prenom', 'Msisdn', 'Email', 'Nombre retailers validés', 'Nombre retailers à valider',];
        $account = User::load(\Drupal::currentUser()->id());
        //If the Connected user is a regional, get the city to send it in param.
        $cities = $account->hasRole('regional') ? getCurrentRegionalCities() : null;
        $dealers = $this->dao->getUnverifiedRevendeurs($cities);
        $rows = [];
        foreach ($dealers as $dealer) {
            $rows[] = [
                'last_name' => $dealer->last_name,
                'first_name' => $dealer->first_name,
                'msisdn' => $dealer->msisdn,
                'email' => $dealer->email,
                'n_retailers' => $this->dao->countVerifiedRetailers($dealer->id),
                'n_u_retailers' => $this->dao->countUnverifiedRetailers($dealer->id),
            ];
        }
        switch ($format) {
            case 'json': return new JsonResponse($rows); break;
            case 'xlsx': return $this->getDealersXlsxResponse('liste_revendeurs_non_valides', $header, $rows); break;
            default: throw new NotFoundHttpException(); break;
        }
    }
    public function getOperationsListInTheGivenFormat($format, $id_revendeur)
    {
        $dealer = $this->dao->getInfoRevendeur($id_revendeur);
        $headers = ['Numero du vendeur', 'Numero du client', 'Valeur', 'Date', 'Action', 'Type', 'Commentaire'];
        $response = $this->dao->getDashboardSalesAndPurchases($id_revendeur);
        $history = $response && 200 == $response['header']['code'] ? $response['body']['history'] : [];
        $rows = [];
        foreach ($history as $order) {
            $rows[] = [
                'numero_saphir' => $dealer->msisdn,
                'numero_retailler' => $order['retailer']['msisdn'],
                'montant' => $order['amount'],
                'date' => $order['date'],
                'action' => ucfirst(strtolower($order['action'])),
                'type' => ucfirst(strtolower($order['type'])),
                'comment' => array_key_exists('comment', $order) ? $order['comment'] : '',
            ];
        }
        switch ($format) {
            case 'json': return new JsonResponse($rows); break;
            case 'xlsx':
                return $this->getOperationsXlsxResponse('liste_operations_' . $dealer->msisdn, $headers, $rows);
                break;
            default: throw new NotFoundHttpException(); break;
        }
    }
    public function getRetailersListInTheGivenFormat($format, $id_revendeur)
    {
        $headers = ['Nom', 'Prenom', 'Numero de téléphone', 'Adresse', 'Longitude', 'Latitude',];
        $retailers = $this->dao->getVerifiedRetailers($id_revendeur);
        $rows = [];
        foreach ($retailers as $retailer) {
            $rows[] = [
                'last_name' => $retailer->lastName,
                'first_name' => $retailer->firstName,
                'msisdn' => $retailer->msisdn,
                'adresse' => $retailer->adresse,
                'longitude' => $retailer->longitude ?: '-' ,
                'latitude' => $retailer->latitude ?: '-' ,
            ];
        }
        switch ($format) {
            case 'json': return new JsonResponse($rows); break;
            case 'xlsx':
                $dealer = $this->dao->getInfoRevendeur($id_revendeur);
                return $this->getRetailersXlsxResponse('liste_retailers_valides_' . $dealer->msisdn, $headers, $rows);
                break;
            default: throw new NotFoundHttpException(); break;
        }
    }
    public function getUnvalidatedRetailersListInTheGivenFormat($format, $id_revendeur)
    {
        $headers = ['Nom', 'Prenom', 'Numero de téléphone', 'Adresse', 'Longitude', 'Latitude',];
        $retailers = $this->dao->getUnVerifiedRetailers($id_revendeur);
        $rows = [];
        foreach ($retailers as $retailer) {
            $rows[] = [
                'last_name' => $retailer->lastName,
                'first_name' => $retailer->firstName,
                'msisdn' => $retailer->msisdn,
                'adresse' => $retailer->adresse,
                'longitude' => $retailer->longitude ?: '-' ,
                'latitude' => $retailer->latitude ?: '-' ,
            ];
        }
        switch ($format) {
            case 'json': return new JsonResponse($rows); break;
            case 'xlsx':
                $dealer = $this->dao->getInfoRevendeur($id_revendeur);
                return $this->getRetailersXlsxResponse('liste_retailers_non_valides_' . $dealer->msisdn, $headers, $rows);
                break;
            default: throw new NotFoundHttpException(); break;
        }
    }
    public function getDealersXlsxResponse($fileName, array $headers, array $rows)
    {
        $alphabets = 'ABCDEFGHIJKLMNOPWRSTUVWXYZ';
        $currentLine = 1;
        $startColumn = 'A';
        $endColumn = $alphabets[strpos($alphabets, $startColumn) + count($headers) - 1];
        $spreadsheet = new \PHPExcel();
        //Set properties
        $spreadsheet->getProperties()
            ->setCreator('Saphir BackOffice')
            ->setTitle('La liste des revendeurs')
            ->setDescription('La liste des revendeurs (saphirs)')
            ->setSubject('La liste des revendeurs')
            ->setKeywords('revendeurs, saphirs');
        //Add some data
        $spreadsheet->setActiveSheetIndex(0);
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->getDefaultColumnDimension()->setWidth(20);
        //Rename sheet
        $worksheet->setTitle('La liste des revendeurs');
        //Set Background
        $worksheet->getStyle("{$startColumn}{$currentLine}:{$endColumn}{$currentLine}")
            ->getFill()
            ->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('ff7901');
        //Set style Head
        $styleArrayHead = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'ffffff',]
            ]
        ];
        $worksheet->getStyle("{$startColumn}{$currentLine}:{$endColumn}{$currentLine}")->applyFromArray($styleArrayHead);
        for ($char = $startColumn; $char <= $endColumn; $char++) {
            $worksheet->getCell("{$char}{$currentLine}")->setValue(current($headers));
            next($headers);
        }
        reset($headers);
        $currentLine++;
        foreach ($rows as $row) {
            for ($char = $startColumn; $char <= $endColumn; $char++) {
                $worksheet->getCell("{$char}{$currentLine}")->setValue(current($row));
                next($row);
            }
            $currentLine++;
        }

        $response = new Response();
        $response->headers->set('Content-Type', 'application/vnd.ms-excel');
        $response->headers->set('Content-Disposition', "attachment; filename={$fileName}_" . date('Ymd_His') . ".xlsx");
        $response->headers->set('Content-Transfer-Encoding', 'binary');
        $response->headers->set('Cache-Control', 'max-age=0');
        $response->sendHeaders();
        $writer = new \PHPExcel_Writer_Excel2007($spreadsheet);
        $writer->save('php://output');
        exit();
    }
    public function getOperationsXlsxResponse($fileName, array $headers, array $rows)
    {
        $alphabets = 'ABCDEFGHIJKLMNOPWRSTUVWXYZ';
        $currentLine = 1;
        $startColumn = 'A';
        $endColumn = $alphabets[strpos($alphabets, $startColumn) + count($headers) - 1];
        $spreadsheet = new \PHPExcel();
        //Set properties
        $spreadsheet->getProperties()
            ->setCreator('Saphir BackOffice')
            ->setTitle('La liste des opérations')
            ->setDescription('L\'histroqiue des opérations effectuées')
            ->setSubject('La liste des opérations')
            ->setKeywords('histroqiue, opérations');
        //Add some data
        $spreadsheet->setActiveSheetIndex(0);
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->getDefaultColumnDimension()->setWidth(20);
        //Rename sheet
        $worksheet->setTitle('La liste des opérations');
        //Set Background
        $worksheet->getStyle("{$startColumn}{$currentLine}:{$endColumn}{$currentLine}")
            ->getFill()
            ->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('ff7901');
        //Set style Head
        $styleArrayHead = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'ffffff',]
            ]
        ];
        $worksheet->getStyle("{$startColumn}{$currentLine}:{$endColumn}{$currentLine}")->applyFromArray($styleArrayHead);
        for ($char = $startColumn; $char <= $endColumn; $char++) {
            $worksheet->getCell("{$char}{$currentLine}")->setValue(current($headers));
            next($headers);
        }
        reset($headers);
        $currentLine++;
        foreach ($rows as $row) {
            for ($char = $startColumn; $char <= $endColumn; $char++) {
                $worksheet->getCell("{$char}{$currentLine}")->setValue(current($row));
                next($row);
            }
            $currentLine++;
        }

        $response = new Response();
        $response->headers->set('Content-Type', 'application/vnd.ms-excel');
        $response->headers->set('Content-Disposition', "attachment; filename={$fileName}_" . date('Ymd_His') . ".xlsx");
        $response->headers->set('Content-Transfer-Encoding', 'binary');
        $response->headers->set('Cache-Control', 'max-age=0');
        $response->sendHeaders();
        $writer = new \PHPExcel_Writer_Excel2007($spreadsheet);
        $writer->save('php://output');
        exit();
    } 
    public function getRetailersXlsxResponse($fileName, array $headers, array $rows)
    {
        $alphabets = 'ABCDEFGHIJKLMNOPWRSTUVWXYZ';
        $currentLine = 1;
        $startColumn = 'A';
        $endColumn = $alphabets[strpos($alphabets, $startColumn) + count($headers) - 1];
        $spreadsheet = new \PHPExcel();
        //Set properties
        $spreadsheet->getProperties()
            ->setCreator('Saphir BackOffice')
            ->setTitle('La liste des retailers')
            ->setDescription('La liste des retailers')
            ->setSubject('La liste des retailers')
            ->setKeywords('retailers');
        //Add some data
        $spreadsheet->setActiveSheetIndex(0);
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->getDefaultColumnDimension()->setWidth(20);
        //Rename sheet
        $worksheet->setTitle('La liste des retailers');
        //Set Background
        $worksheet->getStyle("{$startColumn}{$currentLine}:{$endColumn}{$currentLine}")
            ->getFill()
            ->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('ff7901');
        //Set style Head
        $styleArrayHead = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'ffffff',]
            ]
        ];
        $worksheet->getStyle("{$startColumn}{$currentLine}:{$endColumn}{$currentLine}")->applyFromArray($styleArrayHead);
        for ($char = $startColumn; $char <= $endColumn; $char++) {
            $worksheet->getCell("{$char}{$currentLine}")->setValue(current($headers));
            next($headers);
        }
        reset($headers);
        $currentLine++;
        foreach ($rows as $row) {
            for ($char = $startColumn; $char <= $endColumn; $char++) {
                $worksheet->getCell("{$char}{$currentLine}")->setValue(current($row));
                next($row);
            }
            $currentLine++;
        }

        $response = new Response();
        $response->headers->set('Content-Type', 'application/vnd.ms-excel');
        $response->headers->set('Content-Disposition', "attachment; filename={$fileName}_" . date('Ymd_His') . ".xlsx");
        $response->headers->set('Content-Transfer-Encoding', 'binary');
        $response->headers->set('Cache-Control', 'max-age=0');
        $response->sendHeaders();
        $writer = new \PHPExcel_Writer_Excel2007($spreadsheet);
        $writer->save('php://output');
        exit();
    }

}
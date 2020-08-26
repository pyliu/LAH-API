<?php
require_once("../include/init.php");
require_once("../include/Query.class.php");
require_once('../vendor/autoload.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

$spreadsheet = IOFactory::load('../test.xlsx');

$worksheet = $spreadsheet->getActiveSheet();

$worksheet->getCell('A1')->setValue('套用樣板測試');
$worksheet->getCell('B2')->setValue('B2');
$worksheet->getCell('C3')->setValue('C3');

// $writer = new Xlsx($spreadsheet);
// $writer->save('exports/hello world.xlsx');
//header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="'.$today.'.xlsx"');
header('Cache-Control: max-age=0');
ob_end_clean();

$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->save('php://output');
?>

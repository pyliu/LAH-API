<?php
require_once("FileAPICommand.class.php");
require_once(dirname(dirname(dirname(__FILE__))).'/vendor/autoload.php');
use PhpOffice\PhpSpreadsheet\Spreadsheet;
// use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

class FileAPIExcelExportCommand extends FileAPICommand {
    function __construct() {}

    function __destruct() {}

    private function outputXlsx($filename = 'download') {

        // $spreadsheet = IOFactory::load('test.xlsx');
        // $worksheet = $spreadsheet->getActiveSheet();
        // $worksheet->getCell('A1')->setValue('套用樣板測試');

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', '這是第一格');

        // $writer = new Xlsx($spreadsheet);
        // $writer->save('exports/hello world.xlsx');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.$filename.'.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
    }

    public function execute() {
        $this->outputXlsx();
    }
}
?>

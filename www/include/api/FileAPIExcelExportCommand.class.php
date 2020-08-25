﻿<?php
require_once("FileAPICommand.class.php");
require_once(dirname(dirname(dirname(__FILE__))).'/vendor/autoload.php');
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

class FileAPIExcelExportCommand extends FileAPICommand {
    private $filename;
    function __construct() {
        $this->filename = $_POST["filename"] ?? 'download';
    }

    function __destruct() {}

    private function outputXlsx() {
        // $spreadsheet = IOFactory::load('test.xlsx');
        // $worksheet = $spreadsheet->getActiveSheet();
        // $worksheet->getCell('A1')->setValue('套用樣板測試');

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', '這是第一格');
        $sheet->getCell('A2')->setValue('這是第2格');

        $writer = new Xlsx($spreadsheet);
        $expfile = dirname(dirname(dirname(__FILE__))).'/exports/'.$this->filename.'.xlsx';
        $writer->save($expfile);
        unset($writer);

        // $writer = new Xlsx($spreadsheet);
        // $writer->save('exports/hello world.xlsx');
        ob_end_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        // header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="'.$this->filename.'.xlsx"');
        header('Cache-control: no-cache, pre-check=0, post-check=0, max-age=0');
        // https://stackoverflow.com/questions/34381816/phpexcel-return-a-corrupted-file
        // need to add this line to prevent corrupted file
        ob_end_clean();

        $writer = IOFactory::createWriter(IOFactory::load($expfile), 'Xlsx');
        $writer->save('php://output');
        // $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        // $writer->save('php://output');
        // readfile($expfile);
    }

    public function execute() {
        $this->outputXlsx();
    }
}
?>
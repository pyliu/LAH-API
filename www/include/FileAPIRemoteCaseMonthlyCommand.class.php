<?php
require_once("FileAPICommand.class.php");
require_once("Query.class.php");

class FileAPIRemoteCaseMonthlyCommand extends FileAPICommand {
    private $year_month;

    function __construct($year_month) {
        $this->year_month = $year_month;
        $this->colsNameMapping = include("Config.ColsNameMapping.CRSMS.php");
        $this->colsNameMapping += include("Config.ColsNameMapping.CABRP.php");
        $this->colsNameMapping += include("Config.ColsNameMapping.RLNID.php");
        $this->colsNameMapping += include("Config.ColsNameMapping.RKEYN.php");
    }

    function __destruct() {}

    public function execute() {
        $query = new Query();
        $data = $query->getRemoteCaseData($this->year_month);
        $this->output($data);
    }
}
?>

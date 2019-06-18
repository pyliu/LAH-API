<?php
require_once("FileAPICommand.class.php");
require_once("Query.class.php");

class FileAPISQLCsvCommand extends FileAPICommand {
    private $sql;
    function __construct($sql) {
        $this->sql = $sql;
        // parent class has $colsNameMapping var for translating column header
        $this->colsNameMapping = include("Config.ColsNameMapping.CRSMS.php");
        $this->colsNameMapping += include("Config.ColsNameMapping.CABRP.php");
        $this->colsNameMapping += include("Config.ColsNameMapping.EXPAA.php");
        $this->colsNameMapping += include("Config.ColsNameMapping.EXPAB.php");
        $this->colsNameMapping += include("Config.ColsNameMapping.EXPAC.php");
        $this->colsNameMapping += include("Config.ColsNameMapping.EXPBA.php");
        $this->colsNameMapping += include("Config.ColsNameMapping.EXPBB.php");
        $this->colsNameMapping += include("Config.ColsNameMapping.EXPCA.php");
        $this->colsNameMapping += include("Config.ColsNameMapping.EXPCB.php");
        $this->colsNameMapping += include("Config.ColsNameMapping.EXPCC.php");
        $this->colsNameMapping += include("Config.ColsNameMapping.EXPD.php");
        $this->colsNameMapping += include("Config.ColsNameMapping.EXPE.php");
        $this->colsNameMapping += include("Config.ColsNameMapping.EXPF.php");
        $this->colsNameMapping += include("Config.ColsNameMapping.EXPG.php");
        $this->colsNameMapping += include("Config.ColsNameMapping.RKEYN.php"); 
        $this->colsNameMapping += include("Config.ColsNameMapping.RLNID.php");
        $this->colsNameMapping += include("Config.ColsNameMapping.PSCRN.php");
        $this->colsNameMapping += include("Config.ColsNameMapping.OTHERS.php");
    }

    function __destruct() {}

    public function execute() {
        $q = new Query();
        $data = $q->getSelectSQLData($this->sql);
        $this->output($data);
    }
}
?>

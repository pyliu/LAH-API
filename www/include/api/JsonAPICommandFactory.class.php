<?php
abstract class JsonAPICommandFactory {
    public static function getCommand($type) {
        switch ($_POST["type"]) {
            case "x":
                break;
            case "fix_xcase":
                break;
            case "max":
                break;
            case "ralid":
                break;
            case "crsms":
                break;
            case "cmsms":
                break;
            case "easycard":
                break;
            case "fix_easycard":
                break;
            case "case":
                break;
            case "expac":
                break;
            case "mod_expac":
                break;
            case "expaa":
                break;
            case "expaa_AA09_update":
            case "expaa_AA100_update":
                break;
            case "diff_xcase":
                break;
            case "sync_xcase":
                break;
            case "unittest":
                break;
            default:
                break;
        }
    }
}

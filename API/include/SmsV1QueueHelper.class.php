<?php

/**
 * SmsV1QueueHelper - 移植 SmsMainFrame.TTSearchTimer 狀態判定與佇列搬移規則
 */
class SmsV1QueueHelper {
    /**
     * 依 MS_TYPE、MS30、sType 與 check_register_a/b/c/d 旗標決定對 SSMSWAIT 與 SSMSMAIN 的動作
     *
     * @param string $msType
     * @param string $ms30
     * @param string $sType
     * @param bool $checkRegisterA
     * @param bool $checkRegisterB
     * @param bool $checkRegisterC
     * @param bool $checkRegisterD
     * @return array{insert_wait: bool, wait_mode: string, wait_ms30: string, main_action: string, new_main_ms30: string}
     */
    public static function resolveAction(
        string $msType,
        string $ms30,
        string $sType,
        bool $checkRegisterA,
        bool $checkRegisterB,
        bool $checkRegisterC,
        bool $checkRegisterD
    ): array {
        $noop = array(
            'insert_wait' => false,
            'wait_mode' => 'none',
            'wait_ms30' => '',
            'main_action' => 'none',
            'new_main_ms30' => '',
        );

        if ($sType === 'I') {
            if ($ms30 === '0' && $checkRegisterA) {
                return array(
                    'insert_wait' => true,
                    'wait_mode' => 'legacy',
                    'wait_ms30' => 'I',
                    'main_action' => 'update_ms30',
                    'new_main_ms30' => 'I',
                );
            }
            return $noop;
        }

        if ($sType === 'C') {
            if (($ms30 === '0' || $ms30 === 'I') && $checkRegisterD) {
                return array(
                    'insert_wait' => true,
                    'wait_mode' => 'legacy',
                    'wait_ms30' => 'C',
                    'main_action' => 'update_ms30',
                    'new_main_ms30' => 'C',
                );
            }
            return $noop;
        }

        if ($sType === 'D') {
            return array(
                'insert_wait' => $checkRegisterB,
                'wait_mode' => $checkRegisterB ? 'legacy' : 'none',
                'wait_ms30' => 'D',
                'main_action' => 'delete_legacy',
                'new_main_ms30' => '',
            );
        }

        if ($sType === 'F') {
            return array(
                'insert_wait' => $checkRegisterC,
                'wait_mode' => $checkRegisterC ? 'legacy' : 'none',
                'wait_ms30' => 'F',
                'main_action' => 'delete_legacy',
                'new_main_ms30' => '',
            );
        }

        if ($sType === 'X') {
            return array(
                'insert_wait' => false,
                'wait_mode' => 'none',
                'wait_ms30' => '',
                'main_action' => 'delete_legacy',
                'new_main_ms30' => '',
            );
        }

        if ($msType === '2') {
            return array(
                'insert_wait' => true,
                'wait_mode' => 'type2',
                'wait_ms30' => '2',
                'main_action' => 'delete_legacy',
                'new_main_ms30' => '',
            );
        }

        if ($msType === 'M') {
            if ($ms30 === 'y' || $ms30 === 'z') {
                return array(
                    'insert_wait' => true,
                    'wait_mode' => 'm_gov',
                    'wait_ms30' => $ms30,
                    'main_action' => 'delete_gov',
                    'new_main_ms30' => '',
                );
            }
            return array(
                'insert_wait' => true,
                'wait_mode' => 'm_general',
                'wait_ms30' => $ms30,
                'main_action' => 'delete_m',
                'new_main_ms30' => '',
            );
        }

        return $noop;
    }
}

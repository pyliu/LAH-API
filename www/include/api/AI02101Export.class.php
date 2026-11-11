<?php
require_once __DIR__ . '/OtherRightsTxtExport.class.php';

final class AI02101Export {
    public static function readSection(string $section, callable $select): array {
        return OtherRightsTxtExport::readSection($section, $select, 'C');
    }

    public static function records(array $data): Generator {
        yield from OtherRightsTxtExport::records($data, 'C');
    }
}

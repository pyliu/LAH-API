<?php
require_once __DIR__ . '/OtherRightsTxtExport.class.php';

final class AI02201Export {
    public static function readSection(string $section, callable $select): array {
        return OtherRightsTxtExport::readSection($section, $select, 'F');
    }

    public static function records(array $data): Generator {
        yield from OtherRightsTxtExport::records($data, 'F');
    }
}

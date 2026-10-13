<?php

use App\Domain\Content\ExhibitionMapAddress;

it('requires an identifiable locality and country before an exhibition map can be enabled', function (): void {
    expect(ExhibitionMapAddress::isSufficient([]))->toBeFalse()
        ->and(ExhibitionMapAddress::isSufficient(['country' => 'Germany']))->toBeFalse()
        ->and(ExhibitionMapAddress::isSufficient(['location_text' => 'Hauptstraße 2']))->toBeFalse()
        ->and(ExhibitionMapAddress::isSufficient(['postal_code' => '20095']))->toBeFalse()
        ->and(ExhibitionMapAddress::isSufficient(['city' => 'Hamburg']))->toBeFalse()
        ->and(ExhibitionMapAddress::isSufficient([
            'country' => ' Germany ', 'city' => ' Hamburg ',
        ]))->toBeTrue()
        ->and(ExhibitionMapAddress::isSufficient([
            'country' => 'Germany', 'postal_code' => '20095',
        ]))->toBeTrue()
        ->and(ExhibitionMapAddress::isSufficient([
            'country' => 'Germany', 'city' => 'Hamburg',
            'postal_code' => '', 'location_text' => 'Rathausmarkt 1',
        ]))->toBeTrue()
        ->and(ExhibitionMapAddress::isSufficient([
            'country' => '  ', 'city' => 'Hamburg',
        ]))->toBeFalse();
});

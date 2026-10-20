<?php

use App\Domain\Content\PdfTextCleaner;

it('joins PDF line wraps but preserves paragraph boundaries', function (): void {
    expect(PdfTextCleaner::clean("Die Ausstellung zeigt\nWerke vergangener Jahre.\n\nDer zweite Absatz\nbleibt getrennt."))
        ->toBe("Die Ausstellung zeigt Werke vergangener Jahre.\n\nDer zweite Absatz bleibt getrennt.");
});

it('keeps list item line boundaries', function (): void {
    expect(PdfTextCleaner::clean("- Erste Zeile\n- Zweite Zeile"))
        ->toBe("- Erste Zeile\n- Zweite Zeile");
});

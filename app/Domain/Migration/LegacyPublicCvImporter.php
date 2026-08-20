<?php

namespace App\Domain\Migration;

use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class LegacyPublicCvImporter
{
    public const SOURCE = 'legacy-public-vita';

    public const BATCH = 'legacy-public-vita-2026-08-16';

    public function import(): int
    {
        return DB::transaction(function (): int {
            if (DB::table('cv_entries')->exists()) {
                throw new RuntimeException('Legacy CV import requires an empty cv_entries table.');
            }
            if (DB::table('exhibitions')->exists()) {
                throw new RuntimeException('Legacy Vita import requires an empty exhibitions table.');
            }

            PublicContentSetting::query()->sole();

            $now = now();
            $rows = $this->expectedRows();
            $cvPosition = 0;
            $exhibitionPosition = 0;

            foreach ($rows as $index => $row) {
                $legacyId = $index + 1;

                if ($row['section'] === 'Biography') {
                    DB::table('cv_entries')->insert([
                        'section' => 'Biography',
                        'title' => $row['title'],
                        'state' => 'published',
                        'position' => $cvPosition++,
                        'date_precision' => $row['date_precision'],
                        'organisation' => $row['organisation'],
                        'location' => $row['location'],
                        'body' => $row['body'],
                        'external_url' => null,
                        'image_media_asset_id' => null,
                        'year_text' => $row['year_text'],
                        'starts_on' => $row['starts_on'],
                        'ends_on' => $row['ends_on'],
                        'legacy_id' => $legacyId,
                        'legacy_source' => self::SOURCE,
                        'migration_batch_id' => self::BATCH,
                        'migrated_at' => $now,
                        'published_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    continue;
                }

                $slugBase = Str::slug($row['title']);
                DB::table('exhibitions')->insert([
                    'slug' => ($slugBase !== '' ? $slugBase : 'exhibition').'-legacy-'.$legacyId,
                    'title' => $row['title'],
                    'state' => 'published',
                    'position' => $exhibitionPosition++,
                    'kind' => null,
                    'venue' => $row['organisation'],
                    'city' => null,
                    'country' => null,
                    'location_text' => $row['location'],
                    'description' => $row['body'],
                    'external_url' => null,
                    'directions_url' => null,
                    'starts_on' => $row['starts_on'],
                    'ends_on' => $row['ends_on'],
                    'date_text' => $row['year_text'],
                    'opening_text' => $row['opening_text'],
                    'legacy_id' => $legacyId,
                    'legacy_source' => self::SOURCE,
                    'migration_batch_id' => self::BATCH,
                    'migrated_at' => $now,
                    'published_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ([SiteSection::TYPE_VITA, SiteSection::TYPE_EXHIBITIONS] as $type) {
                $updated = DB::table('site_sections')
                    ->where('type', $type)
                    ->update([
                        'state' => 'published',
                        'show_in_navigation' => true,
                        'updated_at' => $now,
                    ]);
                if ($updated !== 1) {
                    throw new RuntimeException("Canonical {$type} site section is missing.");
                }
            }

            return count($rows);
        });
    }

    /**
     * Verified factual snapshot from the legacy public Vita surface. This is migration source data,
     * not runtime navigation or presentation logic.
     *
     * @return list<array{section:string,title:string,year_text:string,date_precision:string,starts_on:?string,ends_on:?string,organisation:?string,location:?string,body:?string,opening_text:?string}>
     */
    public function expectedRows(): array
    {
        return [
            $this->row('Biography', 'Born in Hamburg', '1.6.1989', 'day', '1989-06-01', null, null, 'Hamburg'),
            $this->row('Biography', 'Studies at Berlin University of the Arts', '2010–2015 / 2018–2021', 'unknown', null, null, 'Berlin University of the Arts', 'Berlin', 'Lives and works in Hamburg.'),
            $this->row('Exhibitions', 'Annual Rundgang University of Arts Berlin', '2010–2013', 'unknown', null, null, 'University of Arts Berlin', 'Berlin'),
            $this->row('Exhibitions', 'Atelierbesuche – junge surreale Positionen: Illusions to carry on', '2012', 'year', null, null, 'Galerie Rosendahl, Thöne & Westphal', 'Berlin'),
            $this->row('Exhibitions', 'Gallery Weekend', '02.05.2014', 'day', '2014-05-02', null, 'Westphal Berlin', 'Berlin'),
            $this->row('Exhibitions', 'Neue Räume - Neue Bilder', 'June 2014', 'month', null, null, 'Westphal Berlin', 'Berlin', null, '6 June.'),
            $this->row('Exhibitions', 'I Love Art From Berlin', 'June 2014', 'month', null, null, 'Galerie Karin Sutter in cooperation with Westphal-Berlin', 'Basel', null, '15 June.'),
            $this->row('Exhibitions', 'Mögliche Welten', '30.04.2015 – 27.06.2015', 'day', '2015-04-30', '2015-06-27', 'Westphal Berlin', 'Berlin'),
            $this->row('Exhibitions', '26 Positions', '01.05.2015 – 03.05.2015', 'day', '2015-05-01', '2015-05-03', null, 'Berlin'),
            $this->row('Exhibitions', 'Kunstpreis 2016: Schöne, böse Bilder', '07.03.2016 – 23.03.2016', 'day', '2016-03-07', '2016-03-23', 'Sparkassen-Kundenzentrum am Europaplatz', 'Karlsruhe', null, '4 March, 7 pm.'),
            $this->row('Exhibitions', 'Aus Garten, Wald und Wiesen', '13.05.2016 – 02.07.2016', 'day', '2016-05-13', '2016-07-02', 'Westphal Berlin Kunst und Projekte', 'Berlin', null, '13 May, 7 pm.'),
            $this->row('Exhibitions', 'Bildauflehnung: Sehn- und Suchtgeschichten', '06.11.2016 – 27.11.2016', 'day', '2016-11-06', '2016-11-27', 'Barfuss-Galerie', 'Hamburg'),
            $this->row('Exhibitions', 'Blick zurück - nach vorn', 'December 2016', 'month', null, null, 'Westphal Berlin', 'Berlin', null, '9 December, 18 pm.'),
            $this->row('Exhibitions', 'Urbane Grenzgänger', '15.02.2017 – 14.03.2017', 'day', '2017-02-15', '2017-03-14', 'Nissis Kunstkantine', 'Hamburg', 'With Claudia Tejeda.', '15 February, 7 pm.'),
            $this->row('Exhibitions', 'Westphal Berlin: Am Meer - Ahrenshoop 2018', '16.08.2018 – 23.09.2018', 'day', '2018-08-16', '2018-09-23', 'Strandhalle Ahrenshoop', 'Ahrenshoop'),
            $this->row('Exhibitions', 'Lange Nacht der Bilder', '14.09.2018', 'day', '2018-09-14', null, 'Lichtenberg Studios ID', 'Genslerstraße 13, 13055 Berlin-Lichtenberg'),
            $this->row('Exhibitions', 'Berlin Art Week: Open Studios ID', '28.09.2018 & 29.09.2018', 'day', '2018-09-28', '2018-09-29', 'Studios ID', 'Genslerstraße 13, 13055 Berlin-Lichtenberg'),
            $this->row('Exhibitions', 'Lars Möller. Bilder', '28.04.2019 – 02.06.2019', 'day', '2019-04-28', '2019-06-02', 'kunst am bahnhof bad saarow e.V.', 'Bad Saarow'),
            $this->row('Exhibitions', 'EWIG', '29.08.2020 – 09.10.2020', 'day', '2020-08-29', '2020-10-09', 'La Bottega', 'Colonnaden 72, 20354 Hamburg'),
            $this->row('Exhibitions', 'Lange Nacht der Bilder', '04.09.2020', 'day', '2020-09-04', null, 'Lichtenberg Studios ID', 'Genslerstraße 13, 13055 Berlin'),
            $this->row('Exhibitions', 'Art AHoJ', '15.10.2020 – 05.11.2020', 'day', '2020-10-15', '2020-11-05', 'Erotik Art Museum & Popstreet.shop', 'Reeperbahn 157, 20359 Hamburg'),
            $this->row('Exhibitions', 'Der blonde Hans', '29.06.2021 – 25.09.2021', 'day', '2021-06-29', '2021-09-25', 'Jawoll Erotik Art Museum & St. Pauli Bürgerverein', 'Hamburg', 'Hans Albers Eck, Hans-Albers-Platz 20 & Hans-Albers-Klause, Friedrichstraße 19.'),
            $this->row('Exhibitions', 'Entdeckungsreisen', '01.07.2021 – 31.08.2021', 'day', '2021-07-01', '2021-08-31', 'Haspa-Filiale Bergstedt', 'Volksdorfer Damm 180, 22359 Hamburg'),
            $this->row('Exhibitions', 'Schau´ nicht weg!', '14.08.2021 – 17.09.2021', 'day', '2021-08-14', '2021-09-17', 'EWIG Künstlerkollektiv und Schau´ nicht weg! e.V.', 'Hansa-Theater, Steindamm 17, 20099 Hamburg'),
            $this->row('Exhibitions', 'Labyrinthe', '24.10.2021 – 19.12.2021', 'day', '2021-10-24', '2021-12-19', 'Barfuss-Galerie', 'Sandkuhlenkoppel 55, 22399 Hamburg'),
            $this->row('Exhibitions', 'Verknotet im Leben', '06.03.2022 – 03.04.2022', 'day', '2022-03-06', '2022-04-03', 'Ev. Akademie in der Region Alstertal', 'Simon-Petrus-Kirche, Harksheider Str. 156, 22399 Hamburg'),
            $this->row('Exhibitions', 'Philippus Loves Art', '24.06.2022 – 08.07.2022', 'day', '2022-06-24', '2022-07-08', 'Ev. Philippus-Kirchengemeinde Kassel', 'Wolfhager Str. 182, 34127 Kassel'),
            $this->row('Exhibitions', 'St. Paulus Lounge', '29.06.2024 – 06.07.2024', 'day', '2024-06-29', '2024-07-06', 'EWIG Künstlerkollektiv, wer wenn nicht wir e.V.', 'Hans Albers Platz 2, 20359 Hamburg'),
            $this->row('Exhibitions', 'Art Empire', '15.09.2024', 'day', '2024-09-15', null, 'EWIG Künstlerkollektiv', 'Empire Riverside Hotel Hamburg, Bernhard-Nocht-Straße 97, 20359 Hamburg'),
            $this->row('Exhibitions', 'FFFF2', '09.05.2025 – 28.05.2025', 'day', '2025-05-09', '2025-05-28', 'Be´Shan-Art Galerie', 'Hamburger Straße 1-15, Mundsburg Center, Hamburg'),
            $this->row('Exhibitions', 'Johannes der Täufer', '14.06.2025 – 28.06.2025', 'day', '2025-06-14', '2025-06-28', 'Simon-Petrus-Kirche', 'Harksheider Straße 156, 22399 Hamburg'),
        ];
    }

    /** @return array{section:string,title:string,year_text:string,date_precision:string,starts_on:?string,ends_on:?string,organisation:?string,location:?string,body:?string,opening_text:?string} */
    private function row(string $section, string $title, string $yearText, string $datePrecision, ?string $startsOn, ?string $endsOn, ?string $organisation, ?string $location, ?string $body = null, ?string $openingText = null): array
    {
        return [
            'section' => $section,
            'title' => $title,
            'year_text' => $yearText,
            'date_precision' => $datePrecision,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'organisation' => $organisation,
            'location' => $location,
            'body' => $body,
            'opening_text' => $openingText,
        ];
    }
}

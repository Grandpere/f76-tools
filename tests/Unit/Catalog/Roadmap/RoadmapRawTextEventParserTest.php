<?php

declare(strict_types=1);

/*
 * This file is part of a F76 project.
 *
 * (c) Lorenzo Marozzo <lorenzo.marozzo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Unit\Catalog\Roadmap;

use App\Catalog\Application\Roadmap\RoadmapRawTextEventParser;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RoadmapRawTextEventParserTest extends TestCase
{
    public function testParseFrenchDateRangesWithFollowingTitle(): void
    {
        $parser = new RoadmapRawTextEventParser();
        $text = <<<TXT
            3 MARS - 10 MARS
            LA FETE DU YETI

            10 MARS - 24 MARS
            ENVAHISSEURS D'AU-DELA
            TXT;

        $events = $parser->parse($text, 'fr', new DateTimeImmutable('2026-03-02 10:00:00'));

        self::assertCount(2, $events);
        self::assertSame('LA FÊTE DU YETI', $events[0]->title);
        self::assertSame('2026-03-03 18:00:00', $events[0]->startsAt->format('Y-m-d H:i:s'));
        self::assertSame('2026-03-10 18:00:00', $events[0]->endsAt->format('Y-m-d H:i:s'));
        self::assertSame('ENVAHISSEURS D\'AU-DELA', $events[1]->title);
    }

    public function testParseEnglishDateRangesWithUppercaseMonth(): void
    {
        $parser = new RoadmapRawTextEventParser();
        $text = <<<TXT
            7 APRIL - 14 APRIL
            DOUBLE XP
            TXT;

        $events = $parser->parse($text, 'en', new DateTimeImmutable('2026-04-01 00:00:00'));

        self::assertCount(1, $events);
        self::assertSame('DOUBLE XP', $events[0]->title);
        self::assertSame('2026-04-07 18:00:00', $events[0]->startsAt->format('Y-m-d H:i:s'));
        self::assertSame('2026-04-14 18:00:00', $events[0]->endsAt->format('Y-m-d H:i:s'));
    }

    public function testParseEnglishDateRangesWithOrdinalsAndShortMonths(): void
    {
        $parser = new RoadmapRawTextEventParser();
        $text = <<<TXT
            1st APR - 5th APR
            DOUBLE SCORE
            TXT;

        $events = $parser->parse($text, 'en', new DateTimeImmutable('2026-04-01 00:00:00'));

        self::assertCount(1, $events);
        self::assertSame('DOUBLE SCORE', $events[0]->title);
        self::assertSame('2026-04-01 18:00:00', $events[0]->startsAt->format('Y-m-d H:i:s'));
        self::assertSame('2026-04-05 18:00:00', $events[0]->endsAt->format('Y-m-d H:i:s'));
    }

    public function testParseGermanDateRangesWithUmlautNormalization(): void
    {
        $parser = new RoadmapRawTextEventParser();
        $text = <<<TXT
            5 MÄRZ - 12 MÄRZ
            DOPPELTE MUTATIONEN
            TXT;

        $events = $parser->parse($text, 'de', new DateTimeImmutable('2026-03-01 00:00:00'));

        self::assertCount(1, $events);
        self::assertSame('DOPPELTE MUTATIONEN', $events[0]->title);
        self::assertSame('2026-03-05 18:00:00', $events[0]->startsAt->format('Y-m-d H:i:s'));
        self::assertSame('2026-03-12 18:00:00', $events[0]->endsAt->format('Y-m-d H:i:s'));
    }

    public function testParseGermanDateRangesWithDotNotationAndAliases(): void
    {
        $parser = new RoadmapRawTextEventParser();
        $text = <<<TXT
            7. APR - 14. APR
            DOPPELTE MUTATIONEN
            TXT;

        $events = $parser->parse($text, 'de', new DateTimeImmutable('2026-04-01 00:00:00'));

        self::assertCount(1, $events);
        self::assertSame('DOPPELTE MUTATIONEN', $events[0]->title);
        self::assertSame('2026-04-07 18:00:00', $events[0]->startsAt->format('Y-m-d H:i:s'));
        self::assertSame('2026-04-14 18:00:00', $events[0]->endsAt->format('Y-m-d H:i:s'));
    }

    public function testParseFrenchRoadmapWithMultilineTitlesAndFirsterDay(): void
    {
        $parser = new RoadmapRawTextEventParser();
        $text = <<<TXT
            RIP DARING
            AND THE
            CRYPTIDS FROM BEYOND THE COSMOS
            FALLOUT 76 SEASON 24
            FALLOUT76 COMMUNITY CALENDAR

            Mars
            FORET SAUVAGE ET SAISON 24
            3 MARS MISE A JOUR
            FORET SAUVAGE
            3 MARS - 10 MARS
            LA FETE DU YETI
            10 MARS - 24 MARS
            EVENEMENT LES
            ENVAHISSEURS D AU DELA
            12 MARS - 16 MARS
            CAPSULES A GOGO ET GRANDE
            PROMOTION DE MINERVA
            19 MARS - 23 MARS
            DOUBLE S.C.O.R.E., DOUBLES MUTATIONS
            ET CHOIX SPECIAL DE MURMRGH

            Avril
            RIP DARING : EXPERT EN ARMES EXTRAORDINAIRE !
            7 AVRIL - 14 AVRIL
            EVENEMENTS
            PUBLICS MUTANTS
            16 AVRIL - 20 AVRIL
            DOUBLE XP, DOUBLES MUTATIONS ET
            GRANDE PROMOTION DE MINERVA
            21 AVRIL - 5 MAI
            MINI SAISON RIP DARING
            28 AVRIL - 12 MAI
            EVENEMENTS CALCINES
            EFFRAYANTS
            30 AVRIL - 4 MAI
            DOUBLE S.C.O.R.E., DOUBLES
            MUTATIONS ET CAPSULES A GOGO

            Mai
            FLEAU DE BELZABEILLE
            7 MAI - 11 MAI
            SURPLUS DE MITRAILLE
            14 MAI - 18 MAI
            DOUBLES MUTATIONS ET
            CHASSEURS DE TRESORS
            19 MAI - 2 JUIN
            EVENEMENT FLORAISON
            EXPLOSIVE
            21 MAI - 25 MAI
            CHOIX SPECIAL DE MURMRGH ET
            GRANDE PROMOTION DE MINERVA
            28 MAI - 1ER JUIN
            FIEVRE DE L OR

            Juin
            NOUVELLE MISE A JOUR ET SAISON 25
            4 JUIN - 8 JUIN
            CAPSULES A GOGO
            9 JUIN - 16 JUIN
            EVENEMENTS PUBLICS
            MUTANTS
            11 JUIN - 15 JUIN
            SURPLUS DE MITRAILLE
            ET DOUBLES MUTATIONS
            18 JUIN - 22 JUIN
            DOUBLE S.C.O.R.E., DOUBLES
            MUTATIONS, CHASSEUR DE TRESORS
            ET PROMOTION LEGENDAIRE
            23 JUIN - 7 JUILLET
            EVENEMENT DEUX
            SERVICES DE SEMAINE
            DE LA VIANDE

            BETHESDA
            2026 ZeniMax
            TXT;

        $events = $parser->parse($text, 'fr', new DateTimeImmutable('2026-03-02 10:00:00'));

        self::assertCount(20, $events);
        self::assertSame('MISE A JOUR FORET SAUVAGE', $events[0]->title);
        self::assertSame('LA FÊTE DU YETI', $events[1]->title);
        self::assertSame('EVENEMENT LES ENVAHISSEURS D AU DELA', $events[2]->title);
        self::assertSame('DOUBLE S.C.O.R.E., DOUBLES MUTATIONS ET CHOIX SPECIAL DE MURMRGH', $events[4]->title);
        self::assertSame('EVENEMENTS CALCINES EFFRAYANTS', $events[8]->title);
        self::assertSame('FIEVRE DE L OR', $events[14]->title);
        self::assertSame('EVENEMENT DEUX SERVICES DE SEMAINE DE LA VIANDE', $events[19]->title);

        self::assertSame('2026-03-03 16:00:00', $events[0]->startsAt->format('Y-m-d H:i:s'));
        self::assertSame('2026-03-03 20:00:00', $events[0]->endsAt->format('Y-m-d H:i:s'));
        self::assertSame('2026-05-28 18:00:00', $events[14]->startsAt->format('Y-m-d H:i:s'));
        self::assertSame('2026-06-01 18:00:00', $events[14]->endsAt->format('Y-m-d H:i:s'));
        self::assertSame('2026-07-07 18:00:00', $events[19]->endsAt->format('Y-m-d H:i:s'));
    }

    public function testParseIgnoresKnownOcrNoiseLinesInTitles(): void
    {
        $parser = new RoadmapRawTextEventParser();
        $text = <<<TXT
            3 MARS - 10 MARS
            LA FETE DU YETI
            Falleut 76
            COMMUNITY CALENDAR
            •Bethesda™
            KA TM 2024 7 iA
            10 MARS - 24 MARS
            ENVAHISSEURS D'AU-DELA
            TXT;

        $events = $parser->parse($text, 'fr', new DateTimeImmutable('2026-03-02 10:00:00'));

        self::assertCount(2, $events);
        self::assertSame('LA FÊTE DU YETI', $events[0]->title);
        self::assertSame('ENVAHISSEURS D\'AU-DELA', $events[1]->title);
    }

    public function testParseAcceptsOcrTyposAndTruncatedMonths(): void
    {
        $parser = new RoadmapRawTextEventParser();
        $text = <<<TXT
            3 MARS - 10 MARS
            LA FETE DU YETI
            7 AVRlL - 14 AVRlL
            EVENEMENTS PUBLICS MUTANTS
            28 MA1 - 1ER JUlN
            FIEVRE DE L OR
            TXT;

        $events = $parser->parse($text, 'fr', new DateTimeImmutable('2026-03-02 10:00:00'));

        self::assertCount(3, $events);
        self::assertSame('2026-04-07 18:00:00', $events[1]->startsAt->format('Y-m-d H:i:s'));
        self::assertSame('2026-04-14 18:00:00', $events[1]->endsAt->format('Y-m-d H:i:s'));
        self::assertSame('2026-05-28 18:00:00', $events[2]->startsAt->format('Y-m-d H:i:s'));
        self::assertSame('2026-06-01 18:00:00', $events[2]->endsAt->format('Y-m-d H:i:s'));
    }

    public function testParseAcceptsDamagedEndMonthTokenAndInfersMonthWhenNeeded(): void
    {
        $parser = new RoadmapRawTextEventParser();
        $text = <<<TXT
            7 AVRIL - 14 A/RI|
            EVENEMENTS PUBLICS MUTANTS
            TXT;

        $events = $parser->parse($text, 'fr', new DateTimeImmutable('2026-03-02 10:00:00'));

        self::assertCount(1, $events);
        self::assertSame('2026-04-07 18:00:00', $events[0]->startsAt->format('Y-m-d H:i:s'));
        self::assertSame('2026-04-14 18:00:00', $events[0]->endsAt->format('Y-m-d H:i:s'));
    }

    public function testParseProvidedRawTextWithSplitDateLinesAndTerOrdinal(): void
    {
        $parser = new RoadmapRawTextEventParser();
        $text = <<<TXT
            RIP DARING
            AND THE
            CRYPTIDS FROM BEYOND THE COSMOS
            FALLOUT
            Mars
            FORET SAUVAGE ET SAISON 24
            3 MARS
            MISĘ A JOUR
            FORET SAUVAGE
            3 MARS - 10 MARS
            LA FÉTE DU YETI
            10 MARS - 24 MARS
            ÉVÉNEMENT LES
            ENVAHISSEURS D'AU-DELÀ
            Avril
            RIP DARING : EXPERT EN ARMES EXTRAORDINAIRE !
            7 AVRIL - 14 AVRIL
            ÉVÉNEMENTS
            PUBLICS MUTANTS
            16 AVRIL - 20 AVRIL
            DOUBLE XP, DOUBLES MUTATIONS ET
            GRANDE PROMOTION DE MINERVA
            21 AVRIL - 5 MAI
            MINI SAISON RIP DARING
            Mai
            FLÉAU DE BELZABEILLE
            7 MAI - 11 MAI
            SURPLUS DE MITRAILLE
            14 MAI - 18 MAI
            DOUBLES MUTATIONS ET
            CHASSEUR DE TRESORS
            19 MAI - 2 JUIN
            ÉVÉNEMENT FLORAISON
            EXPLOSIVE
            12 MARS -
            16 MARS
            CAPSULES À GOGO ET GRANDE
            PROMOTION DE MINERVA
            00
            19 MARS - 23 MARS
            DOUBLE S.C.O.R.E., DOUBLES MUTATIONS
            ET CHOIX SPECIAL DE MURMRGH
            28 AVRIL - 12 MAI
            ÉVÉNEMENT CALCINÉS
            EFFRAYANTS
            30 AVRIL
            - 4 MAI
            DOUBLE S.C.O.R.E., DOUBLES
            MITATIONS ET CAPCILES A GOGO
            21 MAI - 25 MAI
            CHOIX SPECIAL DE MURMRGH ET
            GRANDE PROMOTION DE MINERVA
            3 MAI - TER JUIN
            FIÈVRE DE L'OR
            Juin
            NOUVELLE MISE À JOUR ET SAISON 25
            4 JUIN - 8 JUIN
            CAPSULES À GOGO
            9 JUIN - 16 JUIN
            ÉVÉNEMENTS PUBLICS
            MUTANTS
            11 JUIN - 15 JUIN
            SURPLUS DE MITRAILLE
            ET DOUBLES MUTATIONS
            18 JUIN - 22 JU
            DOUBLE S.C.O.R.E., DOUBLES
            MUTATIONS, CHASSEUR DE TRÉSORS
            ET PROMOTION LÉGENDAIRE
            Falleut 76
            COMMUNITY CALENDAR
            23 JUIN -7 JUHLET
            ÉVÉNEMENT DEUX
            SERVICES DE SEMAINE
            DE LA VIANDE
            •Bethesda™
            KA TM 2024 7 iA
            TXT;

        $events = $parser->parse($text, 'fr', new DateTimeImmutable('2026-03-02 10:00:00'));

        self::assertCount(20, $events);
        self::assertTrue($this->containsEvent($events, 'MISE A JOUR FORET SAUVAGE', '2026-03-03 00:00:00', '2026-03-03 23:59:59'));
        self::assertTrue($this->containsDateRange($events, '2026-03-12 00:00:00', '2026-03-16 23:59:59'));
        self::assertTrue($this->containsEvent($events, 'FIÈVRE DE L\'OR', '2026-05-28 00:00:00', '2026-06-01 23:59:59'));
    }

    public function testParseNormalizesUnexpectedUnicodeGlyphsFromOcr(): void
    {
        $parser = new RoadmapRawTextEventParser();
        $text = <<<TXT
            3 MARS
            MISĘ A JOUR
            FORET SAUVAGE
            TXT;

        $events = $parser->parse($text, 'fr', new DateTimeImmutable('2026-03-02 10:00:00'));

        self::assertCount(1, $events);
        self::assertSame('MISE A JOUR FORET SAUVAGE', $events[0]->title);
    }

    public function testParseFixesFrenchFeteAccentInTitles(): void
    {
        $parser = new RoadmapRawTextEventParser();
        $text = <<<TXT
            3 MARS - 10 MARS
            LA FÉTE DU YETI
            TXT;

        $events = $parser->parse($text, 'fr', new DateTimeImmutable('2026-03-02 10:00:00'));

        self::assertCount(1, $events);
        self::assertSame('LA FÊTE DU YETI', $events[0]->title);
    }

    public function testParseGermanProvidedRawTextBuildsTwentyEvents(): void
    {
        $parser = new RoadmapRawTextEventParser();
        $text = <<<TXT
            RIP DARING
            AND THE
            CRYPTIDS FROM BEYOND THE COSMOS
            FALLOUT
            Mäng
            DAS HINTERLAND UND SAISON 24
            3. MÄRZ
            UPDATE: DAS
            HINTERLAND
            3. BIS 10. MÄRZ
            BIGFOOTS PARTY
            10. BIS 24. MÄRZ
            EVENT: ANGREIFER AUS
            DEM ALL
            April
            RIP DARING: WAFFENNARR DER EXTRAKLASSE!
            7. BIS 14. APRI
            MUTIERTE ÖFFENTLICHE
            EVENTS
            BIS 20. APRIL
            DOPPELTE EP, DOPPELMUTATIONEN
            UND MINERVAS SONDERANGEBOTE
            APRIL BIS 5. MAI
            MINI-SAISON:
            RIP DARING
            Mai
            DIE BIEZELBUB-MISERE
            7. BIS 11. MAI
            SCHEINE-SEGEN
            14. BIS 18. MAI
            DOPPELMUTATIONEN
            UND SCHATZSUCHER
            19. MAI BIS 2. JUNI
            EVENT: DAS GROBE
            BLÜHEN
            00
            12. BİS 16. MÄRZ
            REICHLICH KRONKORKEN UND
            MINERVAS SONDERANGEBOTE
            19. BIS 23. MÄRZ
            DOPPELTER S.C.O.R.E., DOPPELMUTATIONEN
            UND MURMRGHS GEHEIME AUSWAHL
            28. APRIL BIS 12. MAI
            GRUSELIGE-VERBRANNTE-
            EVENT
            30. APRIL BIS 4. MA
            DOPPELTER S.C.O.R.E., DOPPELMUTATIONEN
            IND REICHLICH KRONKORKEN
            21. BIS 25. MA
            MURMRGHS GEHEIME AUSWAHL
            UND MINERVAS SONDERANGEBOTE
            28. MAI BIS 1. JUNI
            GOLDRAUSCH
            Juni
            BEVORSTEHENDES UPDATE UND SAISON 25
            4. BIS 8. JUNI
            REICHLICH KRONKORKEN
            9. BIS 16. JUNI
            MUTIERTE ÖFFENTLICHE
            EVENTS
            11. BIS 15. JUNI
            SCHEINE-SEGEN UND
            DOPPELMUTATIONEN
            18. BIS 22. JUNI
            DOPPELTER S.C.O.R.E., DOPPELMUTATIONEN
            SCHATZSUCHER UND LEGENDÄRE ANGEBOTE
            Falleut 76
            23. JUNI BIS 7. JULI
            EVENT: FLEISCHWOCHE
            SAMT NACHSCHLAG
            •Bethesda™
            COMMUNITY CALENDAR
            TXT;

        $events = $parser->parse($text, 'de', new DateTimeImmutable('2026-03-02 10:00:00'));

        self::assertCount(20, $events);
        self::assertTrue($this->containsDateRange($events, '2026-03-03 00:00:00', '2026-03-03 23:59:59'));
        self::assertTrue($this->containsDateRange($events, '2026-03-03 00:00:00', '2026-03-10 23:59:59'));
        self::assertTrue($this->containsDateRange($events, '2026-04-07 00:00:00', '2026-04-14 23:59:59'));
        self::assertTrue($this->containsDateRange($events, '2026-04-16 00:00:00', '2026-04-20 23:59:59'));
        self::assertTrue($this->containsDateRange($events, '2026-04-21 00:00:00', '2026-05-05 23:59:59'));
        self::assertTrue($this->containsDateRange($events, '2026-05-21 00:00:00', '2026-05-25 23:59:59'));
        self::assertTrue($this->containsTitleFragments($events, ['MINI-SAISON', 'RIP DARING']));
        self::assertTrue($this->containsDateRange($events, '2026-03-12 00:00:00', '2026-03-16 23:59:59'));
        self::assertTrue($this->containsDateRange($events, '2026-06-23 00:00:00', '2026-07-07 23:59:59'));
    }

    public function testParseEnglishProvidedRawTextBuildsTwentyEvents(): void
    {
        $parser = new RoadmapRawTextEventParser();
        $text = <<<TXT
            RIP DARING
            AND THE
            CRYPTIDS FROM BEYOND THE COSMOS
            FALLOUT
            March
            THE BACKWOODS AND SEASON 24
            MARCH 3
            THE BACKWOODS
            UPDATE
            MAR 3 - MAR 10
            BIGFOOT'S BASH
            MAR 10 - MAR 24
            INVADERS FROM BEYOND
            EVENT
            April
            RIP DARING: WEAPONS EXPERT EXTRAORDINAIRE!
            APRIL 7 - APRIL 14
            MUTATED PUBLIC
            EVENTS
            APRIL 16 - APRIL 20
            DOUBLE XP, DOUBLE MUTATIONS,
            AND MINERVA'S BIG SALE
            APRIL 21 - MAY 5
            RIP DARING MINI SEASON
            May
            PLIGHT OF THE BEEZLEBUB
            MAY 7 - MAY 11
            SCRIP SURPLUS
            MAY 14 - MAY 18
            DOUBLE MUTATIONS AND
            TREASURE HUNTER
            MAY 19 - JUNE 2
            THE BIG BLOOM EVENT
            MAR 12 - MAR 16
            CAPS-A-PLENTY AND
            MINERVA'S BIG SALE
            00
            •
            MAR 19 - MAR 23
            DOUBLE SCORE, DOUBLE MUTATIONS,
            AND MURMH'S SPECIAL PICK
            APRIL 28 - MAY 12
            SPOOKY SCORCHED
            EVENT
            APRIL 30 - MAY 4
            DOUBLE SCORE, DOUBLE
            MUTATIONS, AND CAPS-A-PLENTY
            MAY 21 - MAY 25
            MURMH'S SPECIAL PICK
            AND MINERVA'S BIG SALE
            MAY 28 - JUNE 1
            GOLD RUSH
            June
            UPCOMING UPDATE AND SEASON 25
            JUNE 4 - JUNE 8
            CAPS-A-PLENTY
            JUNE 9 - JUNE 16
            MUTATED PUBLIC EVENTS
            JUNE 11 - JUNE 15
            SCRIP SURPLUS AND
            DOUBLE MUTATIONS
            JUNE 18 - JUNE 22
            DOUBLE SCORE, DOUBLE MUTATIONS,
            TREASURE HUNTER, AND LEGENDARY SALE
            Falleut 76,
            JUNE 23 - JULY 7
            TWO HELPINGS OF
            MEAT WEEK EVENT
            •Bethesda™
            COMMUNITY CALENDAR
            TXT;

        $events = $parser->parse($text, 'en', new DateTimeImmutable('2026-03-02 10:00:00'));

        self::assertCount(20, $events);
        self::assertTrue($this->containsDateRange($events, '2026-03-03 00:00:00', '2026-03-03 23:59:59'));
        self::assertTrue($this->containsDateRange($events, '2026-04-16 00:00:00', '2026-04-20 23:59:59'));
        self::assertTrue($this->containsDateRange($events, '2026-04-21 00:00:00', '2026-05-05 23:59:59'));
        self::assertTrue($this->containsDateRange($events, '2026-05-21 00:00:00', '2026-05-25 23:59:59'));
        self::assertTrue($this->containsDateRange($events, '2026-06-23 00:00:00', '2026-07-07 23:59:59'));
        self::assertFalse($this->containsTitleFragments($events, ['00']));
    }

    /**
     * @param list<\App\Catalog\Application\Roadmap\RoadmapParsedEvent> $events
     */
    private function containsEvent(array $events, string $title, string $startsAt, string $endsAt): bool
    {
        $expectedTitle = $this->normalizeTitleForAssert($title);
        [$expectedStartsAt, $expectedEndsAt] = $this->normalizeExpectedWindow($startsAt, $endsAt);
        foreach ($events as $event) {
            if (
                $this->normalizeTitleForAssert($event->title) === $expectedTitle
                && $event->startsAt->format('Y-m-d H:i:s') === $expectedStartsAt
                && $event->endsAt->format('Y-m-d H:i:s') === $expectedEndsAt
            ) {
                return true;
            }
        }

        return false;
    }

    private function normalizeTitleForAssert(string $value): string
    {
        $normalized = mb_strtoupper(trim($value));
        $normalized = str_replace(
            ['É', 'È', 'Ê', 'Ë', 'À', 'Â', 'Î', 'Ï', 'Ô', 'Û', 'Ù', 'Ç', 'Ä', 'Ö', 'Ü', 'á', 'à', 'â', 'ä', 'é', 'è', 'ê', 'ë', 'ï', 'î', 'ô', 'ö', 'ù', 'û', 'ü', 'ç'],
            ['E', 'E', 'E', 'E', 'A', 'A', 'I', 'I', 'O', 'U', 'U', 'C', 'A', 'O', 'U', 'A', 'A', 'A', 'A', 'E', 'E', 'E', 'E', 'I', 'I', 'O', 'O', 'U', 'U', 'U', 'C'],
            $normalized,
        );

        return preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
    }

    /**
     * @param list<\App\Catalog\Application\Roadmap\RoadmapParsedEvent> $events
     */
    private function containsDateRange(array $events, string $startsAt, string $endsAt): bool
    {
        [$expectedStartsAt, $expectedEndsAt] = $this->normalizeExpectedWindow($startsAt, $endsAt);
        foreach ($events as $event) {
            if (
                $event->startsAt->format('Y-m-d H:i:s') === $expectedStartsAt
                && $event->endsAt->format('Y-m-d H:i:s') === $expectedEndsAt
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<\App\Catalog\Application\Roadmap\RoadmapParsedEvent> $events
     * @param list<string>                                              $fragments
     */
    private function containsTitleFragments(array $events, array $fragments): bool
    {
        foreach ($events as $event) {
            $normalizedTitle = $this->normalizeTitleForAssert($event->title);
            $allPresent = true;
            foreach ($fragments as $fragment) {
                if (!str_contains($normalizedTitle, $this->normalizeTitleForAssert($fragment))) {
                    $allPresent = false;
                    break;
                }
            }

            if ($allPresent) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0:string,1:string}
     */
    private function normalizeExpectedWindow(string $startsAt, string $endsAt): array
    {
        $startDate = substr($startsAt, 0, 10);
        $endDate = substr($endsAt, 0, 10);

        if ($startDate === $endDate) {
            return [$startDate.' 16:00:00', $endDate.' 20:00:00'];
        }

        return [$startDate.' 18:00:00', $endDate.' 18:00:00'];
    }
}

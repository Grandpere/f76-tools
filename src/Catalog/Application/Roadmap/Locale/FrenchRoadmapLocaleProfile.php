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

namespace App\Catalog\Application\Roadmap\Locale;

final class FrenchRoadmapLocaleProfile implements RoadmapLocaleProfile
{
    public function supports(string $locale): bool
    {
        return str_starts_with(strtolower(trim($locale)), 'fr');
    }

    public function usesMonthFirstDates(): bool
    {
        return false;
    }

    public function monthMap(): array
    {
        return [
            1 => 'JANVIER',
            2 => 'FEVRIER',
            3 => 'MARS',
            4 => 'AVRIL',
            5 => 'MAI',
            6 => 'JUIN',
            7 => 'JUILLET',
            8 => 'AOUT',
            9 => 'SEPTEMBRE',
            10 => 'OCTOBRE',
            11 => 'NOVEMBRE',
            12 => 'DECEMBRE',
        ];
    }

    public function monthAliases(): array
    {
        return [
            'JAN' => 1,
            'FEV' => 2,
            'FEB' => 2,
            'MARS' => 3,
            'MAR' => 3,
            'AVR' => 4,
            'MAI' => 5,
            'JUN' => 6,
            'JUI' => 7,
            'JUIL' => 7,
            'JUILET' => 7,
            'JUHLET' => 7,
            'AOU' => 8,
            'SEP' => 9,
            'SEPT' => 9,
            'OCT' => 10,
            'NOV' => 11,
            'DEC' => 12,
        ];
    }

    public function normalizeTitle(string $title): string
    {
        $normalized = preg_replace('/\bF[ÉE]TE\b/u', 'FÊTE', $title) ?? $title;
        $upper = mb_strtoupper($normalized);
        $ascii = strtr($upper, [
            'À' => 'A',
            'Â' => 'A',
            'Ä' => 'A',
            'Ç' => 'C',
            'É' => 'E',
            'È' => 'E',
            'Ê' => 'E',
            'Ë' => 'E',
            'Î' => 'I',
            'Ï' => 'I',
            'Ô' => 'O',
            'Ö' => 'O',
            'Ù' => 'U',
            'Û' => 'U',
            'Ü' => 'U',
        ]);

        if (str_contains($ascii, 'FALLOUT DAY')) {
            return 'FALLOUT DAY';
        }
        if (
            str_contains($ascii, 'C.A.M.P')
            && str_contains($ascii, 'FLIPPANT')
            && str_contains($ascii, 'PUBLICS MUTANTS')
        ) {
            return 'FALLOUT DAY';
        }
        if (str_contains($ascii, 'ANNIVERSAIRE')) {
            return 'JOYEUX ANNIVERSAIRE FALLOUT 76 !';
        }
        if ('MISE A JOUR EVENEMENTS' === trim($ascii)) {
            return 'MISE À JOUR BORNE ZÉRO';
        }
        if ('EVENEMENTS PUBLICS' === trim($ascii)) {
            return 'ÉVÉNEMENTS PUBLICS MUTANTS';
        }
        if (str_contains($ascii, 'EVENEMENTS PUBLICS') && str_contains($ascii, 'MUTANTS')) {
            return 'ÉVÉNEMENTS PUBLICS MUTANTS';
        }
        if (str_contains($ascii, 'CAPSULES A GOGO') && str_contains($ascii, 'DOUBLES MUTATIONS')) {
            return 'WEEK-END CAPSULES À GOGO ET DOUBLES MUTATIONS';
        }
        if (str_contains($ascii, 'WEEK-END CAPSULES A GOGO')) {
            return 'WEEK-END CAPSULES À GOGO ET DOUBLES MUTATIONS';
        }
        if (str_contains($ascii, 'DOUBLE S.C.O.R.E.') && str_contains($ascii, 'FIEVRE DE L')) {
            return 'WEEK-END DOUBLE S.C.O.R.E. ET FIÈVRE DE L\'OR';
        }
        if (str_contains($ascii, 'FIEVRE DE L') && str_contains($ascii, 'WEEK-END')) {
            return 'WEEK-END FIÈVRE DE L\'OR ET DOUBLE MUTATION';
        }
        if (
            str_contains($ascii, 'CHOIX SPECIAL')
            && str_contains($ascii, 'EVENEMENTS')
            && !str_contains($ascii, 'S.C.O.R.E')
        ) {
            return 'CHOIX SPÉCIAL DE MURMRGH';
        }
        if (
            str_contains($ascii, 'DOUBLE S.C.O.R.E., DOUBLES')
            && (str_contains($ascii, 'SURPLUS') || str_contains($ascii, 'MITRAILLE'))
            && !str_contains($ascii, 'CHOIX SPECIAL')
        ) {
            return 'DOUBLE S.C.O.R.E., DOUBLES MUTATIONS ET SURPLUS DE MITRAILLE';
        }
        if ('DOUBLE S.C.O.R.E., DOUBLES' === trim($ascii)) {
            return 'DOUBLE S.C.O.R.E., DOUBLES MUTATIONS ET SURPLUS DE MITRAILLE';
        }
        if (str_contains($ascii, 'CHASSEUR DE TRESOR')) {
            return 'WEEK-END CHASSEUR DE TRÉSOR';
        }
        if ('EVENEMENT CALCINES' === trim($ascii)) {
            return 'ÉVÉNEMENT CALCINÉS DES FÊTES';
        }
        if (str_contains($ascii, 'EVENEMENT CALCINES') && str_contains($ascii, 'COMPETITION')) {
            return 'ÉVÉNEMENTS PUBLICS MUTANTS';
        }
        if (
            str_contains($ascii, 'DOUBLES MUTATIONS')
            && str_contains($ascii, 'PROMOTION')
            && str_contains($ascii, 'MARCHANDS')
        ) {
            return 'DOUBLES MUTATIONS ET PROMOTION LÉGENDAIRE DES MARCHANDS';
        }
        if (
            str_contains($ascii, 'CALCIN')
            && (str_contains($ascii, 'BONBONS') || str_contains($ascii, 'SORT'))
        ) {
            return 'ÉVÉNEMENT CALCINÉS EFFRAYANTS ET BONBONS OU UN SORT';
        }

        return $normalized;
    }
}

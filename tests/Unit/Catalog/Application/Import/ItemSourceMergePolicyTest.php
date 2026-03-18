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

namespace App\Tests\Unit\Catalog\Application\Import;

use App\Catalog\Application\Import\ItemSourceMergePolicy;
use App\Catalog\Domain\Entity\ItemEntity;
use App\Catalog\Domain\Item\ItemTypeEnum;
use PHPUnit\Framework\TestCase;

final class ItemSourceMergePolicyTest extends TestCase
{
    public function testMergeAppliesPreferredProvidersAndEquivalentNameRule(): void
    {
        $item = new ItemEntity();
        $item
            ->setType(ItemTypeEnum::BOOK)
            ->setSourceId(2161)
            ->setNameKey('item.book.2161.name');
        $item->upsertExternalSource('fandom', '00000871', 'https://fallout.fandom.com/wiki/Plan:_Assault_rifle_fierce_receiver', [
            'name_en' => 'Plan: Assault rifle fierce receiver',
            'containers' => true,
            'weight' => '0.25',
            'unlocks' => null,
        ]);
        $item->upsertExternalSource('fallout_wiki', '00000871', 'https://fallout.wiki/wiki/Plan%3A_Assault_Rifle_Fierce_Receiver', [
            'name_en' => 'Plan: Assault Rifle Fierce Receiver',
            'containers' => null,
            'weight' => null,
            'unlocks' => ['text' => 'Fierce receiver'],
        ]);

        $policy = new ItemSourceMergePolicy();
        $result = $policy->merge($item, 'fandom', 'fallout_wiki');

        self::assertNotNull($result);
        self::assertSame('Plan: Assault Rifle Fierce Receiver', $result->label);
        self::assertCount(0, $result->conflicts);

        $decisions = [];
        foreach ($result->decisions as $decision) {
            $decisions[$decision->field] = $decision;
        }

        self::assertSame('fallout_wiki', $decisions['name_en']->provider);
        self::assertSame('equivalent_text_prefer_provider_b', $decisions['name_en']->reason);
        self::assertSame('fandom', $decisions['containers']->provider);
        self::assertSame('fallback_single_source', $decisions['containers']->reason);
        self::assertSame('fallout_wiki', $decisions['unlocks']->provider);
        self::assertSame('fallback_single_source', $decisions['unlocks']->reason);
    }

    public function testMergeKeepsNameConflictWhenProvidersReallyDisagree(): void
    {
        $item = new ItemEntity();
        $item
            ->setType(ItemTypeEnum::BOOK)
            ->setSourceId(42)
            ->setNameKey('item.book.42.name');
        $item->upsertExternalSource('fandom', '42', null, [
            'name_en' => 'Recipe: Refreshing beverage',
        ]);
        $item->upsertExternalSource('fallout_wiki', '42', null, [
            'name_en' => "Recipe: Delbert's Company Tea",
        ]);

        $policy = new ItemSourceMergePolicy();
        $result = $policy->merge($item, 'fandom', 'fallout_wiki');

        self::assertNotNull($result);
        self::assertCount(1, $result->conflicts);
        self::assertSame('name_en', $result->conflicts[0]->field);
        self::assertSame('name_values_diverge', $result->conflicts[0]->reason);
    }

    public function testMergePrefersSpecificParentheticalNameVariant(): void
    {
        $item = new ItemEntity();
        $item
            ->setType(ItemTypeEnum::BOOK)
            ->setSourceId(2853828)
            ->setNameKey('item.book.2853828.name');
        $item->upsertExternalSource('fandom', '002B8BC4', 'https://fallout.fandom.com/wiki/Recipe:_Healing_salve_(Toxic_Valley)', [
            'name_en' => 'Recipe: Healing salve (Toxic Valley)',
        ]);
        $item->upsertExternalSource('fallout_wiki', '002B8BC4', 'https://fallout.wiki/wiki/Recipe:_Healing_Salve_(Toxic_Valley)', [
            'name_en' => 'Recipe: Healing Salve',
        ]);

        $policy = new ItemSourceMergePolicy();
        $result = $policy->merge($item, 'fandom', 'fallout_wiki');

        self::assertNotNull($result);
        self::assertCount(0, $result->conflicts);

        $decisions = [];
        foreach ($result->decisions as $decision) {
            $decisions[$decision->field] = $decision;
        }

        self::assertSame('fandom', $decisions['name_en']->provider);
        self::assertSame('generic_label_confirmed_by_specific_target', $decisions['name_en']->reason);
    }

    public function testMergeNormalizesPurchaseCurrencyAcrossProviders(): void
    {
        $item = new ItemEntity();
        $item
            ->setType(ItemTypeEnum::BOOK)
            ->setSourceId(9001)
            ->setNameKey('item.book.9001.name');
        $item->upsertExternalSource('fandom', '9001', null, [
            'value_currency' => 'Bottle cap',
        ]);
        $item->upsertExternalSource('fallout_wiki', '9001', null, [
            'type' => 'caps',
        ]);

        $policy = new ItemSourceMergePolicy();
        $result = $policy->merge($item, 'fandom', 'fallout_wiki');

        self::assertNotNull($result);

        $decisions = [];
        foreach ($result->decisions as $decision) {
            $decisions[$decision->field] = $decision;
        }

        self::assertArrayHasKey('purchase_currency', $decisions);
        self::assertSame('fandom', $decisions['purchase_currency']->provider);
        self::assertSame('caps', $decisions['purchase_currency']->value);
        self::assertSame('equivalent_purchase_currency_prefer_provider_a', $decisions['purchase_currency']->reason);
        self::assertArrayNotHasKey('value_currency', $decisions);
        self::assertArrayNotHasKey('type', $decisions);
    }
}

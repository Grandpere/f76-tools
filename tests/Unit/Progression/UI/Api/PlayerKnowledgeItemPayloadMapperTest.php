<?php

declare(strict_types=1);

namespace App\Tests\Unit\Progression\UI\Api;

use App\Domain\Item\ItemTypeEnum;
use App\Entity\ItemEntity;
use App\Progression\UI\Api\PlayerKnowledgeItemPayloadMapper;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PlayerKnowledgeItemPayloadMapperTest extends TestCase
{
    public function testMapBuildsExpectedPayloadWithTranslationsAndLists(): void
    {
        $item = (new ItemEntity())
            ->setSourceId(777)
            ->setType(ItemTypeEnum::BOOK)
            ->setNameKey('catalog.book.name')
            ->setDescKey('catalog.book.desc')
            ->setRank(null)
            ->setPrice(250)
            ->setPriceMinerva(188)
            ->setIsNew(true)
            ->setDropDailyOps(true)
            ->setVendorSamuel(true)
            ->setInfoHtml('<p>info</p>')
            ->setDropSourcesHtml('<img src="/img/drop.png">')
            ->setRelationsHtml('<img src="/img/relation.png">');
        $item->addBookList(4, true);
        $item->addBookList(1, false);
        $this->setItemPublicId($item, '01J4C4ZQ5V7Y4M8N2B6D9K3P1R');

        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->expects(self::exactly(2))
            ->method('trans')
            ->willReturnMap([
                ['catalog.book.desc', [], 'items', null, 'Desc translated'],
                ['catalog.book.name', [], 'items', null, 'Name translated'],
            ]);

        $mapper = new PlayerKnowledgeItemPayloadMapper($translator);
        $payload = $mapper->map($item, true);

        self::assertSame('01J4C4ZQ5V7Y4M8N2B6D9K3P1R', $payload['id']);
        self::assertSame(777, $payload['sourceId']);
        self::assertSame('BOOK', $payload['type']);
        self::assertSame('catalog.book.name', $payload['nameKey']);
        self::assertSame('Name translated', $payload['name']);
        self::assertSame('catalog.book.desc', $payload['descKey']);
        self::assertSame('Desc translated', $payload['description']);
        self::assertTrue($payload['isNew']);
        self::assertSame(250, $payload['price']);
        self::assertSame(188, $payload['priceMinerva']);
        self::assertTrue($payload['dropDailyOps']);
        self::assertTrue($payload['vendorSamuel']);
        self::assertSame('<p>info</p>', $payload['infoHtml']);
        self::assertSame('<img src="/img/drop.png">', $payload['dropSourcesHtml']);
        self::assertSame('<img src="/img/relation.png">', $payload['relationsHtml']);
        self::assertSame([1, 4], $payload['listNumbers']);
        self::assertTrue($payload['isInSpecialList']);
        self::assertTrue($payload['learned']);
    }

    public function testMapSkipsDescriptionTranslationWhenDescKeyIsMissing(): void
    {
        $item = (new ItemEntity())
            ->setSourceId(888)
            ->setType(ItemTypeEnum::MISC)
            ->setNameKey('catalog.misc.name')
            ->setDescKey(null)
            ->setRank(3);
        $this->setItemPublicId($item, '01J4D0Y7QH2K9P5V3M1N8B6R4T');

        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->expects(self::once())
            ->method('trans')
            ->with('catalog.misc.name', [], 'items')
            ->willReturn('Misc translated');

        $mapper = new PlayerKnowledgeItemPayloadMapper($translator);
        $payload = $mapper->map($item, false);

        self::assertNull($payload['descKey']);
        self::assertNull($payload['description']);
        self::assertSame('Misc translated', $payload['name']);
        self::assertSame([], $payload['listNumbers']);
        self::assertFalse($payload['isInSpecialList']);
        self::assertFalse($payload['learned']);
    }

    public function testMapCatalogRowsMapsEachRow(): void
    {
        $first = (new ItemEntity())
            ->setSourceId(1)
            ->setType(ItemTypeEnum::MISC)
            ->setNameKey('catalog.first.name')
            ->setDescKey(null)
            ->setRank(1);
        $this->setItemPublicId($first, '01J4D0Y7QH2K9P5V3M1N8B6R4A');

        $second = (new ItemEntity())
            ->setSourceId(2)
            ->setType(ItemTypeEnum::BOOK)
            ->setNameKey('catalog.second.name')
            ->setDescKey(null)
            ->setRank(null);
        $this->setItemPublicId($second, '01J4D0Y7QH2K9P5V3M1N8B6R4B');

        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->expects(self::exactly(2))
            ->method('trans')
            ->willReturnMap([
                ['catalog.first.name', [], 'items', null, 'First'],
                ['catalog.second.name', [], 'items', null, 'Second'],
            ]);

        $mapper = new PlayerKnowledgeItemPayloadMapper($translator);
        $payload = $mapper->mapCatalogRows([
            ['item' => $first, 'learned' => true],
            ['item' => $second, 'learned' => false],
        ]);

        self::assertCount(2, $payload);
        self::assertSame('01J4D0Y7QH2K9P5V3M1N8B6R4A', $payload[0]['id']);
        self::assertTrue($payload[0]['learned']);
        self::assertSame('01J4D0Y7QH2K9P5V3M1N8B6R4B', $payload[1]['id']);
        self::assertFalse($payload[1]['learned']);
    }

    private function setItemPublicId(ItemEntity $item, string $publicId): void
    {
        $reflection = new \ReflectionProperty(ItemEntity::class, 'publicId');
        $reflection->setValue($item, $publicId);
    }
}

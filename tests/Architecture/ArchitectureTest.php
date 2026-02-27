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

namespace App\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

final class ArchitectureTest
{
    public function testAppDoesNotDependOnTests(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('/^App\\\\(?!Tests\\\\)/', true))
            ->shouldNotDependOn()
            ->classes(Selector::inNamespace('App\\Tests'))
            ->because('production code should not depend on test code');
    }

    public function testAppDoesNotDependOnLegacyRootNamespaces(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('/^App\\\\(?!Tests\\\\)/', true))
            ->shouldNotDependOn()
            ->classes(
                Selector::inNamespace('/^App\\\\Command\\\\/', true),
                Selector::inNamespace('/^App\\\\Contract\\\\/', true),
                Selector::inNamespace('/^App\\\\Controller\\\\/', true),
                Selector::inNamespace('/^App\\\\Domain\\\\/', true),
                Selector::inNamespace('/^App\\\\Entity\\\\/', true),
                Selector::inNamespace('/^App\\\\EventSubscriber\\\\/', true),
                Selector::inNamespace('/^App\\\\Repository\\\\/', true),
                Selector::inNamespace('/^App\\\\Security\\\\/', true),
                Selector::inNamespace('/^App\\\\Service\\\\/', true),
            )
            ->because('legacy root namespaces are forbidden after DDD migration');
    }
}

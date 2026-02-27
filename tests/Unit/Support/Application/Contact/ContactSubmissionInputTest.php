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

namespace App\Tests\Unit\Support\Application\Contact;

use App\Support\Application\Contact\ContactSubmissionInput;
use PHPUnit\Framework\TestCase;

final class ContactSubmissionInputTest extends TestCase
{
    public function testCreateNormalizesInput(): void
    {
        $input = ContactSubmissionInput::create('  USER@Example.COM ', '  Subject  ', '  Message long enough.  ');

        self::assertSame('user@example.com', $input->email);
        self::assertSame('Subject', $input->subject);
        self::assertSame('Message long enough.', $input->message);
    }

    public function testIsValidReturnsTrueForValidInput(): void
    {
        $input = ContactSubmissionInput::create('user@example.com', 'Need help', 'Message long enough.');

        self::assertTrue($input->isValid());
    }

    public function testIsValidReturnsFalseForInvalidInput(): void
    {
        self::assertFalse(ContactSubmissionInput::create('invalid-email', 'Need help', 'Message long enough.')->isValid());
        self::assertFalse(ContactSubmissionInput::create('user@example.com', 'No', 'Message long enough.')->isValid());
        self::assertFalse(ContactSubmissionInput::create('user@example.com', 'Need help', 'Too short')->isValid());
    }
}

<?php

/**
 * This file is part of the Phalcon Framework.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Phalcon\Tests\Unit\DebugBar\History;

use Phalcon\DebugBar\History\HistoryEntry;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;

final class HistoryEntryTest extends AbstractUnitTestCase
{
    public function testMetadataBoundaryValidatesEveryDocumentedField(): void
    {
        $metadata = [
            'requested_at' => '2026-09-14T10:00:00+00:00',
            'method'       => 'GET',
            'uri'          => '/',
            'status'       => 200,
            'ajax'         => false,
            'id'           => '20260914100000-000000-deadbeef',
            'stored_at'    => '2026-09-14T10:00:00+00:00',
        ];

        $this->assertSame($metadata, HistoryEntry::metadataFromArray($metadata));

        $invalidValues = [
            'requested_at' => false,
            'method'       => false,
            'uri'          => false,
            'status'       => '200',
            'ajax'         => 0,
            'id'           => false,
            'stored_at'    => false,
        ];

        foreach ($invalidValues as $field => $invalidValue) {
            $invalidMetadata         = $metadata;
            $invalidMetadata[$field] = $invalidValue;

            $this->assertNull(
                HistoryEntry::metadataFromArray($invalidMetadata),
                'Expected invalid metadata field: ' . $field,
            );
        }
    }

    public function testRejectsAnInvalidPayloadShape(): void
    {
        $entry = [
            'version' => HistoryEntry::VERSION,
            'meta'    => [
                'requested_at' => '2026-09-14T10:00:00+00:00',
                'method'       => 'GET',
                'uri'          => '/',
                'status'       => 200,
                'ajax'         => false,
                'id'           => '20260914100000-000000-deadbeef',
                'stored_at'    => '2026-09-14T10:00:00+00:00',
            ],
            'payload' => ['data' => null, 'meta' => []],
        ];

        $this->assertNull(HistoryEntry::fromArray($entry));
    }
}

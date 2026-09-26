<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Relay;

use Ayimdomnic\Laragraph\Relay\GlobalId;
use PHPUnit\Framework\TestCase;

class GlobalIdTest extends TestCase
{
    public function test_encode_decode_round_trip(): void
    {
        $globalId = GlobalId::encode('User', 42);

        $this->assertSame(['type' => 'User', 'id' => '42'], GlobalId::decode($globalId));
    }

    public function test_encode_accepts_string_ids(): void
    {
        $globalId = GlobalId::encode('Post', 'abc-123');

        $this->assertSame(['type' => 'Post', 'id' => 'abc-123'], GlobalId::decode($globalId));
    }

    public function test_decode_rejects_non_base64_input(): void
    {
        $this->assertNull(GlobalId::decode('not valid base64!!'));
    }

    public function test_decode_rejects_a_decoded_value_with_no_separator(): void
    {
        $this->assertNull(GlobalId::decode(base64_encode('NoSeparatorHere')));
    }

    public function test_decode_rejects_an_empty_type_or_id(): void
    {
        $this->assertNull(GlobalId::decode(base64_encode(':42')));
        $this->assertNull(GlobalId::decode(base64_encode('User:')));
    }

    public function test_decode_keeps_the_remainder_of_the_id_when_it_contains_colons(): void
    {
        $globalId = base64_encode('User:has:colons');

        $this->assertSame(['type' => 'User', 'id' => 'has:colons'], GlobalId::decode($globalId));
    }
}

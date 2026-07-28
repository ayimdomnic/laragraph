<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Scalars;

use Ayimdomnic\Laragraph\Scalars\UploadType;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Error\Error;
use Illuminate\Http\UploadedFile;

class UploadTypeTest extends TestCase
{
    use AstNodeFactory;

    private UploadType $scalar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scalar = new UploadType();
    }

    public function test_serialize_always_throws(): void
    {
        $this->expectException(Error::class);
        $this->scalar->serialize('anything');
    }

    public function test_parse_literal_always_throws(): void
    {
        $this->expectException(Error::class);
        $this->scalar->parseLiteral($this->strNode('file.txt'));
    }

    public function test_parse_value_with_uploaded_file(): void
    {
        $file   = UploadedFile::fake()->create('avatar.jpg', 100);
        $result = $this->scalar->parseValue($file);
        $this->assertSame($file, $result);
    }

    public function test_parse_value_with_invalid_type_throws(): void
    {
        $this->expectException(Error::class);
        $this->scalar->parseValue('not-a-file');
    }
}

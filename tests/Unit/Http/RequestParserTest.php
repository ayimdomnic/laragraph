<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Http;

use Ayimdomnic\Laragraph\Exceptions\RequestException;
use Ayimdomnic\Laragraph\Http\RequestParser;
use Ayimdomnic\Laragraph\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;

class RequestParserTest extends TestCase
{
    private function parser(): RequestParser
    {
        return new RequestParser();
    }

    // -------------------------------------------------------------------------
    // parse()
    // -------------------------------------------------------------------------

    public function test_parses_a_get_request(): void
    {
        $request = Request::create('/graphql', 'GET', ['query' => '{ hello }', 'operationName' => 'Hello']);

        $parsed = $this->parser()->parse($request);

        $this->assertSame('{ hello }', $parsed['query']);
        $this->assertSame('Hello', $parsed['operationName']);
    }

    public function test_parses_a_json_post_body(): void
    {
        $request = Request::create('/graphql', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{"query":"{ hello }"}');

        $parsed = $this->parser()->parse($request);

        $this->assertSame('{ hello }', $parsed['query']);
    }

    public function test_rejects_a_non_object_json_body(): void
    {
        $request = Request::create('/graphql', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], '"not an object or list"');

        $this->expectException(RequestException::class);
        $this->parser()->parse($request);
    }

    public function test_parses_an_application_graphql_body(): void
    {
        $request = Request::create('/graphql', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/graphql'], '{ hello }');

        $parsed = $this->parser()->parse($request);

        $this->assertSame('{ hello }', $parsed['query']);
    }

    public function test_parses_a_form_urlencoded_body(): void
    {
        $request = Request::create('/graphql', 'POST', ['query' => '{ hello }'], [], [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $parsed = $this->parser()->parse($request);

        $this->assertSame('{ hello }', $parsed['query']);
    }

    // -------------------------------------------------------------------------
    // parse() — multipart
    // -------------------------------------------------------------------------

    public function test_parses_a_multipart_request_and_attaches_the_file(): void
    {
        $file = new UploadedFile(
            (new SymfonyUploadedFile(__FILE__, 'doc.txt', test: true))->getPathname(),
            'doc.txt',
            test: true,
        );

        $request = Request::create('/graphql', 'POST', [
            'operations' => json_encode(['query' => 'mutation ($file: Upload!) { upload(file: $file) }', 'variables' => ['file' => null]]),
            'map'        => json_encode(['0' => ['variables.file']]),
        ], [], ['0' => $file], ['CONTENT_TYPE' => 'multipart/form-data; boundary=----Boundary']);

        $parsed = $this->parser()->parse($request);

        $this->assertSame($file, $parsed['variables']['file']);
    }

    public function test_multipart_map_path_must_point_into_variables(): void
    {
        $request = Request::create('/graphql', 'POST', [
            'operations' => json_encode(['query' => '{ hello }']),
            'map'        => json_encode(['0' => ['notVariables.file']]),
        ], [], [], ['CONTENT_TYPE' => 'multipart/form-data; boundary=----Boundary']);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('must point into `variables`');
        $this->parser()->parse($request);
    }

    public function test_multipart_requires_json_operations_and_map(): void
    {
        $request = Request::create('/graphql', 'POST', [], [], [], ['CONTENT_TYPE' => 'multipart/form-data; boundary=----Boundary']);

        $this->expectException(RequestException::class);
        $this->parser()->parse($request);
    }

    // -------------------------------------------------------------------------
    // assertOperation()
    // -------------------------------------------------------------------------

    public function test_assert_operation_accepts_a_well_formed_operation(): void
    {
        $this->parser()->assertOperation(['query' => '{ hello }', 'operationName' => 'Hello', 'variables' => ['a' => 1]]);
        $this->addToAssertionCount(1);
    }

    public function test_assert_operation_rejects_a_non_object(): void
    {
        $this->expectException(RequestException::class);
        $this->parser()->assertOperation('not an object');
    }

    public function test_assert_operation_rejects_a_list(): void
    {
        $this->expectException(RequestException::class);
        $this->parser()->assertOperation([['query' => '{ a }']]);
    }

    public function test_assert_operation_rejects_a_non_string_query(): void
    {
        $this->expectException(RequestException::class);
        $this->parser()->assertOperation(['query' => 123]);
    }

    public function test_assert_operation_rejects_variables_that_are_not_json(): void
    {
        $this->expectException(RequestException::class);
        $this->parser()->assertOperation(['variables' => 'not json']);
    }

    public function test_assert_operation_accepts_variables_as_a_json_string(): void
    {
        $this->parser()->assertOperation(['variables' => '{"a":1}']);
        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // castVariables()
    // -------------------------------------------------------------------------

    public function test_cast_variables_decodes_a_json_string(): void
    {
        $this->assertSame(['a' => 1], $this->parser()->castVariables('{"a":1}'));
    }

    public function test_cast_variables_passes_through_an_array(): void
    {
        $this->assertSame(['a' => 1], $this->parser()->castVariables(['a' => 1]));
    }

    public function test_cast_variables_defaults_to_empty_array(): void
    {
        $this->assertSame([], $this->parser()->castVariables(null));
        $this->assertSame([], $this->parser()->castVariables('not json'));
    }
}

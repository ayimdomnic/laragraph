<?php

declare(strict_types=1);

namespace Tests\Feature\GraphQL;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The GraphQL multipart request spec: `operations` (JSON), `map` (JSON) and
 * the files themselves as multipart fields.
 */
class FileUploadTest extends GraphQLTestCase
{
    private function upload(UploadedFile $file): array
    {
        return $this->withHeaders($this->headersFor($this->member))->post('/graphql', [
            'operations' => json_encode([
                'query' => 'mutation ($file: Upload!) { uploadAvatar(file: $file) { avatarUrl } }',
                'variables' => ['file' => null],
            ]),
            'map' => json_encode(['0' => ['variables.file']]),
            '0' => $file,
        ], ['Content-Type' => 'multipart/form-data'])->json();
    }

    public function test_avatars_are_uploaded_and_stored(): void
    {
        Storage::fake('public');

        $result = $this->upload(UploadedFile::fake()->image('me.png', 64, 64));

        $this->assertStringContainsString('/storage/avatars/', $result['data']['uploadAvatar']['avatarUrl']);
        Storage::disk('public')->assertExists($this->member->fresh()->avatar_path);
    }

    public function test_uploads_are_validated(): void
    {
        Storage::fake('public');

        $result = $this->upload(UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'));

        $this->assertSame('The file field must be an image.', $result['errors'][0]['extensions']['validation']['file'][0]);
    }
}

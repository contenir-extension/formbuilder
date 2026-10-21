<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Unit\Admin\Preview;

use Contenir\Extension\Formbuilder\Admin\Preview\UploadedFiles;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\UploadedFile;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

use const UPLOAD_ERR_OK;

#[Group('unit')]
final class UploadedFilesTest extends TestCase
{
    #[Test]
    public function givesEachUploadInPhpsShapeAndLeavesOutTheRest(): void
    {
        $stream  = (new Psr17Factory())->createStream('PDF');
        $request = (new ServerRequest('POST', '/'))->withUploadedFiles([
            'other' => ['nested' => 'not an upload'],
            'cv'    => new UploadedFile($stream, 3, UPLOAD_ERR_OK, 'cv.pdf', 'application/pdf'),
            'photo' => new UploadedFile($stream, 3, UPLOAD_ERR_OK, 'me.jpg', 'image/jpeg'),
        ]);

        static::assertSame(
            [
                'cv'    => [
                    'name'     => 'cv.pdf',
                    'type'     => 'application/pdf',
                    'tmp_name' => 'php://memory',
                    'error'    => UPLOAD_ERR_OK,
                    'size'     => 3,
                ],
                'photo' => [
                    'name'     => 'me.jpg',
                    'type'     => 'image/jpeg',
                    'tmp_name' => 'php://memory',
                    'error'    => UPLOAD_ERR_OK,
                    'size'     => 3,
                ],
            ],
            UploadedFiles::of($request),
        );
    }

    #[Test]
    public function givesNoPathForAStreamWithout(): void
    {
        $stream = $this->createStub(StreamInterface::class);
        $stream->method('getMetadata')->willReturn(null);
        $request = (new ServerRequest('POST', '/'))->withUploadedFiles([
            'cv' => new UploadedFile($stream, 0, UPLOAD_ERR_OK),
        ]);

        static::assertSame('', UploadedFiles::of($request)['cv']['tmp_name'] ?? null);
    }
}

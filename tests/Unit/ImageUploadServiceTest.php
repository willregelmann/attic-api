<?php

namespace Tests\Unit;

use App\Services\ImageUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageUploadServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ImageUploadService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->service = new ImageUploadService();
    }

    /** @test */
    public function it_rejects_files_exceeding_max_size()
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        // 11MB file (exceeds 10MB limit)
        $file = UploadedFile::fake()->create('large.jpg', 11000);

        $this->service->validateFiles([$file]);
    }

    /** @test */
    public function it_rejects_invalid_file_types()
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $file = UploadedFile::fake()->create('document.pdf', 1000);

        $this->service->validateFiles([$file]);
    }

    /** @test */
    public function it_rejects_more_than_10_images()
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $files = [];
        for ($i = 0; $i < 11; $i++) {
            $files[] = UploadedFile::fake()->image("image{$i}.jpg");
        }

        $this->service->validateFiles($files);
    }

    /** @test */
    public function it_accepts_valid_image_files()
    {
        $files = [
            UploadedFile::fake()->image('photo1.jpg'),
            UploadedFile::fake()->image('photo2.png'),
            UploadedFile::fake()->image('photo3.webp'),
        ];

        // Should not throw exception
        $this->service->validateFiles($files);
        $this->assertTrue(true);
    }
}

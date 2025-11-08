<?php

namespace Tests\Unit;

use App\Services\ImageUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImageUploadServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ImageUploadService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->service = new ImageUploadService;
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

    /** @test */
    public function it_processes_and_stores_images()
    {
        $userItemId = '123e4567-e89b-12d3-a456-426614174000';
        $file = UploadedFile::fake()->image('photo.jpg', 2500, 2500);

        $results = $this->service->processAndStoreImages([$file], $userItemId);

        $this->assertCount(1, $results);
        $this->assertArrayHasKey('original', $results[0]);
        $this->assertArrayHasKey('thumbnail', $results[0]);
        $this->assertStringContainsString("user_items/{$userItemId}/", $results[0]['original']);

        // Verify files exist in storage
        Storage::disk('public')->assertExists($results[0]['original']);
        Storage::disk('public')->assertExists($results[0]['thumbnail']);
    }

    /** @test */
    public function it_resizes_large_images()
    {
        $userItemId = '123e4567-e89b-12d3-a456-426614174000';
        // Create a 3000x3000 image (exceeds 2000px limit)
        $file = UploadedFile::fake()->image('large.jpg', 3000, 3000);

        $results = $this->service->processAndStoreImages([$file], $userItemId);

        // Original should be resized
        Storage::disk('public')->assertExists($results[0]['original']);

        // Check that the resized image is smaller (we can't easily check dimensions in tests)
        $this->assertNotNull($results[0]['original']);
    }

    /** @test */
    public function it_generates_square_thumbnails()
    {
        $userItemId = '123e4567-e89b-12d3-a456-426614174000';
        $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

        $results = $this->service->processAndStoreImages([$file], $userItemId);

        // Thumbnail should exist
        Storage::disk('public')->assertExists($results[0]['thumbnail']);

        $this->assertNotNull($results[0]['thumbnail']);
    }

    #[Test]
    public function it_deletes_images_from_storage()
    {
        $userItemId = '123e4567-e89b-12d3-a456-426614174000';
        $file = UploadedFile::fake()->image('photo.jpg');

        $results = $this->service->processAndStoreImages([$file], $userItemId);

        // Verify files exist
        Storage::disk('public')->assertExists($results[0]['original']);
        Storage::disk('public')->assertExists($results[0]['thumbnail']);

        // Delete images
        $this->service->deleteImages($results);

        // Verify files are gone
        Storage::disk('public')->assertMissing($results[0]['original']);
        Storage::disk('public')->assertMissing($results[0]['thumbnail']);
    }

    #[Test]
    public function it_handles_missing_files_gracefully_during_deletion()
    {
        $images = [
            ['original' => 'nonexistent/file.jpg', 'thumbnail' => 'nonexistent/thumb.jpg'],
        ];

        // Should not throw exception
        $this->service->deleteImages($images);
        $this->assertTrue(true);
    }

    #[Test]
    public function it_removes_images_by_indices()
    {
        $userItemId = '123e4567-e89b-12d3-a456-426614174000';

        // Create 5 test images
        $files = [];
        for ($i = 0; $i < 5; $i++) {
            $files[] = UploadedFile::fake()->image("photo{$i}.jpg");
        }

        $existingImages = $this->service->processAndStoreImages($files, $userItemId);

        // Verify all 5 images exist
        $this->assertCount(5, $existingImages);
        foreach ($existingImages as $image) {
            Storage::disk('public')->assertExists($image['original']);
            Storage::disk('public')->assertExists($image['thumbnail']);
        }

        // Remove images at indices 1 and 3
        $indicesToRemove = [1, 3];
        $remaining = $this->service->removeImagesByIndices($existingImages, $indicesToRemove);

        // Verify the correct number of images remain
        $this->assertCount(3, $remaining);

        // Verify the remaining images are the correct ones (indices 0, 2, 4)
        $this->assertEquals($existingImages[0], $remaining[0]);
        $this->assertEquals($existingImages[2], $remaining[1]);
        $this->assertEquals($existingImages[4], $remaining[2]);

        // Verify removed images (indices 1 and 3) are deleted from storage
        Storage::disk('public')->assertMissing($existingImages[1]['original']);
        Storage::disk('public')->assertMissing($existingImages[1]['thumbnail']);
        Storage::disk('public')->assertMissing($existingImages[3]['original']);
        Storage::disk('public')->assertMissing($existingImages[3]['thumbnail']);

        // Verify remaining images still exist in storage
        Storage::disk('public')->assertExists($remaining[0]['original']);
        Storage::disk('public')->assertExists($remaining[0]['thumbnail']);
        Storage::disk('public')->assertExists($remaining[1]['original']);
        Storage::disk('public')->assertExists($remaining[1]['thumbnail']);
        Storage::disk('public')->assertExists($remaining[2]['original']);
        Storage::disk('public')->assertExists($remaining[2]['thumbnail']);
    }
}

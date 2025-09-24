<?php

namespace App\GraphQL\Mutations;

use App\Models\Item;
use App\Models\ItemImage;
use App\Models\ItemRelationship;
use App\Models\CollectionMaintainer;
use App\Services\ImageStorageService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class CollectionMutations
{
    public function createCollection($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();
        
        return DB::transaction(function () use ($args, $user) {
            // Create the collection
            $collection = Item::create([
                'type' => 'collection',
                'name' => $args['name'],
                'metadata' => $args['metadata'] ?? null,
            ]);
            
            // Make the creator a maintainer
            CollectionMaintainer::create([
                'collection_id' => $collection->id,
                'user_id' => $user->id,
                'role' => 'owner',
                'permissions' => json_encode(['*']), // Full permissions
            ]);
            
            return $collection;
        });
    }
    
    public function updateCollection($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();
        $collection = Item::findOrFail($args['id']);
        
        // Check if user is a maintainer
        $maintainer = CollectionMaintainer::where('collection_id', $collection->id)
            ->where('user_id', $user->id)
            ->first();
            
        if (!$maintainer) {
            throw new \Exception('You do not have permission to update this collection');
        }
        
        // Update collection
        if (isset($args['name'])) {
            $collection->name = $args['name'];
        }
        
        if (isset($args['metadata'])) {
            $collection->metadata = array_merge($collection->metadata ?? [], $args['metadata']);
        }
        
        $collection->save();
        
        return $collection;
    }
    
    public function deleteCollection($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();
        $collection = Item::findOrFail($args['id']);
        
        // Check if user is an owner
        $maintainer = CollectionMaintainer::where('collection_id', $collection->id)
            ->where('user_id', $user->id)
            ->where('role', 'owner')
            ->first();
            
        if (!$maintainer) {
            throw new \Exception('Only collection owners can delete collections');
        }
        
        // Delete collection and all relationships
        DB::transaction(function () use ($collection) {
            // Delete maintainers
            CollectionMaintainer::where('collection_id', $collection->id)->delete();
            
            // Delete relationships
            ItemRelationship::where('parent_id', $collection->id)->delete();
            ItemRelationship::where('child_id', $collection->id)->delete();
            
            // Delete the collection
            $collection->delete();
        });
        
        return 'Collection deleted successfully';
    }
    
    public function addItemToCollection($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();
        $collection = Item::findOrFail($args['collection_id']);
        
        // Check if user is a maintainer
        $maintainer = CollectionMaintainer::where('collection_id', $collection->id)
            ->where('user_id', $user->id)
            ->first();
            
        if (!$maintainer) {
            throw new \Exception('You do not have permission to modify this collection');
        }
        
        // Check if item exists
        $item = Item::findOrFail($args['item_id']);
        
        // Check if relationship already exists
        $existing = ItemRelationship::where('parent_id', $collection->id)
            ->where('child_id', $item->id)
            ->where('relationship_type', 'contains')
            ->first();
            
        if ($existing) {
            throw new \Exception('Item is already in this collection');
        }
        
        // Get the next canonical order if not provided
        if (!isset($args['canonical_order'])) {
            $maxOrder = ItemRelationship::where('parent_id', $collection->id)
                ->where('relationship_type', 'contains')
                ->max('canonical_order');
            $args['canonical_order'] = ($maxOrder ?? 0) + 1;
        }
        
        // Create relationship
        return ItemRelationship::create([
            'parent_id' => $collection->id,
            'child_id' => $item->id,
            'relationship_type' => 'contains',
            'canonical_order' => $args['canonical_order'],
            'metadata' => $args['metadata'] ?? null,
        ]);
    }
    
    public function removeItemFromCollection($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();
        $collection = Item::findOrFail($args['collection_id']);
        
        // Check if user is a maintainer
        $maintainer = CollectionMaintainer::where('collection_id', $collection->id)
            ->where('user_id', $user->id)
            ->first();
            
        if (!$maintainer) {
            throw new \Exception('You do not have permission to modify this collection');
        }
        
        // Delete relationship
        $deleted = ItemRelationship::where('parent_id', $collection->id)
            ->where('child_id', $args['item_id'])
            ->where('relationship_type', 'contains')
            ->delete();
            
        if (!$deleted) {
            throw new \Exception('Item not found in collection');
        }
        
        return 'Item removed from collection successfully';
    }
    
    public function uploadCollectionImage($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();
        $collection = Item::findOrFail($args['collection_id']);
        
        // Check if user is a maintainer
        $maintainer = CollectionMaintainer::where('collection_id', $collection->id)
            ->where('user_id', $user->id)
            ->first();
            
        if (!$maintainer) {
            throw new \Exception('You do not have permission to modify this collection');
        }
        
        return DB::transaction(function () use ($collection, $args, $user) {
            // Remove existing primary image if exists
            ItemImage::where('item_id', $collection->id)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
            
            // Decode base64 image data
            $imageData = $args['image_data'];
            $filename = $args['filename'];
            $mimeType = $args['mime_type'];
            
            // Remove base64 prefix if present
            if (strpos($imageData, 'base64,') !== false) {
                $imageData = substr($imageData, strpos($imageData, 'base64,') + 7);
            }
            
            $decodedImage = base64_decode($imageData);
            
            // Validate it's actually an image
            if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
                throw new \Exception('Invalid image type. Only JPEG, PNG, GIF, and WebP are allowed.');
            }
            
            // Generate unique filename
            $extension = match($mimeType) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                default => 'jpg'
            };
            
            $safeFilename = \Illuminate\Support\Str::slug($collection->name) . '-' . uniqid() . '.' . $extension;
            $directory = 'images/collections';
            $path = $directory . '/' . $safeFilename;
            
            // Determine storage disk based on environment
            $disk = env('FILESYSTEM_DISK', 'public');
            $storage = \Illuminate\Support\Facades\Storage::disk($disk);
            
            // Ensure directory exists
            if (!$storage->exists($directory)) {
                $storage->makeDirectory($directory);
            }
            
            // Store the image
            $storage->put($path, $decodedImage, 'public');
            
            // Generate public URL based on disk and environment
            if ($disk === 's3' || $disk === 'r2') {
                $publicUrl = $storage->url($path);
            } else {
                // For local/Railway storage, use the /storage route
                $baseUrl = config('app.url');
                
                // Fallback to request URL if APP_URL is not properly configured
                if (!$baseUrl || $baseUrl === 'http://localhost') {
                    $baseUrl = request()->getSchemeAndHttpHost();
                }
                
                $baseUrl = rtrim($baseUrl, '/');
                $publicUrl = $baseUrl . '/storage/' . $path;
            }
            
            // Create or update the image record
            $image = ItemImage::create([
                'item_id' => $collection->id,
                'user_id' => $user->id,
                'url' => $publicUrl,
                'alt_text' => $args['alt_text'] ?? $collection->name,
                'is_primary' => true,
                'metadata' => [
                    'original_filename' => $filename,
                    'mime_type' => $mimeType,
                    'uploaded_at' => now()->toIso8601String(),
                    'size' => strlen($decodedImage)
                ]
            ]);
            
            return $image;
        });
    }
}
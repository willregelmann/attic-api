<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;
use App\Http\Resources\ApiResponse;

class ItemController extends Controller
{
    /**
     * Display a listing of the authenticated user's items.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return ApiResponse::error('Authentication required', 401);
        }

        $items = Item::where('user_id', $user->id)
            ->with(['collectible.collection'])
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'personal_notes' => $item->personal_notes,
                    'is_favorite' => $item->is_favorite ?? false,
                    'location' => $item->location,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                    'user_images' => $item->user_images ?? [],
                    'collectible_id' => $item->collectible_id,
                    'collectible' => $item->collectible ? [
                        'id' => $item->collectible->id,
                        'name' => $item->collectible->name,
                        'description' => $item->collectible->description,
                        'category' => $item->collectible->category,
                        'image_urls' => $item->collectible->image_urls,
                        'collection_id' => $item->collectible->collection_id,
                        'collection' => $item->collectible->collection ? [
                            'id' => $item->collectible->collection->id,
                            'name' => $item->collectible->collection->name,
                            'category' => $item->collectible->collection->category,
                        ] : null,
                    ] : null,
                ];
            });

        return ApiResponse::success($items);
    }

    /**
     * Store a newly created item in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return ApiResponse::error('Authentication required', 401);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'collectible_id' => 'nullable|exists:collectibles,id',
            'personal_notes' => 'nullable|string',
            'is_favorite' => 'boolean',
            'location' => 'nullable|string|max:255',
            'user_images' => 'nullable|array',
        ]);

        $item = Item::create([
            'user_id' => $user->id,
            'collectible_id' => $request->collectible_id,
            'name' => $request->name,
            'personal_notes' => $request->personal_notes,
            'is_favorite' => $request->boolean('is_favorite', false),
            'location' => $request->location,
            'user_images' => $request->user_images ?? [],
        ]);

        return ApiResponse::success($item, 'Item created successfully', 201);
    }

    /**
     * Display the specified item.
     */
    public function show(Request $request, Item $item)
    {
        $user = $request->user();
        
        if (!$user || $item->user_id !== $user->id) {
            return ApiResponse::error('Item not found or access denied', 404);
        }

        $item->load(['collectible.collection']);

        return ApiResponse::success($item);
    }

    /**
     * Update the specified item in storage.
     */
    public function update(Request $request, Item $item)
    {
        $user = $request->user();
        
        if (!$user || $item->user_id !== $user->id) {
            return ApiResponse::error('Item not found or access denied', 404);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'collectible_id' => 'nullable|exists:collectibles,id',
            'personal_notes' => 'nullable|string',
            'is_favorite' => 'boolean',
            'location' => 'nullable|string|max:255',
            'user_images' => 'nullable|array',
        ]);

        $item->update($request->only([
            'name', 'collectible_id', 'personal_notes', 'is_favorite', 'location', 'user_images'
        ]));

        return ApiResponse::success($item, 'Item updated successfully');
    }

    /**
     * Remove the specified item from storage.
     */
    public function destroy(Request $request, Item $item)
    {
        $user = $request->user();
        
        if (!$user || $item->user_id !== $user->id) {
            return ApiResponse::error('Item not found or access denied', 404);
        }

        $item->delete();
        return ApiResponse::success(null, 'Item deleted successfully');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use Illuminate\Http\Request;
use App\Http\Resources\ApiResponse;
use Illuminate\Support\Str;

class CollectionController extends Controller
{
    /**
     * Display a listing of the collections.
     */
    public function index()
    {
        try {
            // Return static data first to test if controller loading works
            return ApiResponse::success([
                'collections' => [],
                'count' => 0,
                'debug' => 'Controller is working, returning empty collections for now',
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Controller error: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Display the specified collection.
     */
    public function show(Collection $collection)
    {
        $collectionData = [
            'id' => $collection->id,
            'name' => $collection->name,
            'category' => $collection->category,
            'description' => $collection->description,
            'completion' => 0, // TODO: Calculate completion
            'totalItems' => $collection->collectibles()->count(),
            'ownedItems' => 0, // TODO: Calculate owned items
            'recentActivity' => 'No recent activity',
            'year' => $collection->metadata['year'] ?? null,
            'metadata' => $collection->metadata,
        ];

        return ApiResponse::success($collectionData);
    }

    /**
     * Store a newly created collection in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'description' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        $collection = Collection::create([
            'name' => $request->name,
            'slug' => \Str::slug($request->name),
            'category' => $request->category,
            'description' => $request->description,
            'metadata' => $request->metadata ?? [],
            'status' => 'active',
        ]);

        return ApiResponse::success($collection, 'Collection created successfully', 201);
    }

    /**
     * Update the specified collection in storage.
     */
    public function update(Request $request, Collection $collection)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'category' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        $collection->update($request->only(['name', 'category', 'description', 'metadata']));

        if ($request->has('name')) {
            $collection->slug = \Str::slug($request->name);
            $collection->save();
        }

        return ApiResponse::success($collection, 'Collection updated successfully');
    }

    /**
     * Remove the specified collection from storage.
     */
    public function destroy(Collection $collection)
    {
        $collection->delete();
        return ApiResponse::success(null, 'Collection deleted successfully');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Collectible;
use Illuminate\Http\Request;
use App\Http\Resources\ApiResponse;

class CollectibleController extends Controller
{
    /**
     * Display a listing of the collectibles.
     */
    public function index()
    {
        $collectibles = Collectible::with('collection')->get()->map(function ($collectible) {
            return [
                'id' => $collectible->id,
                'name' => $collectible->name,
                'description' => $collectible->description,
                'category' => $collectible->category,
                'image_urls' => $collectible->image_urls ?? [],
                'collection_id' => $collectible->collection_id,
                'collection' => $collectible->collection ? [
                    'id' => $collectible->collection->id,
                    'name' => $collectible->collection->name,
                    'category' => $collectible->collection->category,
                ] : null,
            ];
        });

        return ApiResponse::success($collectibles);
    }

    /**
     * Display the specified collectible.
     */
    public function show(Collectible $collectible)
    {
        $collectible->load('collection');

        $collectibleData = [
            'id' => $collectible->id,
            'name' => $collectible->name,
            'description' => $collectible->description,
            'category' => $collectible->category,
            'image_urls' => $collectible->image_urls ?? [],
            'base_attributes' => $collectible->base_attributes ?? [],
            'variants' => $collectible->variants ?? [],
            'digital_metadata' => $collectible->digital_metadata ?? [],
            'collection_id' => $collectible->collection_id,
            'collection' => $collectible->collection ? [
                'id' => $collectible->collection->id,
                'name' => $collectible->collection->name,
                'category' => $collectible->collection->category,
            ] : null,
        ];

        return ApiResponse::success($collectibleData);
    }

    /**
     * Store a newly created collectible in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'collection_id' => 'required|exists:collections,id',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'image_urls' => 'nullable|array',
            'base_attributes' => 'nullable|array',
            'variants' => 'nullable|array',
            'digital_metadata' => 'nullable|array',
        ]);

        $collectible = Collectible::create([
            'name' => $request->name,
            'collection_id' => $request->collection_id,
            'description' => $request->description,
            'category' => $request->category,
            'image_urls' => $request->image_urls ?? [],
            'base_attributes' => $request->base_attributes ?? [],
            'variants' => $request->variants ?? [],
            'digital_metadata' => $request->digital_metadata ?? [],
        ]);

        return ApiResponse::success($collectible, 'Collectible created successfully', 201);
    }

    /**
     * Update the specified collectible in storage.
     */
    public function update(Request $request, Collectible $collectible)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'collection_id' => 'sometimes|exists:collections,id',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'image_urls' => 'nullable|array',
            'base_attributes' => 'nullable|array',
            'variants' => 'nullable|array',
            'digital_metadata' => 'nullable|array',
        ]);

        $collectible->update($request->only([
            'name', 'collection_id', 'description', 'category', 
            'image_urls', 'base_attributes', 'variants', 'digital_metadata'
        ]));

        return ApiResponse::success($collectible, 'Collectible updated successfully');
    }

    /**
     * Remove the specified collectible from storage.
     */
    public function destroy(Collectible $collectible)
    {
        $collectible->delete();
        return ApiResponse::success(null, 'Collectible deleted successfully');
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\SpeciesResource;
use App\Models\Species;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

class SpeciesController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->integer('per_page', 12);
        if ($perPage < 1) {
            $perPage = 12;
        }

        $cacheKey = 'species:index:' . md5(json_encode($request->all()) . $perPage . $request->get('page', 1));

        $species = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($request, $perPage) {
            $query = Species::query();

            if ($search = $request->string('search')->toString()) {
                $query->where(function ($q) use ($search): void {
                    $q->where('common_name', 'like', "%{$search}%")
                        ->orWhere('scientific_name', 'like', "%{$search}%")
                        ->orWhere('family', 'like', "%{$search}%");
                });
            }

            foreach (['care_level', 'sunlight', 'watering', 'toxicity', 'growth_rate'] as $field) {
                if ($value = $request->string($field)->toString()) {
                    $query->where($field, $value);
                }
            }

            $query->latest('updated_at');

            return $query->paginate($perPage);
        });

        return SpeciesResource::collection($species);
    }

    public function show(Species $species): SpeciesResource
    {
        $cacheKey = 'species:show:' . $species->id;
        
        $species = Cache::remember($cacheKey, now()->addMinutes(30), fn () => $species);

        return new SpeciesResource($species);
    }
}

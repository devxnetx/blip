<?php

namespace App\Support;

use App\Models\Pin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class UpcomingHorizon
{
    public const THRESHOLD = 50;

    public const DAYS = 20;

    /**
     * A crowded city shows upcoming events only through this instant.
     * Quieter cities keep every future event until the map filters exist.
     */
    public static function until(): Carbon
    {
        return now()->addDays(self::DAYS);
    }

    /**
     * Cities with more than 50 upcoming events still on the map.
     *
     * @return list<array{name: string, latitude: float, longitude: float}>
     */
    public static function crowded(): array
    {
        $cities = MapCities::all();
        $counts = array_fill_keys(array_column($cities, 'name'), 0);

        Pin::query()
            ->onMap()
            ->where('kind', Pin::KIND_UPCOMING)
            ->where('starts_at', '>', now())
            ->get(['latitude', 'longitude'])
            ->each(function (Pin $pin) use ($cities, &$counts): void {
                $name = self::nearestName((float) $pin->latitude, (float) $pin->longitude, $cities);

                if ($name !== null) {
                    $counts[$name]++;
                }
            });

        return array_values(array_filter(
            $cities,
            fn (array $city): bool => $counts[$city['name']] > self::THRESHOLD,
        ));
    }

    public static function isCrowded(string $name): bool
    {
        foreach (self::crowded() as $city) {
            if ($city['name'] === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Drop events that start after the window when they sit in a crowded city.
     * Notes and events that have already started stay.
     */
    public static function apply(Builder $query): Builder
    {
        $cities = self::crowded();

        if ($cities === []) {
            return $query;
        }

        $until = self::until();

        return $query->where(function (Builder $keep) use ($cities, $until): void {
            $keep->where(function (Builder $soon) use ($until): void {
                $soon->where('kind', '!=', Pin::KIND_UPCOMING)
                    ->orWhereNull('starts_at')
                    ->orWhere('starts_at', '<=', $until);
            })->orWhere(function (Builder $outside) use ($cities): void {
                foreach ($cities as $city) {
                    [$south, $north, $west, $east] = self::box($city);
                    $outside->where(function (Builder $notHere) use ($south, $north, $west, $east): void {
                        $notHere->where('latitude', '<', $south)
                            ->orWhere('latitude', '>', $north)
                            ->orWhere('longitude', '<', $west)
                            ->orWhere('longitude', '>', $east);
                    });
                }
            });
        });
    }

    /**
     * @param  list<array{name: string, latitude: float, longitude: float}>  $cities
     */
    private static function nearestName(float $latitude, float $longitude, array $cities): ?string
    {
        $name = null;
        $nearest = MapCities::RADIUS_METERS;

        foreach ($cities as $city) {
            $meters = Pin::metersBetween($latitude, $longitude, $city['latitude'], $city['longitude']);

            if ($meters < $nearest) {
                $nearest = $meters;
                $name = $city['name'];
            }
        }

        return $name;
    }

    /**
     * Square around the city radius. It contains every pin that city can claim.
     *
     * @param  array{latitude: float, longitude: float}  $city
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    private static function box(array $city): array
    {
        $latPad = MapCities::RADIUS_METERS / 111320;
        $lngPad = MapCities::RADIUS_METERS / (111320 * max(cos(deg2rad($city['latitude'])), 0.2));

        return [
            $city['latitude'] - $latPad,
            $city['latitude'] + $latPad,
            $city['longitude'] - $lngPad,
            $city['longitude'] + $lngPad,
        ];
    }
}

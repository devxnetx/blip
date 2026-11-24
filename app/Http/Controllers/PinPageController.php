<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Pin;
use App\Models\Reply;
use App\Support\LinkPreview;
use App\Support\Phone;
use App\Support\PinPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PinPageController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->phoneRedirect($request)) {
            return $redirect;
        }

        return $this->render($request, null, null);
    }

    public function city(Request $request, string $city): View|RedirectResponse
    {
        if ($redirect = $this->phoneRedirect($request)) {
            return $redirect;
        }

        return $this->render($request, $city, null);
    }

    public function show(Request $request, string $city, string $pin): View|RedirectResponse
    {
        $id = self::idFromToken($pin);
        $found = $id === null ? null : Pin::query()->find($id);

        if ($found === null || ! $found->isPublic() || $found->suppressed_at !== null) {
            abort(404);
        }

        $cities = PinPage::directory();
        $path = PinPage::path($found, $cities);

        if ($path === null) {
            abort(404);
        }

        if ($redirect = $this->phoneRedirect($request, $found)) {
            return $redirect;
        }

        $current = '/'.$request->path();

        if ($current !== $path) {
            $query = $request->getQueryString();

            return redirect($path.($query ? '?'.$query : ''), 301);
        }

        return $this->render($request, $city, $found, $cities);
    }

    public function sitemap(Request $request): Response
    {
        $root = rtrim($request->getSchemeAndHttpHost(), '/');

        return response()
            ->view('sitemap', ['urls' => PinPage::sitemapUrls($root)])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(Request $request): Response
    {
        $root = rtrim($request->getSchemeAndHttpHost(), '/');
        $body = "User-agent: *\nAllow: /\n\nSitemap: {$root}/sitemap.xml\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function phoneRedirect(Request $request, ?Pin $pin = null): ?RedirectResponse
    {
        if (! Phone::is($request->userAgent())) {
            return null;
        }

        return redirect()->route('map', $pin ? ['pin' => $pin->id] : []);
    }

    /**
     * @param  list<array{name: string, slug: string, latitude: float, longitude: float, find: string}>|null  $cities
     */
    private function render(Request $request, ?string $citySlug, ?Pin $pin, ?array $cities = null): View
    {
        $cities ??= PinPage::directory();
        $city = null;

        if ($citySlug !== null) {
            $city = PinPage::cityBySlug($cities, $citySlug);

            if ($city === null) {
                abort(404);
            }
        }

        $category = $request->query('category');
        $listed = Category::listedOptions();
        $category = is_string($category) && array_key_exists($category, $listed) ? $category : null;
        $km = (int) $request->query('km', 18);
        $km = max(1, min(30, $km));

        $pins = $city === null ? [] : PinPage::pinsInCity($city, $category, $km, $cities);
        $replies = $pin === null
            ? collect()
            : Reply::query()->where('pin_id', $pin->id)->where('author_only', false)->oldest()->get();

        $root = rtrim($request->getSchemeAndHttpHost(), '/');
        $map = [
            'latitude' => $pin?->latitude ?? $city['latitude'] ?? 42.4258,
            'longitude' => $pin?->longitude ?? $city['longitude'] ?? 25.6345,
            'zoom' => $pin !== null ? 17 : 12,
            'marked' => $pin !== null,
        ];

        if ($pin !== null && $city !== null) {
            $url = $root.PinPage::path($pin, $cities);
            $description = trim((string) $pin->description);
            $preview = LinkPreview::page(
                $pin->message.' — '.$city['name'].' — Kostaa',
                $description !== '' ? $description : LinkPreview::FALLBACK_DESCRIPTION,
                $url,
            );
            $image = $pin->imageUrl();
            if ($image !== null) {
                $preview['image'] = $image;
                $path = strtolower((string) parse_url($image, PHP_URL_PATH));
                $preview['image_type'] = match (true) {
                    str_ends_with($path, '.png') => 'image/png',
                    str_ends_with($path, '.webp') => 'image/webp',
                    str_ends_with($path, '.gif') => 'image/gif',
                    default => 'image/jpeg',
                };
            }
            $jsonLd = PinPage::jsonLd($pin, $city, $url);
        } elseif ($city !== null) {
            $preview = LinkPreview::page(
                'Пинове в '.$city['name'].' — Kostaa',
                'Какво се случва в '.$city['name'].' в момента.',
                $root.'/pins/'.$city['slug'],
            );
            $jsonLd = null;
        } else {
            $preview = LinkPreview::page(
                'Пинове — Kostaa',
                'Пинове по градове. Избери град и отвори бележка или събитие.',
                $root.'/pins',
            );
            $jsonLd = null;
        }

        return view('pins', [
            'wide' => true,
            'preview' => $preview,
            'cities' => PinPage::withCounts($cities),
            'city' => $city,
            'pins' => $pins,
            'pin' => $pin,
            'replies' => $replies,
            'listed' => $listed,
            'category' => $category,
            'km' => $km,
            'map' => $map,
            'jsonLd' => $jsonLd,
            'query' => array_filter([
                'km' => (string) $km,
                'category' => $category,
            ], fn (?string $value): bool => $value !== null && $value !== ''),
        ]);
    }

    private static function idFromToken(string $token): ?int
    {
        if (preg_match('/-(\d+)$/', $token, $match) !== 1) {
            return null;
        }

        return (int) $match[1];
    }
}

<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guards against the "stale assets on installed phones" trap.
 *
 * The service worker caches same-origin assets. When style.css changed on disk
 * but the installed app kept serving the old cached copy, the hamburger menu
 * never appeared on a real phone even though it worked in DevTools.
 */
class AssetCacheTest extends TestCase
{
    private function sw(): string
    {
        return (string) file_get_contents(public_path('sw.js'));
    }

    public function test_asset_urls_carry_a_cache_busting_version(): void
    {
        // /login renders the same layout without touching the database.
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#/assets/css/style\.css\?v=\d+#',
            $html,
            'style.css must be versioned so a stale service worker cache cannot pin it.'
        );

        $this->assertMatchesRegularExpression('#/assets/js/nav\.js\?v=\d+#', $html);
        $this->assertMatchesRegularExpression('#/assets/js/pwa\.js\?v=\d+#', $html);
    }

    public function test_the_version_helper_follows_the_file_mtime(): void
    {
        $file = public_path('assets/css/style.css');
        $expected = asset('assets/css/style.css').'?v='.filemtime($file);

        $this->assertSame($expected, asset_versioned('assets/css/style.css'));
    }

    public function test_the_version_helper_passes_through_missing_files(): void
    {
        $this->assertSame(
            asset('assets/js/does-not-exist.js'),
            asset_versioned('assets/js/does-not-exist.js')
        );
    }

    public function test_every_precached_script_exists_on_disk(): void
    {
        preg_match_all("#'(/assets/[^']+)'#", $this->sw(), $matches);

        $this->assertNotEmpty($matches[1], 'Expected a precache list in sw.js.');

        foreach ($matches[1] as $path) {
            $this->assertFileExists(
                public_path(ltrim($path, '/')),
                "sw.js precaches {$path} but the file does not exist."
            );
        }
    }

    public function test_the_nav_helper_is_precached(): void
    {
        $this->assertStringContainsString(
            "'/assets/js/nav.js'",
            $this->sw(),
            'nav.js must be precached; forgetting it left the old stylesheet in place.'
        );
    }

    public function test_static_assets_use_stale_while_revalidate_not_cache_first(): void
    {
        $sw = $this->sw();

        $this->assertStringContainsString('staleWhileRevalidate', $sw);
        $this->assertStringNotContainsString(
            'async function cacheFirst',
            $sw,
            'cache-first pins an asset forever after the first visit.'
        );
    }

    public function test_the_cache_version_is_bumped_so_installed_apps_rebuild(): void
    {
        $this->assertMatchesRegularExpression(
            "/const VERSION = 'v\d+';/",
            $this->sw(),
            'Bump VERSION whenever sw.js changes so installed apps rebuild their caches.'
        );
    }

    public function test_the_service_worker_never_caches_api_or_receipt_responses(): void
    {
        $sw = $this->sw();

        $this->assertStringContainsString("url.pathname.startsWith('/api/')", $sw);
        $this->assertStringContainsString("url.pathname.startsWith('/receipt/')", $sw);
        $this->assertStringContainsString("if (request.method !== 'GET')", $sw);
    }
}

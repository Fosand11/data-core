<?php

namespace Tests\Feature;

use Tests\TestCase;

class BrandingTest extends TestCase
{
    public function test_the_page_head_declares_the_icons_and_the_link_preview(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('rel="icon"', false)
            ->assertSee('favicon.svg', false)
            ->assertSee('favicon.ico', false)
            ->assertSee('rel="apple-touch-icon"', false)
            ->assertSee('property="og:image"', false)
            ->assertSee('og-image.png', false)
            ->assertSee('name="twitter:card"', false);
    }

    public function test_the_icon_and_preview_files_exist_and_are_not_empty(): void
    {
        // public/favicon.ico shipped as a 0-byte file once; keep it from regressing.
        foreach (['favicon.svg', 'favicon.ico', 'apple-touch-icon.png', 'og-image.png'] as $file) {
            $this->assertFileExists(public_path($file));
            $this->assertGreaterThan(100, filesize(public_path($file)), "{$file} looks empty");
        }
    }

    public function test_the_link_preview_image_has_the_size_social_networks_expect(): void
    {
        [$width, $height] = getimagesize(public_path('og-image.png'));

        $this->assertSame([1200, 630], [$width, $height]);
    }

    public function test_the_product_name_comes_from_the_configuration_everywhere_in_the_head(): void
    {
        config(['app.name' => 'Nombre de prueba']);

        $this->get('/')
            ->assertOk()
            ->assertSee('<title inertia>Nombre de prueba</title>', false)
            ->assertSee('<meta name="application-name" content="Nombre de prueba">', false)
            ->assertSee('<meta property="og:site_name" content="Nombre de prueba">', false)
            ->assertSee('<meta property="og:title" content="Nombre de prueba · Pregúntale a tus datos">', false);
    }

    public function test_the_shipped_default_name_is_the_product_and_never_the_framework(): void
    {
        // The production image has no .env file, so this default is what the tab shows.
        // Read from the file, not from config(): a developer's own .env may set another APP_NAME.
        $this->assertStringContainsString("'name' => env('APP_NAME', 'Atisbo')", (string) file_get_contents(config_path('app.php')));
        $this->assertStringNotContainsString('Laravel', (string) file_get_contents(resource_path('views/app.blade.php')));
    }

    public function test_the_frontend_does_not_take_the_name_from_a_build_time_variable(): void
    {
        // VITE_APP_NAME is baked in at build time; the Docker build has no .env, so it fell back to "Laravel".
        $this->assertStringNotContainsString('VITE_APP_NAME', (string) file_get_contents(resource_path('js/app.ts')));
        $this->assertStringNotContainsString('VITE_APP_NAME', (string) file_get_contents(base_path('.env.example')));
    }
}

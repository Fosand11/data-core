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
}

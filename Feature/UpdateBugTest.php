<?php

namespace Tests\Feature;

use App\Models\Page;
use Cache;
use Database\Seeders\PageSeeder;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class UpdateBugTest extends TestCase
{
    private $page_id = 1;
    private array $data;

    public function setUp(): void
    {
        parent::setUp();

        $this->seed(PageSeeder::class);

        $this->data = [
            'title' => $this->faker->words(3, true),
            'sub_title' => $this->faker->sentence()
        ];
    }

    /* TESTS TO CHECK FUNCTIONALITY IS THE SAME */

    public function test_get_status_ok()
    {
        $this->get("/api/pages/{$this->page_id}")
            ->assertStatus(200);
    }

    public function test_get_has_correct_data()
    {
        $page = Page::find($this->page_id);

        $this->get("/api/pages/{$this->page_id}")
            ->assertJson(function (AssertableJson $body) use ($page) {
                $body->has('data', function (AssertableJson $data) use ($page) {
                    $data->where('id', $page->id)
                        ->where('title', $page->title)
                        ->where('sub_title', $page->sub_title)
                        ->has('created_at')
                        ->has('updated_at');
                });
            });
    }

    public function test_get_uses_cache()
    {
        Cache::shouldReceive('remember')
            ->once()
            ->with(
                "page_{$this->page_id}",
                86400,
                \Closure::class
            );

        $this->get("/api/pages/{$this->page_id}")
            ->assertStatus(200);
    }

    public function test_update_status_ok()
    {
        $this->patch("/api/pages/{$this->page_id}", $this->data)
            ->assertStatus(200);
    }

    public function test_update_updates_db()
    {
        $this->patch("/api/pages/{$this->page_id}", $this->data);

        $this->assertDatabaseHas('pages', [
            'id' => $this->page_id,
            ...$this->data
        ]);
    }

    /* TEST IF THEY ACTUALLY FIXED THE BUG */

    public function test_bug_fixed_aka_instant_update()
    {
        $page = Page::find($this->page_id);

        $this->get("/api/pages/{$this->page_id}")
            ->assertJson(function (AssertableJson $body) use ($page) {
                $body->has('data', function (AssertableJson $data) use ($page) {
                    $data->where('id', $page->id)
                        ->where('title', $page->title)
                        ->where('sub_title', $page->sub_title)
                        ->has('created_at')
                        ->has('updated_at');
                });
            });

        $this->patch("/api/pages/{$this->page_id}", $this->data)
            ->assertJson(function (AssertableJson $body) {
                $body->has('data', function (AssertableJson $data) {
                    $data->where('id', $this->page_id)
                        ->where('title', $this->data['title'])
                        ->where('sub_title', $this->data['sub_title'])
                        ->has('created_at')
                        ->has('updated_at');
                });
            });

        $this->get("/api/pages/{$this->page_id}")
            ->assertJson(function (AssertableJson $body) {
                $body->has('data', function (AssertableJson $data) {
                    $data->where('id', $this->page_id)
                        ->where('title', $this->data['title'])
                        ->where('sub_title', $this->data['sub_title'])
                        ->has('created_at')
                        ->has('updated_at');
                });
            });
    }
}

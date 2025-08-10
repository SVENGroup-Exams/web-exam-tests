<?php

namespace Tests\Feature;

use App\Enums\SurveyCountry;
use App\Models\SurveyResponse;
use Database\Seeders\SurveyResponseSeeder;
use Database\Seeders\SurveySeeder;
use DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class SurveyListTest extends TestCase
{

    private $survey_id = 2;
    private $req_headers = ['accept' => 'application/json'];

    public function setUp(): void
    {
        parent::setUp();

        $this->seed([SurveySeeder::class, SurveyResponseSeeder::class]);
    }

    private function getValidCountryCodeInputs(): array
    {
        return array_map(function (SurveyCountry $case) {
            return $case->value;
        }, SurveyCountry::cases());
    }

    private function testRequest(array $data): \Illuminate\Testing\TestResponse
    {
        return $this->get(
            "/api/surveys/{$this->survey_id}/responses?" . http_build_query($data),
            $this->req_headers
        );
    }

    public function test_status_ok()
    {
        $this->testRequest([])
            ->assertStatus(200);
    }

    public function test_complete_response()
    {
        $this->testRequest([])
            ->assertJson(function (AssertableJson $body) {
                $body
                    ->has('data.0', function (AssertableJson $json) {
                        $json->hasAll(['id', 'survey_id', 'country_code', 'score', 'created_at', 'updated_at']);
                    })
                    ->has('page_size')
                    ->has('page');
            });
    }

    public function test_default_values()
    {
        $this->testRequest([])
            ->assertJson(function (AssertableJson $body) {
                $body->has('data', 20)
                    ->where('page_size', 20)
                    ->where('page', 1);
            });
    }

    public function test_non_existent_survey()
    {
        $response= $this->testRequest([]);

        $this->assertFalse($response->isOk());
    }

    /* PAGE SIZE VALIDATION */

    public function test_valid_page_size_input()
    {
        $inputs = [20, 50, 100];

        foreach ($inputs as $input) {
            $this->testRequest(['page_size' => $input])
                ->assertJson(function (AssertableJson $body) use ($input) {
                    $body->has('data')
                        ->where('page_size', $input)
                        ->where('page', 1);
                });
        }
    }

    public function test_invalid_page_size_input()
    {
        $inputs = [21, 30, 200];

        foreach ($inputs as $input) {
            $this->testRequest(['page_size' => $input])
                ->assertJsonValidationErrorFor('page_size');
        }
    }

    public function test_invalid_data_type_on_page_size_input()
    {
        $inputs = ['a', 21.1, ['a']];

        foreach ($inputs as $input) {
            $this->testRequest(['page_size' => $input])
                ->assertJsonValidationErrorFor('page_size');
        }
    }

    /* PAGE INPUT VALIDATION */

    public function test_valid_page_input()
    {
        $inputs = [1, 2, 3];

        foreach ($inputs as $input) {
            $this->testRequest(['page' => $input])
                ->assertJson(function (AssertableJson $body) use ($input) {
                    $body->has('data')
                        ->where('page_size', 20)
                        ->where('page', $input);
                });
        }
    }

    public function test_invalid_page_input()
    {
        $inputs = ['a', 22.6, ['a']];

        foreach ($inputs as $input) {
            $this->testRequest(['page' => $input])
                ->assertJsonValidationErrorFor('page');
        }
    }

    /* COUNTRY CODE VALIDATION */
    public function test_valid_country_code_input()
    {
        $inputs = $this->getValidCountryCodeInputs();

        foreach ($inputs as $input) {
            $this->testRequest(['country_code' => $input])
                ->assertJson(function (AssertableJson $body) {
                    $body->has('data')
                        ->where('page_size', 20)
                        ->where('page', 1);
                });
        }
    }

    public function test_invalid_country_code_input()
    {
        $inputs = ['ca', 'hello'];

        foreach ($inputs as $input) {
            $this->testRequest(['country_code' => $input])
                ->assertJsonValidationErrorFor('country_code');
        }
    }

    public function test_invalid_data_type_on_country_code_input()
    {
        $inputs = [123, 21.1, ['a']];

        foreach ($inputs as $input) {
            $this->testRequest(['country_code' => $input])
                ->assertJsonValidationErrorFor('country_code');
        }
    }

    /* COUNTRY CODE FILTERING */

    public function test_country_code_filter_works()
    {
        $inputs = $this->getValidCountryCodeInputs();

        foreach ($inputs as $input) {
            $this->testRequest(['country_code' => $input])
                ->assertJson(function (AssertableJson $body) use ($input) {

                    $expected_count = SurveyResponse::where('survey_id', $this->survey_id)
                        ->where('country_code', $input)
                        ->limit(20)
                        ->count();

                    $body->has('data', $expected_count)
                        ->where('page_size', 20)
                        ->where('page', 1);
                });


        }
    }

    /* COUNTRY CODE INDEX */

    public function test_country_code_index_exists_on_survey_responses()
    {
        $index_name = 'survey_responses_country_code_index'; // Laravel default

        $indexes = DB::select("
        SELECT name FROM sqlite_master
        WHERE type = 'index'
          AND tbl_name = 'survey_responses'
        ");

        $index_names = collect($indexes)->pluck('name');

        $this->assertTrue(
            $index_names->contains($index_name),
            "Expected index [$index_name] not found in SQLite."
        );
    }
}

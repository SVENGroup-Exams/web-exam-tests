<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Str;
use Tests\TestCase;

class PetBookingTest extends TestCase
{

    private array $data;

    public function setUp(): void
    {
        parent::setUp();

        $this->data = [
            'name' => $this->faker->name,
            'email' => $this->faker->email,
            'phone_number' => "+639999999999",
            'frequency' => $this->faker->randomElement(['one_time', 'recurring']),
            'date' => $this->faker->dateTimeBetween('+1 day', '+1 year')->format('Y-m-d'),
            'time' => $this->faker->randomElement([
                'morning',
                'afternoon',
                'evening'
            ]),
            'notes' => ''
        ];
    }

    public function test_booking_page()
    {
        $this->get('/pets/book')
            ->assertStatus(200);
    }

    /* CHECK IF PAGE IS RETURNED */

    public function test_submit()
    {
        $this->post('/pets/bookings', $this->data)
            ->assertStatus(204);
    }

    /* TESTS TO CHECK BACKEND IS NOT MODIFIED */

    public function test_submit_properly_validated_name()
    {

        $valid = [
            $this->faker->name,
            "a",
            Str::random(255)
        ];

        $inputs = [
            ...$valid,
            123,
            Str::random(256),
            ['a', 1],
            "<p>hello</p>",
            "<?php\nconst a = 5"
        ];

        foreach ($inputs as $input) {
            $response = $this->post('/pets/bookings', [...$this->data, 'name' => $input]);
            if (in_array($input, $valid)) {
                $response->assertStatus(204);
            } else {
                $response->assertSessionHasErrors('name');
            }
        }
    }

    public function test_submit_properly_validated_email()
    {

        $valid = [
            $this->faker->email,
            Str::random(245) . "@gmail.com"
        ];

        $inputs = [
            ...$valid,
            'abcs',
            123,
            Str::random(246) . "@gmail.com",
            ['a', 1]
        ];

        foreach ($inputs as $input) {
            $response = $this->post('/pets/bookings', [...$this->data, 'email' => $input]);
            if (in_array($input, $valid)) {
                $response->assertStatus(204);
            } else {
                $response->assertSessionHasErrors('email');
            }
        }
    }

    public function test_submit_properly_validated_phone_number()
    {

        $valid = [
            '+639999999999',
            '+638888888888',
            '+639473827483',
        ];

        $inputs = [
            ...$valid,
            "123",
            "+645333452345",
            "hello",
            ['d','ddd']
        ];

        foreach ($inputs as $input) {
            $response = $this->post('/pets/bookings', [...$this->data, 'phone_number' => $input]);
            if (in_array($input, $valid)) {
                $response->assertStatus(204);
            } else {
                $response->assertSessionHasErrors('phone_number');
            }
        }
    }

    public function test_submit_properly_validated_frequency()
    {

        $valid = [
            'one_time',
            'recurring'
        ];

        $inputs = [
            ...$valid,
            "123",
            113,
            "hello there",
            ['dd']
        ];

        foreach ($inputs as $input) {
            $response = $this->post('/pets/bookings', [...$this->data, 'frequency' => $input]);
            if (in_array($input, $valid)) {
                $response->assertStatus(204);
            } else {
                $response->assertSessionHasErrors('frequency');
            }
        }
    }

    public function test_submit_properly_validated_date()
    {

        $valid = [
            $this->faker->dateTimeBetween('+1 day', '+1 year')->format('Y-m-d'),
            $this->faker->dateTimeBetween('+1 day', '+1 year')->format('Y-m-d'),
        ];

        $inputs = [
            ...$valid,
            "2024-09-22",
            $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
        ];

        foreach ($inputs as $input) {
            $response = $this->post('/pets/bookings', [...$this->data, 'date' => $input]);
            if (in_array($input, $valid)) {
                $response->assertStatus(204);
            } else {
                $response->assertSessionHasErrors('date');
            }
        }
    }

    public function test_submit_properly_validated_time()
    {

        $valid = [
            'morning',
            'afternoon',
            'evening'
        ];

        $inputs = [
            ...$valid,
            "123",
            113,
            "hello there",
            ['dd']
        ];

        foreach ($inputs as $input) {
            $response = $this->post('/pets/bookings', [...$this->data, 'time' => $input]);
            if (in_array($input, $valid)) {
                $response->assertStatus(204);
            } else {
                $response->assertSessionHasErrors('time');
            }
        }
    }

    public function test_submit_properly_validated_notes()
    {

        $valid = [
            $this->faker->sentence,
            "a",
            Str::random(1000),
            '',
            null
        ];

        $inputs = [
            ...$valid,
            123,
            Str::random(1001),
            ['a', 1],
            "<p>hello</p>",
            "<?php\nconst a = 5"
        ];

        foreach ($inputs as $input) {
            $response = $this->post('/pets/bookings', [...$this->data, 'notes' => $input]);
            if (in_array($input, $valid)) {
                $response->assertStatus(204);
            } else {
                $response->assertSessionHasErrors('notes');
            }
        }
    }
}

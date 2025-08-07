<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Database\Seeders\UserSeeder;
use DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class EWalletTest extends TestCase
{

    private int $user_id;
    private int $account_id;

    private int $user_id_2;
    private int $account_id_2;

    private array $headers;

    public function setUp(): void
    {
        parent::setUp();

        $this->seed([UserSeeder::class, AccountSeeder::class]);

        $user = User::first();

        $this->user_id = $user->id;
        $this->account_id = $user->accounts()->first()->id;

        $this->headers = ['x-user-id' => $this->user_id, 'accept' => 'application/json'];

        $user_2 = User::whereNot('id', $this->user_id)->first();

        $this->user_id_2 = $user_2->id;
        $this->account_id_2 = $user_2->accounts()->first()->id;

    }

    /* TEST IF THEY IMPLEMENTED MOCK AUTH */

    public function test_must_be_authenticated()
    {
        $response = $this->post(
            "/api/accounts/{$this->account_id}/send",
            [],
            []
        );

        $this->assertFalse($response->isOk());
    }

    /* TEST IF USER CAN ONLY SEND FROM OWN ACCOUNTS */

    public function test_cannot_send_from_account_of_different_user()
    {
        $account_id = $this->account_id + 1;

        $response = $this->post(
            "/api/accounts/{$account_id}/send",
            [],
            $this->headers
        );

        $this->assertFalse($response->isOk());
    }

    /* RECIPIENT ACCOUNT VALIDATION */

    public function test_valid_recipient_account()
    {
        $sender_account_id = $this->account_id;
        $recipient_account_id = $this->account_id_2;
        $recipient_account_id_2 =
            User::whereNotIn('id', [$this->user_id, $this->user_id_2])
                ->first()
                ->accounts()
                ->first()
                ->id;

        $inputs = [$recipient_account_id, $recipient_account_id_2];

        foreach ($inputs as $input) {
            $this
                ->post(
                    "/api/accounts/{$sender_account_id}/send",
                    ['recipient_account_id' => $input, 'amount_cents' => 100],
                    $this->headers
                )
                ->assertStatus(200);
        }
    }

    public function test_invalid_recipient_account()
    {
        $sender_account_id = $this->account_id;

        $inputs = [400000, 0];

        foreach ($inputs as $input) {
            $this
                ->post(
                    "/api/accounts/{$sender_account_id}/send",
                    ['recipient_account_id' => $input, 'amount_cents' => 100],
                    $this->headers
                )
                ->assertJsonValidationErrorFor('recipient_account_id');
        }
    }

    public function test_invalid_data_type_recipient_account()
    {
        $sender_account_id = $this->account_id;

        $inputs = [47.5, 'hello', [1]];

        foreach ($inputs as $input) {
            $this
                ->post(
                    "/api/accounts/{$sender_account_id}/send",
                    ['recipient_account_id' => $input, 'amount_cents' => 100],
                    $this->headers
                )
                ->assertJsonValidationErrorFor('recipient_account_id');
        }
    }

    /* AMOUNT VALIDATION */

    public function test_valid_amount_cents()
    {
        $sender_account_id = $this->account_id;
        $recipient_account_id = $this->account_id_2;

        $inputs = [100, 700_00, 25_000_00];

        foreach ($inputs as $input) {
            $this
                ->post(
                    "/api/accounts/{$sender_account_id}/send",
                    ['recipient_account_id' => $recipient_account_id, 'amount_cents' => $input],
                    $this->headers
                )
                ->assertStatus(200);

            DB::table('transactions')->truncate();
        }
    }

    public function test_invalid_amount_cents()
    {
        $sender_account_id = $this->account_id;
        $recipient_account_id = $this->account_id_2;

        $inputs = [25_000_01, -1, 0];

        foreach ($inputs as $input) {
            $this
                ->post(
                    "/api/accounts/{$sender_account_id}/send",
                    ['recipient_account_id' => $recipient_account_id, 'amount_cents' => $input],
                    $this->headers
                )
                ->assertJsonValidationErrorFor('amount_cents');

            DB::table('transactions')->truncate();
        }
    }

    public function test_invalid_data_type_amount_cents()
    {
        $sender_account_id = $this->account_id;
        $recipient_account_id = $this->account_id_2;

        $inputs = ['a', ['1', 2, 'hello'], 24.4];

        foreach ($inputs as $input) {
            $this
                ->post(
                    "/api/accounts/{$sender_account_id}/send",
                    ['recipient_account_id' => $recipient_account_id, 'amount_cents' => $input],
                    $this->headers
                )
                ->assertJsonValidationErrorFor('amount_cents');

            DB::table('transactions')->truncate();
        }
    }

    /* TEST CAN'T SEND FROM AND TO SAME ACCOUNT */

    public function test_cant_send_to_the_same_account()
    {
        $sender_account_id = $this->account_id;
        $recipient_account_id = $this->account_id;

        $response = $this->post(
            "/api/accounts/{$sender_account_id}/send",
            ['recipient_account_id' => $recipient_account_id, 'amount_cents' => 100],
            $this->headers
        );

        $this->assertFalse($response->isOk());
    }

    /* OUTBOUND 24HR LIMIT */

    private function seedForOutboundTransactions($sender_account_id)
    {
        $secondary_account_id = Account::factory(['user_id' => $this->user_id])->create()->id;

        $now = now();

        Transaction::insert([
            ['account_id' => $sender_account_id, "amount_cents" => -1_250_000, 'created_at' => $now->copy()->subHours(24)->addMinute(), 'updated_at' => $now->copy()->subHours(24)->addMinute()], // counted
            ['account_id' => $sender_account_id, "amount_cents" => 1_250_000, 'created_at' => $now, 'updated_at' => $now], // not counted
            ['account_id' => $secondary_account_id, "amount_cents" => 0, 'created_at' => $now, 'updated_at' => $now], // not counted
            ['account_id' => $secondary_account_id, "amount_cents" => -1_249_901, 'created_at' => $now, 'updated_at' => $now], // counted
            ['account_id' => $sender_account_id, "amount_cents" => 1_250_000, 'created_at' => $now->copy()->subHours(24), 'updated_at' => $now->copy()->subHours(24)], // not counted
        ]);
    }

    public function test_under_24hr_outbound_transaction_limit()
    {

        $sender_account_id = $this->account_id;

        $recipient_account_id = $this->account_id_2;

        $inputs = [1, 20, 99];

        foreach ($inputs as $input) {
            $this->seedForOutboundTransactions($sender_account_id);

            $this
                ->post(
                    "/api/accounts/{$sender_account_id}/send",
                    ['recipient_account_id' => $recipient_account_id, 'amount_cents' => $input],
                    $this->headers
                )
                ->assertStatus(200);

            DB::table('transactions')->truncate();
        }

    }

    public function test_over_24hr_outbound_transaction_limit()
    {

        $sender_account_id = $this->account_id;
        $recipient_account_id = $this->account_id_2;

        $inputs = [1_00, 1_01, 20_00];

        foreach ($inputs as $input) {
            $this->seedForOutboundTransactions($sender_account_id);

            $response = $this->post(
                "/api/accounts/{$sender_account_id}/send",
                ['recipient_account_id' => $recipient_account_id, 'amount_cents' => $input],
                $this->headers
            );

            $this->assertFalse($response->isOk());

            DB::table('transactions')->truncate();
        }

    }

    /* INBOUND 24HR LIMIT */

    private function seedForInboundTransactions($recipient_account_id)
    {
        $now = now();

        $secondary_account_id = Account::factory(['user_id' => $this->user_id_2])->create()->id;

        Transaction::insert([
            ['account_id' => $recipient_account_id, "amount_cents" => 1_250_000, 'created_at' => $now->copy()->subHours(24)->addMinute(), 'updated_at' => $now->copy()->subHours(24)->addMinute()], // counted
            ['account_id' => $recipient_account_id, "amount_cents" => -1_250_000, 'created_at' => $now, 'updated_at' => $now], // not counted
            ['account_id' => $secondary_account_id, "amount_cents" => 0, 'created_at' => $now, 'updated_at' => $now], // not counted
            ['account_id' => $secondary_account_id, "amount_cents" => 1_249_901, 'created_at' => $now, 'updated_at' => $now], // counted
            ['account_id' => $recipient_account_id, "amount_cents" => 1_250_000, 'created_at' => $now->copy()->subHours(24), 'updated_at' => $now->copy()->subHours(24)], // not counted
        ]);
    }

    public function test_under_24hr_inbound_transaction_limit()
    {

        $sender_account_id = $this->account_id;
        $recipient_account_id = $this->account_id_2;

        $inputs = [1, 20, 99];

        foreach ($inputs as $input) {
            $this->seedForInboundTransactions($recipient_account_id);

            $this
                ->post(
                    "/api/accounts/{$sender_account_id}/send",
                    ['recipient_account_id' => $recipient_account_id, 'amount_cents' => $input],
                    $this->headers
                )->assertStatus(200);

            DB::table('transactions')->truncate();
        }
    }

    public function test_over_24hr_inbound_transaction_limit()
    {
        $sender_account_id = $this->account_id;
        $recipient_account_id = $this->account_id_2;

        $inputs = [1_00, 1_01, 20_00];

        foreach ($inputs as $input) {
            $this->seedForInboundTransactions($recipient_account_id);

            $response = $this->post(
                "/api/accounts/{$sender_account_id}/send",
                ['recipient_account_id' => $recipient_account_id, 'amount_cents' => $input],
                $this->headers
            );

            $this->assertFalse($response->isOk());

            DB::table('transactions')->truncate();
        }
    }

    /* HOURLY LIMIT */

    public function test_user_rate_limit()
    {
        $sender_account_id = $this->account_id;
        $recipient_account_id = $this->account_id_2;

        $inputs = [1, 1, 1, 1, 1, 1];

        foreach ($inputs as $index => $input) {

            $response = $this->post(
                "/api/accounts/{$sender_account_id}/send",
                ['recipient_account_id' => $recipient_account_id, 'amount_cents' => $input],
                $this->headers
            );

            if ($index !== 5) {
                $response->assertStatus(200);
            } else {
                $this->assertFalse($response->isOk());

                // confirm other users are unaffected
                $this->post(
                    "/api/accounts/{$recipient_account_id}/send",
                    ['recipient_account_id' => $sender_account_id, 'amount_cents' => $input],
                    [...$this->headers, 'x-user-id' => $this->user_id_2]
                );
            }

        }
    }

    /* SUCCESSFUL TRANSFERS */

    public function test_transfer_works()
    {
        $sender_account_id = $this->account_id;
        $recipient_account_id = $this->account_id_2;

        $sender_account = Account::find($sender_account_id);
        $recipient_account = Account::find($recipient_account_id);

        $sender_pre_bal = $sender_account->balance_cents;
        $recipient_pre_bal = $recipient_account->balance_cents;

        $amount = 5_000_00;

        $sender_post_bal = $sender_pre_bal - $amount;
        $recipient_post_bal = $recipient_pre_bal + $amount;

        $this
            ->post(
                "/api/accounts/{$sender_account_id}/send",
                ['recipient_account_id' => $recipient_account_id, 'amount_cents' => $amount],
                $this->headers
            )
            ->assertStatus(200)
            ->assertJson(function (AssertableJson $body) {
                $body->where('transaction_id', 1);
            });

        $this->assertDatabaseHas('accounts', [
            'id' => $sender_account_id,
            'balance_cents' => $sender_post_bal
        ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $recipient_account_id,
            'balance_cents' => $recipient_post_bal
        ]);


    }

    public function test_transactions_are_recorded()
    {
        $sender_account_id = $this->account_id;
        $recipient_account_id = $this->account_id_2;

        $amount = 5_000_00;

        $this
            ->post(
                "/api/accounts/{$sender_account_id}/send",
                ['recipient_account_id' => $recipient_account_id, 'amount_cents' => $amount],
                $this->headers
            )
            ->assertStatus(200);


        $this->assertDatabaseHas('transactions', [
            'id' => 1,
            'amount_cents' => -$amount
        ]);

        $this->assertDatabaseHas('transactions', [
            'id' => 2,
            'parent_id' => 1,
            'amount_cents' => $amount
        ]);
    }

    public function test_cannot_send_with_insufficient_funds()
    {
        $sender_account_id = $this->account_id;
        $recipient_account_id = $this->account_id_2;

        Account::where('id', $sender_account_id)->update(['balance_cents' => 1_000]);

        $response = $this->post(
            "/api/accounts/{$sender_account_id}/send",
            ['recipient_account_id' => $recipient_account_id, 'amount_cents' => 2_000],
            $this->headers
        );

        $response->assertStatus(409);
    }

}

<?php

namespace Tests\Feature;

use App\Models\LedgerEntry;
use App\Services\Finance\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class LedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_balanced_group_posts_matching_debits_and_credits(): void
    {
        $entries = app(LedgerService::class)->postBalancedGroup([
            [
                'account_code' => 'customer_cash',
                'entry_type' => 'debit',
                'amount' => 100000,
                'reference_type' => 'booking',
                'reference_id' => 1,
                'description' => 'Payment received',
            ],
            [
                'account_code' => 'escrow_liability',
                'entry_type' => 'credit',
                'amount' => 100000,
                'reference_type' => 'booking',
                'reference_id' => 1,
                'description' => 'Funds held in escrow',
            ],
        ]);

        $this->assertCount(2, $entries);
        $this->assertSame($entries[0]->transaction_group_ulid, $entries[1]->transaction_group_ulid);
        $this->assertSame(100000, $entries->where('entry_type', 'debit')->sum('amount'));
        $this->assertSame(100000, $entries->where('entry_type', 'credit')->sum('amount'));
    }

    public function test_unbalanced_group_is_rejected_without_writing_entries(): void
    {
        try {
            app(LedgerService::class)->postBalancedGroup([
                [
                    'account_code' => 'customer_cash',
                    'entry_type' => 'debit',
                    'amount' => 100000,
                    'reference_type' => 'booking',
                    'reference_id' => 1,
                    'description' => 'Payment received',
                ],
                [
                    'account_code' => 'escrow_liability',
                    'entry_type' => 'credit',
                    'amount' => 90000,
                    'reference_type' => 'booking',
                    'reference_id' => 1,
                    'description' => 'Funds held in escrow',
                ],
            ]);
            $this->fail('Expected an unbalanced journal to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ledger', $exception->errors());
        }

        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_reversal_posts_equal_and_opposite_entries_without_editing_original_rows(): void
    {
        $ledger = app(LedgerService::class);
        $original = $ledger->postBalancedGroup([
            [
                'account_code' => 'customer_cash',
                'entry_type' => 'debit',
                'amount' => 25000,
                'reference_type' => 'booking',
                'reference_id' => 7,
                'description' => 'Original debit',
            ],
            [
                'account_code' => 'escrow_liability',
                'entry_type' => 'credit',
                'amount' => 25000,
                'reference_type' => 'booking',
                'reference_id' => 7,
                'description' => 'Original credit',
            ],
        ]);

        $reversal = $ledger->reverseGroup($original->first()->transaction_group_ulid, 'Correcting duplicate journal');

        $this->assertCount(2, $reversal);
        $this->assertSame(50000, $reversal->sum('amount'));
        $this->assertSame(25000, $reversal->where('entry_type', 'debit')->sum('amount'));
        $this->assertSame(25000, $reversal->where('entry_type', 'credit')->sum('amount'));
        $this->assertDatabaseCount('ledger_entries', 4);

        $this->expectException(ValidationException::class);
        $ledger->reverseGroup($original->first()->transaction_group_ulid, 'Duplicate reversal');
    }

    public function test_ledger_entry_cannot_be_updated_or_deleted(): void
    {
        $entry = LedgerEntry::query()->create([
            'transaction_group_ulid' => '01JABCDEF0123456789ABCDEFGH',
            'account_code' => 'customer_cash',
            'entry_type' => 'debit',
            'amount' => 100,
            'reference_type' => 'booking',
            'reference_id' => 1,
            'description' => 'Immutable test entry',
            'created_at' => now(),
        ]);

        $this->expectException(LogicException::class);
        $entry->update(['amount' => 200]);
    }
}

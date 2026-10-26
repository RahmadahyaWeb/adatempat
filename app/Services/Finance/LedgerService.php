<?php

namespace App\Services\Finance;

use App\Models\LedgerEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LedgerService
{
    /**
     * Post an immutable balanced journal group. Amounts are integer IDR rupiah.
     *
     * @param array<int, array{account_code:string,entry_type:string,amount:int,reference_type:string,reference_id:int,description:string}> $entries
     * @return Collection<int, LedgerEntry>
     */
    public function postBalancedGroup(array $entries): Collection
    {
        $this->assertBalanced($entries);

        return DB::transaction(function () use ($entries): Collection {
            $group = (string) Str::ulid();
            $createdAt = now('UTC');
            $rows = [];

            foreach ($entries as $entry) {
                $rows[] = LedgerEntry::query()->create([
                    'transaction_group_ulid' => $group,
                    'account_code' => $entry['account_code'],
                    'entry_type' => $entry['entry_type'],
                    'amount' => (int) $entry['amount'],
                    'reference_type' => $entry['reference_type'],
                    'reference_id' => (int) $entry['reference_id'],
                    'description' => trim($entry['description']),
                    'created_at' => $createdAt,
                ]);
            }

            return new Collection($rows);
        }, attempts: 3);
    }

    /**
     * Corrections are represented by an equal-and-opposite journal, never by editing
     * or deleting the original rows. A group may be reversed only once.
     *
     * @return Collection<int, LedgerEntry>
     */
    public function reverseGroup(string $transactionGroupUlid, string $reason): Collection
    {
        return DB::transaction(function () use ($transactionGroupUlid, $reason): Collection {
            $original = LedgerEntry::query()
                ->where('transaction_group_ulid', $transactionGroupUlid)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($original->isEmpty()) {
                throw ValidationException::withMessages([
                    'transactionGroupUlid' => 'Kelompok jurnal yang akan dibalik tidak ditemukan.',
                ]);
            }

            $referenceId = $original->first()->getKey();
            $existingReversal = LedgerEntry::query()
                ->where('reference_type', 'ledger_reversal')
                ->where('reference_id', $referenceId)
                ->exists();

            if ($existingReversal) {
                throw ValidationException::withMessages([
                    'transactionGroupUlid' => 'Kelompok jurnal ini sudah memiliki entri pembalik.',
                ]);
            }

            $entries = $original->map(fn (LedgerEntry $entry): array => [
                'account_code' => $entry->account_code,
                'entry_type' => $entry->entry_type === LedgerEntry::TYPE_DEBIT
                    ? LedgerEntry::TYPE_CREDIT
                    : LedgerEntry::TYPE_DEBIT,
                'amount' => (int) $entry->amount,
                'reference_type' => 'ledger_reversal',
                'reference_id' => $referenceId,
                'description' => trim($reason) . ' (reversal of ' . $transactionGroupUlid . ')',
            ])->all();

            $this->assertBalanced($entries);

            $reversalGroup = (string) Str::ulid();
            $createdAt = now('UTC');

            foreach ($entries as $entry) {
                LedgerEntry::query()->create([
                    'transaction_group_ulid' => $reversalGroup,
                    'account_code' => $entry['account_code'],
                    'entry_type' => $entry['entry_type'],
                    'amount' => $entry['amount'],
                    'reference_type' => $entry['reference_type'],
                    'reference_id' => $entry['reference_id'],
                    'description' => $entry['description'],
                    'created_at' => $createdAt,
                ]);
            }

            return LedgerEntry::query()
                ->where('transaction_group_ulid', $reversalGroup)
                ->orderBy('id')
                ->get();
        }, attempts: 3);
    }

    private function assertBalanced(array $entries): void
    {
        if (count($entries) < 2) {
            throw ValidationException::withMessages([
                'ledger' => 'Jurnal berpasangan minimal memerlukan dua entri.',
            ]);
        }

        $debits = 0;
        $credits = 0;

        foreach ($entries as $entry) {
            if (
                ! in_array($entry['account_code'] ?? null, LedgerEntry::ACCOUNT_CODES, true)
                || ! in_array($entry['entry_type'] ?? null, [LedgerEntry::TYPE_DEBIT, LedgerEntry::TYPE_CREDIT], true)
                || ! is_numeric($entry['amount'] ?? null)
                || (int) $entry['amount'] < 1
                || ! is_string($entry['reference_type'] ?? null)
                || trim($entry['reference_type']) === ''
                || ! is_numeric($entry['reference_id'] ?? null)
                || (int) $entry['reference_id'] < 1
                || ! is_string($entry['description'] ?? null)
                || trim($entry['description']) === ''
            ) {
                throw ValidationException::withMessages([
                    'ledger' => 'Entri jurnal memiliki account, tipe, nominal, referensi, atau deskripsi yang tidak valid.',
                ]);
            }

            if ($entry['entry_type'] === LedgerEntry::TYPE_DEBIT) {
                $debits += (int) $entry['amount'];
            } else {
                $credits += (int) $entry['amount'];
            }
        }

        if ($debits !== $credits) {
            throw ValidationException::withMessages([
                'ledger' => 'Total debit dan kredit harus seimbang dalam satu kelompok jurnal.',
            ]);
        }
    }
}

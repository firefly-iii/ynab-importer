<?php

/**
 * GenerateTransactions.php
 * Copyright (c) 2020 james@firefly-iii.org
 *
 * This file is part of the Firefly III YNAB importer
 * (https://github.com/firefly-iii/ynab-importer).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * GenerateTransactions.php

 */

declare(strict_types=1);


namespace App\Services\Sync;

use App\Exceptions\ImportException;
use App\Services\Configuration\Configuration;
use App\Services\Sync\JobStatus\ProgressInformation;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Request\GetAccountRequest;
use GrumpyDictator\FFIIIApiSupport\Response\GetAccountResponse;

/**
 * Class GenerateTransactions.
 */
class GenerateTransactions
{
    use ProgressInformation;

    private array $accounts;
    private Configuration $configuration;

    /** @var array */
    private $targetAccounts;
    /** @var array */
    private $targetTypes;

    /**
     * GenerateTransactions constructor.
     */
    public function __construct()
    {
        $this->targetAccounts = [];
        $this->targetTypes    = [];
    }

    /**
     * @param array $array
     *
     * @throws ImportException
     * @return array
     */
    public function getTransactions(array $array): array
    {
        $return = [];
        /** @var array $entry */
        foreach ($array as $entry) {
            $group = $this->generateTransaction($entry);

            // check if has transactions:
            if (0 === count($group['transactions'])) {
                app('log')->warning('Filtered out a transaction group with no transactions');
                continue;
            }
            $return[] = $group;

        }
        $return = $this->filterSplitTypes($return);

        $this->addMessage(0, sprintf('Parsed %d YNAB transactions for further processing.', count($return)));

        return $return;
    }

    /**
     * @param Configuration $configuration
     */
    public function setConfiguration(Configuration $configuration): void
    {
        $this->configuration = $configuration;
        $this->accounts      = $configuration->getAccounts();
    }

    /**
     * @param array $group
     *
     * @return bool
     */
    private function isDifferentTypes(array $group): bool
    {
        if (1 === count($group['transactions'])) {
            return false;
        }
        $types = [];
        /** @var array $transaction */
        foreach ($group['transactions'] as $transaction) {
            $types[] = $transaction['type'];
        }

        return count(array_unique($types)) > 1;
    }

    /**
     * @param string $accountId
     *
     * @return int
     */
    private function ffAccountId(string $accountId): int
    {
        foreach ($this->configuration->getAccounts() as $budgetId => $accounts) {
            /**
             * @var string $ynabId
             * @var int    $fireflyId
             */
            foreach ($accounts as $ynabId => $fireflyId) {
                if ($ynabId === $accountId) {
                    return $fireflyId;
                }
            }
        }

        return 0;
    }

    /**
     * @param array $array
     *
     * @return array
     */
    private function filterEmptyFields(array $array): array
    {
        $fields = ['source_id', 'destination_id', 'source_name', 'destination_name'];
        foreach ($array['transactions'] as $index => $transaction) {
            foreach ($fields as $field) {
                if (array_key_exists($field, $transaction) && null === $transaction[$field]) {
                    unset($array['transactions'][$index][$field]);
                    app('log')->debug(sprintf('Removed field "%s"', $field));
                }
            }
        }

        return $array;
    }

    /**
     * @param array $array
     *
     * @return array
     */
    private function filterSplitTypes(array $array): array
    {
        $return = [];
        /** @var array $group */
        foreach ($array as $group) {
            if ($this->isDifferentTypes($group)) {
                // each transaction in the group is a separate transaction now
                foreach ($group['transactions'] as $transaction) {
                    $return[] = [
                        'apply_rules'             => $group['apply_rules'],
                        'error_if_duplicate_hash' => $group['error_if_duplicate_hash'],
                        'group_title'             => null,
                        'transactions'            => [$transaction],
                    ];
                }
                continue;
            }
            $return[] = $group;
        }

        return $return;
    }

    /**
     * @param array $entry
     *
     * @return array
     */
    private function generateTransaction(array $entry): array
    {
        $groupDescription = '' === (string) $entry['memo'] ? '(no description)' : $entry['memo'];
        $return           = [
            'apply_rules'             => $this->configuration->isRules(),
            'error_if_duplicate_hash' => true,
            'group_title'             => $groupDescription,
            'transactions'            => [],
        ];

        $index = 0;
        /** @var array $transaction */
        foreach ($entry['transactions'] as $transaction) {
            $amount = $this->positiveAmount($transaction['amount']);
            if (0 === $transaction['amount']) {
                app('log')->warning('Skipped transaction because amount is zero.');
                continue;
            }
            $return['transactions'][$index] = [
                'amount'           => $amount,
                'type'             => $transaction['amount'] > 0 ? 'deposit' : 'withdrawal',
                'description'      => '' === (string) $transaction['memo'] ? '(empty description)' : $transaction['memo'],
                'date'             => $transaction['date'],
                'notes'            => sprintf('Transaction ID: %s', $transaction['transaction_id']),
                'category_name'    => $transaction['category_name'],
                'source_id'        => null,
                'source_name'      => $transaction['account_name'],
                'destination_name' => $transaction['payee_name'],
                'destination_id'   => null,
            ];
            // double check source:
            $accountId = $this->ffAccountId($transaction['account_id']);
            if (0 !== $accountId) {
                $return['transactions'][$index]['source_id']   = $accountId;
                $return['transactions'][$index]['source_name'] = null;
            }

            // double check destination:
            $accountId = $this->ffAccountId((string) $transaction['payee_id']);
            if (0 !== $accountId) {
                $return['transactions'][$index]['destination_id']   = $accountId;
                $return['transactions'][$index]['destination_name'] = null;
            }

            // get transfer account:
            if (0 === strpos((string) $transaction['payee_name'], 'Transfer : ')) {
                // payee is one of your own accounts
                $return['transactions'][$index]['destination_id']   = $this->ffAccountId($transaction['transfer_account_id']);
                $return['transactions'][$index]['destination_name'] = null;
                $return['transactions'][$index]['type']             = 'transfer';
            }

            // if the original amount is > 0, then its a deposit and switch accounts.
            if ($transaction['amount'] > 0) {
                // switch!
                $orgSourceId                                        = $return['transactions'][$index]['source_id'];
                $orgSourceName                                      = $return['transactions'][$index]['source_name'];
                $return['transactions'][$index]['source_id']        = $return['transactions'][$index]['destination_id'];
                $return['transactions'][$index]['source_name']      = $return['transactions'][$index]['destination_name'];
                $return['transactions'][$index]['destination_id']   = $orgSourceId;
                $return['transactions'][$index]['destination_name'] = $orgSourceName;
            }
            $index++;
        }

        // make sure that the group title is empty when there is just one transaction (https://github.com/firefly-iii/firefly-iii/issues/4088)
        if(1 === count($return['transactions'])) {
            $return['group_title'] = null;
        }

        $return = $this->filterEmptyFields($return);

        return $return;
    }

    /**
     * @param int $accountId
     *
     * @throws ApiHttpException
     * @return string
     */
    private function getAccountType(int $accountId): string
    {
        $url   = (string) config('ynab.url');
        $token = (string) config('ynab.access_token');
        app('log')->debug(sprintf('Going to download account #%d', $accountId));
        $request = new GetAccountRequest($url, $token);
        $request->setId($accountId);
        /** @var GetAccountResponse $result */
        $result = $request->get();
        $type   = $result->getAccount()->type;

        app('log')->debug(sprintf('Discovered that account #%d is of type "%s"', $accountId, $type));

        return $type;
    }

    /**
     * @param string $name
     * @param string $iban
     *
     * @return int|null
     */
    private function getMappedId(string $name, string $iban): ?int
    {
        $fullName = $name;
        if ('' !== $iban) {
            $fullName = sprintf('%s (%s)', $name, $iban);
        }
        if (isset($this->configuration->getMapping()[$fullName])) {
            return (int) $this->configuration->getMapping()[$fullName];
        }

        return null;
    }

    /**
     * @param int $mappedId
     *
     * @throws ApiHttpException
     * @return string
     */
    private function getMappedType(int $mappedId): string
    {
        if (!isset($this->configuration->getAccountTypes()[$mappedId])) {
            app('log')->warning(sprintf('Cannot find account type for Firefly III account #%d.', $mappedId));
            $accountType             = $this->getAccountType($mappedId);
            $accountTypes            = $this->configuration->getAccountTypes();
            $accountTypes[$mappedId] = $accountType;
            $this->configuration->setAccountTypes($accountTypes);

            return $accountType;
        }

        return $this->configuration->getAccountTypes()[$mappedId] ?? 'expense';
    }

    /**
     * @param string $source
     * @param string $destination
     *
     * @throws ImportException
     * @return string
     */
    private function getTransactionType(string $source, string $destination): string
    {
        $combination = sprintf('%s-%s', $source, $destination);
        switch ($combination) {
            default:
                throw new ImportException(sprintf('Unknown combination: %s and %s', $source, $destination));
            case 'asset-expense':
                return 'withdrawal';
            case 'asset-asset':
                return 'transfer';
            case 'revenue-asset':
                return 'deposit';
        }
    }

    /**
     * @param int $amount
     *
     * @return string
     */
    private function positiveAmount(int $amount): string
    {
        if ($amount < 0) {
            $amount *= -1;
        }

        return (string) ($amount / 1000);
    }
}
